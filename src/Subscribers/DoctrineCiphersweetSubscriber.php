<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Subscribers;

use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\Persistence\ObjectManager;
use Doctrine\ORM\UnitOfWork;
use Odandb\DoctrineCiphersweetEncryptionBundle\Configuration\EncryptedField;
use Odandb\DoctrineCiphersweetEncryptionBundle\Configuration\IndexableField;
use Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors\EncryptorInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Services\EncryptedFieldsService;
use Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexableFieldsService;
use Odandb\DoctrineCiphersweetEncryptionBundle\Services\PropertyHydratorService;
use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Service\ResetInterface;

class DoctrineCiphersweetSubscriber implements EventSubscriberInterface, ResetInterface
{
    public const ENCRYPTED_ANN_NAME = EncryptedField::class;
    public const INDEXABLE_ANN_NAME = IndexableField::class;

    /** @deprecated */
    private Reader $annReader;
    private EncryptorInterface $encryptor;
    private IndexableFieldsService $indexableFieldsService;
    private PropertyHydratorService $propertyHydratorService;

    private EncryptedFieldsService $encryptedFieldsService;

    /**
     * Caches the original encrypt value of an entity field
     *
     * @var array<int, array<string, mixed>>
     */
    private array $_originalValues = [];

    /**
     * Cache the entities SPL ID that have already been decrypted
     *
     * @var array<int, bool>
     */
    private array $decodedRegistry = [];

    /**
     * Caches information on an entity's encrypted fields in an array keyed on
     * the entity's class name. The value will be a list of Reflected fields that are encrypted.
     *
     * @var array<string, array<int, \ReflectionProperty>
     */
    private array $encryptedFieldCache = [];

    /**
     * Before flushing the objects out to the database, we modify their password value to the
     * encrypted value. Since we want the password to remain decrypted on the entity after a flush,
     * we have to write the decrypted value back to the entity.
     *
     * @var array<int, array{entity: object, fields: array<string, array{field: \ReflectionProperty, value: mixed}>}>
     */
    private array $postFlushDecryptQueue = [];

    /**
     * Entity that remains to be encrypted (converting an existing field to encryption)
     *
     * @var array<int, object>
     */
    private array $entitiesToEncrypt = [];

