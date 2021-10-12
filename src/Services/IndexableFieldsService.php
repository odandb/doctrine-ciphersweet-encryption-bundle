<?php

declare(strict_types=1);


namespace Odandb\DoctrineCiphersweetEncryptionBundle\Services;


use Odandb\DoctrineCiphersweetEncryptionBundle\Configuration\EncryptedField;
use Odandb\DoctrineCiphersweetEncryptionBundle\Configuration\IndexableField;
use Odandb\DoctrineCiphersweetEncryptionBundle\Entity\IndexedEntityInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Exception\MissingPropertyFromReflectionException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManagerInterface;

class IndexableFieldsService
{
    public const ENCRYPTED_ANN_NAME = EncryptedField::class;
    public const INDEXABLE_ANN_NAME = IndexableField::class;

    private Reader $annReader;
    private EntityManagerInterface $em;
    private IndexesGenerator $indexesGenerator;

    public function __construct(Reader $annReader, EntityManagerInterface $em, IndexesGenerator $generator)
    {
        $this->annReader = $annReader;
        $this->em = $em;
        $this->indexesGenerator = $generator;
    }

    public function getChunksForMultiThread(string $className, int $chuncksLength): array
    {
        /** @var ServiceEntityRepository $repo */
        $repo = $this->em->getRepository($className);
        $result = $repo->createQueryBuilder('c')
            ->select('c.id')
            ->getQuery()
            ->getArrayResult();

        return array_chunk(array_column($result, 'id'), $chuncksLength);
    }

    public function buildContext(string $className, ?array $fieldnames): array
    {
        $contexts = [];

        $classMetadata = $this->em->getClassMetadata($className);

        if ($fieldnames === [] || $fieldnames === null) {
            $fieldnames = array_map(
                static function (\ReflectionProperty $refProperty): string {return $refProperty->name;},
                $classMetadata->getReflectionProperties()
            );
        }

        foreach ($fieldnames as $fieldname) {
            $refProperty = $classMetadata->getReflectionProperty($fieldname);

            if ($refProperty === null) {
                throw new MissingPropertyFromReflectionException(sprintf("No refProperty found for fieldname %s", $fieldname));
            }

            $indexableAnnotationConfig = $this->annReader->getPropertyAnnotation($refProperty, self::INDEXABLE_ANN_NAME);

            if ($indexableAnnotationConfig instanceof IndexableField) {
                $contexts []= ['refProperty' => $refProperty, 'indexableConfig' => $indexableAnnotationConfig];
            }
        }

        return $contexts;
    }

    public function purgeFiltersForContextAndIds(array $fieldsContexts, ?array $ids): void
    {
        /**
         * @var \ReflectionProperty $refProperty
         * @var IndexableField $indexableAnnotationConfig
         */
        foreach($fieldsContexts as ['refProperty' => $refProperty, 'indexableConfig' => $indexableAnnotationConfig]) {
            $qb = $this->em->createQueryBuilder()
                ->delete()
                ->from($indexableAnnotationConfig->indexesEntityClass, 'f');
            $qb->where('f.fieldname=:fieldname')
                ->setParameter('fieldname', $refProperty->name);

            if ($ids !== null && $ids !== []) {
                $qb->andWhere('f.targetEntity IN (:ids)')
                    ->setParameter('ids', $ids);
            }

            $qb->getQuery()->execute();
        }
    }

    /**
     * @throws \ReflectionException
     * @throws \Odandb\DoctrineCiphersweetEncryptionBundle\Exception\UndefinedGeneratorException
     */
    public function handleFilterableFieldsForChunck(string $className, ?array $ids, array $fieldsContexts, bool $needsToComputeChangeset = false): void
    {
        $criteria = $ids !== null && $ids !== [] ? ['id' => $ids] : [];
        $chunck = $this->em->getRepository($className)->findBy($criteria);
        foreach ($chunck as $entity) {
            $this->handleIndexableFieldsForEntity($entity, $fieldsContexts, $needsToComputeChangeset);
            $this->em->flush();
        }
    }

