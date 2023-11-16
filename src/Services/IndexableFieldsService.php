<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Services;

use Odandb\DoctrineCiphersweetEncryptionBundle\Configuration\IndexableField;
use Odandb\DoctrineCiphersweetEncryptionBundle\Entity\IndexedEntityInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Exception\MissingPropertyFromReflectionException;
use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManagerInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Exception\UndefinedGeneratorException;

class IndexableFieldsService
{
    public const INDEXABLE_ANN_NAME = IndexableField::class;

    /** @deprecated */
    private ?Reader $annReader;
    private EntityManagerInterface $em;
    private IndexesGenerator $indexesGenerator;

    public function __construct(?Reader $annReader, EntityManagerInterface $em, IndexesGenerator $generator)
    {
        $this->annReader = $annReader;
        $this->em = $em;
        $this->indexesGenerator = $generator;
    }

    /**
     * Chunks all data ID of the entity
     */
    public function getChunksForMultiThread(string $className, int $chuncksLength): array
    {
        $repo = $this->em->getRepository($className);
        $result = $repo->createQueryBuilder('c')
            ->select('c.id')
            ->getQuery()
            ->getArrayResult();

        return array_chunk(array_column($result, 'id'), $chuncksLength);
    }

    /**
     * @param null|array<int, string> $fieldNames
     *
     * @return array<int, array{refProperty: \ReflectionProperty, indexableConfig: IndexableField}>
     *
     * @throws MissingPropertyFromReflectionException
     */
    public function buildContext(string $className, ?array $fieldNames): array
    {
        $contexts = [];

        $classMetadata = $this->em->getClassMetadata($className);

        if (empty($fieldNames)) {
            $fieldNames = array_map(
                static function (\ReflectionProperty $refProperty): string {return $refProperty->name;},
                $classMetadata->getReflectionProperties()
            );
        }

        foreach ($fieldNames as $fieldname) {
            $refProperty = $classMetadata->getReflectionProperty($fieldname);
            if ($refProperty === null) {
                throw new MissingPropertyFromReflectionException(sprintf("No refProperty found for fieldname %s", $fieldname));
            }

            $indexableAnnotationConfig = null;
            if (PHP_VERSION_ID >= 80000 && null !== $refAttribute = $refProperty->getAttributes(self::INDEXABLE_ANN_NAME)[0] ?? null) {
                $indexableAnnotationConfig = $refAttribute->newInstance();
            }

            if (null === $indexableAnnotationConfig && null !== $this->annReader) {
                $indexableAnnotationConfig = $this->annReader->getPropertyAnnotation($refProperty, self::INDEXABLE_ANN_NAME);
                if (PHP_VERSION_ID >= 80000) {
                    trigger_deprecation(
                        'odandb/doctrine-ciphersweet-encryption-bundle',
                        '0.10.5',
                        'The support of annotation is deprecated and will be remove in doctrine-ciphersweet-encryption-bundle 1.0'
                    );
                }
            }

            if ($indexableAnnotationConfig instanceof IndexableField) {
                $contexts []= ['refProperty' => $refProperty, 'indexableConfig' => $indexableAnnotationConfig];
            }
        }

        return $contexts;
    }

    /**
     * Remove all (or by ids) the search possibilities of an entity field
     *
     * @param array<int, array{refProperty: \ReflectionProperty, indexableConfig: IndexableField}> $fieldsContexts
     * @param null|array<int, string> $ids
     */
    public function purgeFiltersForContextAndIds(array $fieldsContexts, ?array $ids): void
    {
        foreach($fieldsContexts as ['refProperty' => $refProperty, 'indexableConfig' => $indexableAnnotationConfig]) {
            $qb = $this->em->createQueryBuilder()
                ->delete()
                ->from($indexableAnnotationConfig->indexesEntityClass, 'f')
                ->where('f.fieldname=:fieldname')
                ->setParameter('fieldname', $refProperty->name)
            ;

            if (!empty($ids)) {
                $qb->andWhere('f.targetEntity IN (:ids)')
                    ->setParameter('ids', $ids);
            }

            $qb->getQuery()->execute();
        }
    }

    /**
     * Generate and save all (or by ids) the search possibilities of an entity
     *
     * @param null|array<int, string> $ids
     * @param array<int, array{refProperty: \ReflectionProperty, indexableConfig: IndexableField}> $fieldsContexts
     */
    public function handleFilterableFieldsForChunck(string $className, ?array $ids, array $fieldsContexts, bool $needsToComputeChangeset = false): void
    {
        $chunck = $this->em->getRepository($className)->findBy(!empty($ids) ? ['id' => $ids] : []);
        foreach ($chunck as $entity) {
            $this->handleIndexableFieldsForEntity($entity, $fieldsContexts, $needsToComputeChangeset);
            $this->em->flush();
        }
    }

    /**
     * Generate and save the search possibilities of an entity field
     *
     * @param array{refProperty: \ReflectionProperty, indexableConfig: IndexableField} $fieldsContexts
     *
     * @throws UndefinedGeneratorException|\ReflectionException
     */
    public function handleIndexableFieldsForEntity(object $entity, array $fieldsContexts, bool $needsToComputeChangeset = false): void
    {
        $className = get_class($entity);
        $searchIndexes = $this->generateIndexableValuesForEntity($entity, $fieldsContexts);

        foreach ($fieldsContexts as ['refProperty' => $refProperty, 'indexableConfig' => $indexableAnnotationConfig]) {
            if (!isset($searchIndexes[$refProperty->getName()])) {
                continue;
            }

            $indexesToEncrypt = $searchIndexes[$refProperty->getName()];

            $indexes = $this->indexesGenerator->generateBlindIndexesFromPossibleValues($className, $refProperty->getName(), $indexesToEncrypt, $indexableAnnotationConfig->fastIndexing);

            // We create the filter object instances and associate them to the parent entity
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
        }
    }

    /**
     * Generate the search possibilities of an entity field
     *
     * @param array{refProperty: \ReflectionProperty, indexableConfig: IndexableField} $fieldsContexts
     *
     * @return array<string, array<int, string>>
     *
     * @throws UndefinedGeneratorException
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
            $valueCleanerMethod = $indexableAnnotationConfig->valuePreprocessMethod;
            if ($valueCleanerMethod !== null && method_exists($entity, $valueCleanerMethod)) {
                $cleanValue = $entity->$valueCleanerMethod($value);
            }

            // We call the filter index generation service which will create the collection of possible patterns
            // according to the method(s) specified in the annotation
            // Then retrieve each associated "blind_index" to save in database
            $indexesMethods = $indexableAnnotationConfig->indexesGenerationMethods;

            $indexesToEncrypt = $this->indexesGenerator->generateAndEncryptFilters($cleanValue, $indexesMethods);
            $indexesToEncrypt[] = $value;
            $indexesToEncrypt = array_unique($indexesToEncrypt);

            $searchIndexes[$refProperty->getName()] = $indexesToEncrypt;
        }

        return $searchIndexes;
    }
}
