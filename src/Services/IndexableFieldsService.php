<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Services;

use Odandb\DoctrineCiphersweetEncryptionBundle\Configuration\IndexableField;
use Odandb\DoctrineCiphersweetEncryptionBundle\Entity\IndexedEntityInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Exception\MissingPropertyFromReflectionException;
use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManagerInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Exception\UndefinedGeneratorException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class IndexableFieldsService
{
    public const INDEXABLE_ANN_NAME = IndexableField::class;

    /** @deprecated */
    private ?Reader $annReader;
    private EntityManagerInterface $em;
    private IndexesGenerator $indexesGenerator;
    private PropertyAccessorInterface $propertyAccessor;

    public function __construct(?Reader $annReader, EntityManagerInterface $em, IndexesGenerator $generator, PropertyAccessorInterface $propertyAccessor)
    {
        $this->annReader = $annReader;
        $this->em = $em;
        $this->indexesGenerator = $generator;
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * Chunks all data ID of the entity
     */
    public function getChunksForMultiThread(#[\SensitiveParameter] string $className, int $chuncksLength): array
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
    public function buildContext(#[\SensitiveParameter] string $className, #[\SensitiveParameter] ?array $fieldNames): array
    {
        $contexts = [];

        $classMetadata = $this->em->getClassMetadata($className);

        if (empty($fieldNames)) {
            // ORM 3.4+ uses getPropertyAccessors(), older versions use getReflectionProperties()
            if (method_exists($classMetadata, 'getPropertyAccessors')) {
                $fieldNames = array_map(
                    static function ($propertyAccessor): string {return $propertyAccessor->getUnderlyingReflector()->name;},
                    $classMetadata->getPropertyAccessors()
                );
            } else {
                $fieldNames = array_map(
                    static function (\ReflectionProperty $refProperty): string {return $refProperty->name;},
                    $classMetadata->getReflectionProperties()
                );
            }
        }

        foreach ($fieldNames as $fieldname) {
            // ORM 3.4+ uses getPropertyAccessor(), older versions use getReflectionProperty()
            if (method_exists($classMetadata, 'getPropertyAccessor')) {
                $propertyAccessor = $classMetadata->getPropertyAccessor($fieldname);
                if ($propertyAccessor === null) {
                    throw new MissingPropertyFromReflectionException(sprintf("No refProperty found for fieldname %s", $fieldname));
                }
                $refProperty = $propertyAccessor->getUnderlyingReflector();
            } else {
                $refProperty = $classMetadata->getReflectionProperty($fieldname);
                if ($refProperty === null) {
                    throw new MissingPropertyFromReflectionException(sprintf("No refProperty found for fieldname %s", $fieldname));
                }
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
    public function handleFilterableFieldsForChunck(#[\SensitiveParameter] string $className, ?array $ids, array $fieldsContexts, bool $runtimeMode = false): void
    {
        $chunck = $this->em->getRepository($className)->findBy(!empty($ids) ? ['id' => $ids] : []);
        foreach ($chunck as $entity) {
            $this->handleIndexableFieldsForEntity($entity, $fieldsContexts, $runtimeMode);
            $this->em->flush();
        }
    }

    /**
     * Generate and save the search possibilities of an entity field
     *
     * @param array<int, array{refProperty: \ReflectionProperty, indexableConfig: IndexableField}> $fieldsContexts
     *
     * @throws UndefinedGeneratorException|\ReflectionException
     */
    public function handleIndexableFieldsForEntity(#[\SensitiveParameter] object $entity, array $fieldsContexts, bool $runtimeMode = false): void
    {
        $className = get_class($entity);
        $searchIndexes = $this->generateIndexableValuesForEntity($entity, $fieldsContexts);

        foreach ($fieldsContexts as ['refProperty' => $refProperty, 'indexableConfig' => $indexableAnnotationConfig]) {
            if (!isset($searchIndexes[$refProperty->getName()])) {
                continue;
            }

            $indexesToEncrypt = $searchIndexes[$refProperty->getName()];

            $indexes = $this->indexesGenerator->generateBlindIndexesFromPossibleValues($className, $refProperty->getName(), $indexesToEncrypt, $indexableAnnotationConfig->fastIndexing);

            // We create the filter object instances and associate them to the parent entity if is needed
            $indexEntities = [];
            $indexEntityClass = $indexableAnnotationConfig->indexesEntityClass;

            // If we are in runtime with autoRefresh indexes, we need to compute the change set and set the inverse property to overwrite the existing one. With the orphanRemoval option, the old collection will be deleted.
            // In other cases, the old indexes remain and must be purged by you.
            $needToCompute = $runtimeMode && $indexableAnnotationConfig->autoRefresh;

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

                    if ($needToCompute) {
                        $this->em->getUnitOfWork()->computeChangeSet($classMetadata, $indexEntity);
                    }
                }
            }

            if ($needToCompute) {
                if ($this->propertyAccessor->isWritable($entity, $refClass->getShortName())) {
                    $this->propertyAccessor->setValue($entity, $refClass->getShortName(), $indexEntities);
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
    public function generateIndexableValuesForEntity(#[\SensitiveParameter] object $entity, array $fieldsContexts): array
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