    /**
     * Permet de générer les valeurs indexables pour une entité et un contexte donné.
     *
     * @param object $entity
     * @param array $fieldsContexts
     * @return array
     * @throws \Odandb\DoctrineCiphersweetEncryptionBundle\Exception\UndefinedGeneratorException
     */
    public function generateIndexableValuesForEntity(object $entity, array $fieldsContexts): array
    {
        $searchIndexes = [];

        foreach ($fieldsContexts as ['refProperty' => $refProperty, 'indexableConfig' => $indexableAnnotationConfig]) {
            $value = $refProperty->getValue($entity);
            if ($value === null || $value === '') {
                continue;
            }

            $cleanValue = $value;
            $valueCleanerMethod = $indexableAnnotationConfig->valuePreprocessMethod ?? null;
            if ($valueCleanerMethod !== null && (method_exists($entity, $valueCleanerMethod) || method_exists(get_class($entity), $valueCleanerMethod))) {
                $cleanValue = $entity->$valueCleanerMethod($value);
            }

            // On appelle le service de génération des index de filtre qui va créer la collection de pattern possibles
            // en fonction de la ou des méthodes renseignées en annotation
            // Puis récupérer chaque "blind_index" associé à enregistrer en base
            $indexesMethods = $indexableAnnotationConfig->indexesGenerationMethods ?? [];

            $indexesToEncrypt = $this->indexesGenerator->generateAndEncryptFilters($cleanValue, $indexesMethods);
            $indexesToEncrypt [] = $value;
            $indexesToEncrypt = array_unique($indexesToEncrypt);

            $searchIndexes[$refProperty->getName()] = $indexesToEncrypt;
        }

        return $searchIndexes;
    }

    /**
     * @param object $entity
     * @param array['refProperty' => \ReflectionProperty, 'indexableConfig' => IndexableField] $fieldsContexts
     * @param bool $needsToComputeChangeset
     *
     * @throws \Odandb\DoctrineCiphersweetEncryptionBundle\Exception\UndefinedGeneratorException
     * @throws \ReflectionException
     */
    public function handleIndexableFieldsForEntity(object $entity, array $fieldsContexts, bool $needsToComputeChangeset = false): void
    {
        $searchIndexes = $this->generateIndexableValuesForEntity($entity, $fieldsContexts);

        /**
         * @var \ReflectionProperty $refProperty
         * @var EncryptedField $annotationConfig
         * @var IndexableField $indexableAnnotationConfig
         */
        foreach ($fieldsContexts as ['refProperty' => $refProperty, 'indexableConfig' => $indexableAnnotationConfig]) {
            if (!isset($searchIndexes[$refProperty->getName()])) {
                continue;
            }

            $indexesToEncrypt = $searchIndexes[$refProperty->getName()];

            $indexes = $this->indexesGenerator->generateBlindIndexesFromPossibleValues(get_class($entity), $refProperty->getName(), $indexesToEncrypt);

            // On crée les instances d'objet filtre et on les associe à l'entité parente
            $indexEntities = [];
            $indexEntityClass = $indexableAnnotationConfig->indexesEntityClass;

            $refClass = new \ReflectionClass($indexEntityClass);
            $classMetadata = $this->em->getClassMetadata($refClass->getName());
            foreach ($indexes as $index) {
                $indexEntity = $refClass->newInstance();
                if ($indexEntity instanceof IndexedEntityInterface) {
                    $indexEntity->setIndexBi($index);
                    $indexEntity->setFieldname($refProperty->getName());
                    $indexEntity->setTargetEntity($entity);
                    $indexEntities [] = $indexEntity;

                    $this->em->persist($indexEntity);
                    if ($needsToComputeChangeset) {
                        $this->em->getUnitOfWork()->computeChangeSet($classMetadata, $indexEntity);
                    }
                }
            }
            $setter = 'set' . $refClass->getShortName();
            $entity->$setter($indexEntities);
        }
    }
}