    public function __construct(
        Reader                  $annReader,
        EncryptedFieldsService  $encryptedFieldsService,
        EncryptorInterface      $encryptorClass,
        IndexableFieldsService  $indexableFieldsService,
        PropertyHydratorService $propertyHydratorService
    )
    {
        $this->annReader = $annReader;
        $this->encryptedFieldsService = $encryptedFieldsService;
        $this->encryptor = $encryptorClass;
        $this->indexableFieldsService = $indexableFieldsService;
        $this->propertyHydratorService = $propertyHydratorService;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::postLoad,
            Events::onFlush,
            Events::postFlush,
            Events::onClear,
        ];
    }

    public function reset(): void
    {
        $this->_originalValues = [];
        $this->decodedRegistry = [];
        $this->encryptedFieldCache = [];
        $this->postFlushDecryptQueue = [];
        $this->entitiesToEncrypt = [];
    }

    /**
     * Listen a postLoad lifecycle event. Checking and decrypt entities which have `EncryptedField` annotations/attributes.
     */
    public function postLoad(PostLoadEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($this->hasInDecodedRegistry($entity)) {
            return;
        }

        if ($this->processFields($entity, $args->getObjectManager(), false)) {
            $this->addToDecodedRegistry($entity);
        }
    }

    /**
     * Encrypt the password before it is written to the database.
     */
    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $unitOfWork = $em->getUnitOfWork();

        $this->postFlushDecryptQueue = [];
        $this->entitiesToEncrypt = [];

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            $this->entityOnFlush($entity, $em);
            $unitOfWork->recomputeSingleEntityChangeSet($em->getClassMetadata(\get_class($entity)), $entity);
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            $this->entityOnFlush($entity, $em);
            $unitOfWork->recomputeSingleEntityChangeSet($em->getClassMetadata(\get_class($entity)), $entity);
            unset($this->entitiesToEncrypt[spl_object_id($entity)]);
        }

        foreach ($this->entitiesToEncrypt as $entity) {
            $this->entityOnFlush($entity, $em);
            $unitOfWork->recomputeSingleEntityChangeSet($em->getClassMetadata(\get_class($entity)), $entity);
        }

        // We flush the array of entities to encrypt after the loop to avoid memory leaks
        $this->entitiesToEncrypt = [];
    }

    /**
     * After we have persisted the entities, we want to have the
     * decrypted information available once more.
     */
    public function postFlush(PostFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();

        foreach ($this->postFlushDecryptQueue as $pair) {
            $fieldPairs = $pair['fields'];
            $entity = $pair['entity'];
            $oid = spl_object_id($entity);

            foreach ($fieldPairs as $fieldPair) {
                /** @var \ReflectionProperty $field */
                $field = $fieldPair['field'];
                $field->setValue($entity, $fieldPair['value']);
                $unitOfWork->setOriginalEntityProperty($oid, $field->getName(), $fieldPair['value']);
            }

            $this->addToDecodedRegistry($entity);
        }
        $this->postFlushDecryptQueue = [];
    }

    public function onClear(OnClearEventArgs $args): void
    {
        unset($this->_originalValues, $this->decodedRegistry, $this->encryptedFieldCache, $this->postFlushDecryptQueue, $this->entitiesToEncrypt);

        $this->_originalValues = [];
        $this->decodedRegistry = [];
        $this->encryptedFieldCache = [];
        $this->postFlushDecryptQueue = [];
        $this->entitiesToEncrypt = [];
    }

    /**
     * Processes the entity for an onFlush event.
     *
     * @param object $entity
     * @param EntityManagerInterface $em
     */
    private function entityOnFlush(object $entity, EntityManagerInterface $em): void
    {
        $objId = spl_object_id($entity);

        $fields = [];
        $ecnryptedFields = $this->getEncryptedFields($entity, $em);

        // If no encryptedFields detected we early return as we don't need to process anything
        if ($ecnryptedFields === []) {
            return;
        }

        foreach ($ecnryptedFields as $field) {
            $fields[$field->getName()] = [
                'field' => $field,
                'value' => $field->getValue($entity),
            ];
        }
        $this->postFlushDecryptQueue[$objId] = [
            'entity' => $entity,
            'fields' => $fields,
        ];

        $this->processFields($entity, $em);
    }

    /**
     * @return \ReflectionProperty[]
     */
    private function getEncryptedFields(object $entity, ObjectManager $em): array
    {
        $className = \get_class($entity);
        if (isset($this->encryptedFieldCache[$className])) {
            return $this->encryptedFieldCache[$className];
        }

        $meta = $em->getClassMetadata($className);

        $encryptedFields = $this->encryptedFieldsService->getEncryptedFields($meta);

        return $this->encryptedFieldCache[$className] = $encryptedFields;
    }

    /**
     * Process (encrypt/decrypt) entities fields.
     *
     * Upon encryption operation, if the entity is not new, we check if there are changes in the entity.
     * If no changes, we early return.
     * Make sure you call first $unitOfWork->computeChangeSet or $unitOfWork->recomputeSingleEntityChangeSet
     * if you think your entity should be updated and has not been handled by entity manager.
     */
    public function processFields(object $entity, ObjectManager $em, bool $isEncryptOperation = true, bool $force = false): bool
    {
        $properties = $this->getEncryptedFields($entity, $em);
        $unitOfWork = $em->getUnitOfWork();

        // If there is no encrypted fields nor changes in given entity upon encryption operation and the entity is not new, we early return
        // In case of new entity, there is no need to check for changes as they may not have been persisted nor computed yet
        if (
            $properties === []
            || (
                $isEncryptOperation
                && $unitOfWork->getEntityState($entity) !== UnitOfWork::STATE_NEW
                && $unitOfWork->getEntityChangeSet($entity) === []
            )
        ) {
            return false;
        }

        $oid = spl_object_id($entity);

        $entityClassName = $em->getClassMetadata(get_class($entity))->getName();

        foreach ($properties as $refProperty) {
            $value = $refProperty->getValue($entity);
            if ($value === null) {
                continue;
            }

            $context = $this->buildContext($entityClassName, $refProperty);

            if ($isEncryptOperation) {
                $value = $this->handleEncryptOperation($entity, $oid, $value, $refProperty, $context, $force);
            } else {
                if (!$this->isValueEncrypted($value)) {
                    $this->entitiesToEncrypt[$oid] = $entity;
                }
                $value = $this->handleDecryptOperation($oid, $value, $refProperty, $context);
            }

            if (null !== $value) {
                $refProperty->setValue($entity, $value);
                if (!$this->isValueEncrypted($value)) {
                    $this->propertyHydratorService->setValueToMappedField($entity, $value, $context['annotationConfig']['mappedTypedProperty']);
                }
            }

            if (!$isEncryptOperation && !\defined('_DONOTENCRYPT')) {
                //we don't want the object to be dirty immediately after reading
                $unitOfWork->setOriginalEntityProperty($oid, $refProperty->getName(), $value);
            }
        }

        return !empty($properties);
    }

    /**
     * @return array{
     *     annotationConfig: array{blindIndex: bool, filterBits: int, mappedTypedProperty: null|string},
     *     indexableAnnotation: null|IndexableField,
     *     entityClassName: string
     * }
     */
    private function buildContext(string $entityClassName, \ReflectionProperty $refProperty): array
    {
        $annotationConfig = null;
        $indexableAnnotationConfig = null;
        if (PHP_VERSION_ID >= 80000) {
            $refEncryptedAttributes = $refProperty->getAttributes(self::ENCRYPTED_ANN_NAME);
            $refIndexableAttributes = $refProperty->getAttributes(self::INDEXABLE_ANN_NAME);

            if (isset($refEncryptedAttributes[0])) {
                $annotationConfig = $refEncryptedAttributes[0]->newInstance();
            }
            if (isset($refIndexableAttributes[0])) {
                $indexableAnnotationConfig = $refIndexableAttributes[0]->newInstance();
            }
        }

        if (null === $annotationConfig && null === $indexableAnnotationConfig) {
            $annotationConfig = $this->annReader->getPropertyAnnotation($refProperty, self::ENCRYPTED_ANN_NAME);
            $indexableAnnotationConfig = $this->annReader->getPropertyAnnotation($refProperty, self::INDEXABLE_ANN_NAME);

            if (PHP_VERSION_ID >= 80000) {
                trigger_deprecation(
                    'odandb/doctrine-ciphersweet-encryption-bundle',
                    '0.10.5',
                    'The support of annotation is deprecated and will be remove in doctrine-ciphersweet-encryption-bundle 1.0'
                );
            }
        }

        $storeBlindIndex = true;
        $filterBits = EncryptorInterface::DEFAULT_FILTER_BITS;
        $mappedTypedProperty = null;

        if ($annotationConfig instanceof EncryptedField) {
            $storeBlindIndex = $annotationConfig->indexable;
            $filterBits = $annotationConfig->filterBits;
            $mappedTypedProperty = $annotationConfig->mappedTypedProperty;
        }

        return [
            'annotationConfig' => [
                'blindIndex' => $storeBlindIndex,
                'filterBits' => $filterBits,
                'mappedTypedProperty' => $mappedTypedProperty,
            ],
            'indexableAnnotation' => $indexableAnnotationConfig,
            'entityClassName' => $entityClassName,
        ];
    }

    /**
     * @param mixed $value
     * @param array{
     *     annotationConfig: array{blindIndex: bool, filterBits: int, mappedTypedProperty: null|string},
     *     indexableAnnotation: null|IndexableField,
     *     entityClassName: string
     * } $context
     */
    private function handleEncryptOperation(object $entity, int $oid, $value, \ReflectionProperty $refProperty, array $context, bool $force): ?string
    {
        [
            'annotationConfig' => [
                'blindIndex' => $storeBlindIndex,
                'filterBits' => $filterBits,
                'mappedTypedProperty' => $mappedTypedProperty,
            ],
            'indexableAnnotation' => $indexableAnnotationConfig,
            'entityClassName' => $entityClassName,
        ] = $context;

        $value = $this->propertyHydratorService->getMappedFieldValueAsString($entity, $mappedTypedProperty, $value);
        if ('' === $value) {
            return null;
        }

        // Force encryption
        if ($force) {
            $value = $this->storeValue($entity, $refProperty, $value, $storeBlindIndex, $filterBits, $indexableAnnotationConfig->fastIndexing ?? EncryptorInterface::DEFAULT_FAST_INDEXING);
            $this->storeIndexes($entity, $refProperty, $indexableAnnotationConfig);

            return $value;
        }

        // Get the original value
        $oldValue = null;
        if (isset($this->_originalValues[$oid][$refProperty->getName()])) {
            $oldValue = $this->_originalValues[$oid][$refProperty->getName()];
            if ($this->isValueEncrypted($oldValue)) {
                $oldValue = $this->encryptor->decrypt($entityClassName, $refProperty->getName(), $oldValue, $filterBits, $indexableAnnotationConfig->fastIndexing ?? EncryptorInterface::DEFAULT_FAST_INDEXING);
            }
        }

        if (!$this->isValueEncrypted($oldValue) || $oldValue !== $value) {
            $value = $this->storeValue($entity, $refProperty, $value, $storeBlindIndex, $filterBits, $indexableAnnotationConfig->fastIndexing ?? EncryptorInterface::DEFAULT_FAST_INDEXING);
            $this->storeIndexes($entity, $refProperty, $indexableAnnotationConfig);
        }

        return $value;
    }

    /**
     * @param mixed $value
     */
    private function handleDecryptOperation(int $oid, $value, \ReflectionProperty $refProperty, array $context): string
    {
        /**
         * @var IndexableField $indexableAnnotationConfig
         */
        [
            'annotationConfig' => [
                'filterBits' => $filterBits,
            ],
            'indexableAnnotation' => $indexableAnnotationConfig,
            'entityClassName' => $entityClassName,
        ] = $context;

        $this->_originalValues[$oid][$refProperty->getName()] = $value;

        if ($this->isValueEncrypted($value)) {
            $value = $this->encryptor->decrypt($entityClassName, $refProperty->getName(), $value, $filterBits, $indexableAnnotationConfig->fastIndexing ?? EncryptorInterface::DEFAULT_FAST_INDEXING);
        }

        return $value;
    }

    private function isValueEncrypted(?string $value): bool
    {
        return $this->encryptor->isValueEncrypted($value);
    }

    private function storeValue(object $entity, \ReflectionProperty $refProperty, string $value, bool $storeBlindIndex, int $filterBits, bool $fastIndexing = true)
    {
        [$value, $indexes] = $this->encryptor->prepareForStorage($entity, $refProperty->getName(), $value, $storeBlindIndex, $filterBits, $fastIndexing);

        if ($storeBlindIndex === true) {
            foreach ($indexes as $key => $blindIndexValue) {
                $setter = 'set' . str_replace('_', '', ucwords($key, '_'));
                $entity->$setter($blindIndexValue);
            }
        }

        return $value;
    }

    /**
     * Generate and save indexable value
     */
    private function storeIndexes(object $entity, \ReflectionProperty $refProperty, ?IndexableField $indexableAnnotationConfig): void
    {
        if ($indexableAnnotationConfig === null) {
            return;
        }

        if (!$indexableAnnotationConfig->autoRefresh) {
            return;
        }

        $this->indexableFieldsService->handleIndexableFieldsForEntity($entity, [['refProperty' => $refProperty, 'indexableConfig' => $indexableAnnotationConfig]], true);
    }

    /**
     * Adds entity to decoded registry.
     *
     * @param object $entity Some doctrine entity
     */
    private function addToDecodedRegistry(object $entity): void
    {
        $this->decodedRegistry[spl_object_id($entity)] = true;
    }

    /**
     * Check if we have entity in decoded registry.
     *
     * @param object $entity Some doctrine entity
     */
    private function hasInDecodedRegistry(object $entity): bool
    {
        return isset($this->decodedRegistry[spl_object_id($entity)]);
    }
}
