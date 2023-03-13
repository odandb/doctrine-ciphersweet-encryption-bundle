<?php

declare(strict_types=1);


namespace Odandb\DoctrineCiphersweetEncryptionBundle\Subscribers;

use Odandb\DoctrineCiphersweetEncryptionBundle\Configuration\EncryptedField;
use Odandb\DoctrineCiphersweetEncryptionBundle\Configuration\IndexableField;
use Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors\EncryptorInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexableFieldsService;
use Odandb\DoctrineCiphersweetEncryptionBundle\Services\PropertyHydratorService;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

/**
 *
 */
class DoctrineCiphersweetSubscriber implements EventSubscriber
{
    public const ENCRYPTED_ANN_NAME = EncryptedField::class;
    public const INDEXABLE_ANN_NAME = IndexableField::class;

    private EncryptorInterface $encryptor;
    private Reader $annReader;

    public array $_originalValues = [];

    private array $decodedRegistry = [];
    /**
     * Caches information on an entity's encrypted fields in an array keyed on
     * the entity's class name. The value will be a list of Reflected fields that are encrypted.
     */
    private array $encryptedFieldCache = [];

    /**
     * Before flushing the objects out to the database, we modify their password value to the
     * encrypted value. Since we want the password to remain decrypted on the entity after a flush,
     * we have to write the decrypted value back to the entity.
     */
    private array $postFlushDecryptQueue = [];

    private array $entitiesToEncrypt = [];

    private IndexableFieldsService $indexableFieldsService;

    private PropertyHydratorService $propertyHydratorService;

    /**
     * Initialization of subscriber.
     */
    public function __construct(
        Reader                  $annReader,
        EncryptorInterface      $encryptorClass,
        IndexableFieldsService  $indexableFieldsService,
        PropertyHydratorService $propertyHydratorService
    )
    {
        $this->annReader = $annReader;
        $this->encryptor = $encryptorClass;
        $this->indexableFieldsService = $indexableFieldsService;
        $this->propertyHydratorService = $propertyHydratorService;
    }

    /**
     * Encrypt the password before it is written to the database.
     */
    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getEntityManager();
        $unitOfWork = $em->getUnitOfWork();

        $this->postFlushDecryptQueue = [];

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
    }

    public function onClear(OnClearEventArgs $args): void
    {
        unset($this->_originalValues, $this->decodedRegistry, $this->encryptedFieldCache, $this->postFlushDecryptQueue);

        $this->_originalValues = [];
        $this->decodedRegistry = [];
        $this->encryptedFieldCache = [];
        $this->postFlushDecryptQueue = [];
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

        foreach ($this->getEncryptedFields($entity, $em) as $field) {
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
     * @param object $entity
     * @param EntityManagerInterface $em
     *
     * @return \ReflectionProperty[]
     */
    private function getEncryptedFields(object $entity, EntityManagerInterface $em): array
    {
        $className = \get_class($entity);

        if (isset($this->encryptedFieldCache[$className])) {
            return $this->encryptedFieldCache[$className];
        }

        $meta = $em->getClassMetadata($className);
        $encryptedFields = [];

        foreach ($meta->getReflectionProperties() as $refProperty) {
            if (PHP_VERSION_ID >= 80000 && isset($refProperty->getAttributes(self::ENCRYPTED_ANN_NAME)[0])) {
                $refProperty->setAccessible(true);
                $encryptedFields[] = $refProperty;

                continue;
            }

            /** @var \ReflectionProperty $refProperty */
            if ($this->annReader->getPropertyAnnotation($refProperty, self::ENCRYPTED_ANN_NAME)) {
                $refProperty->setAccessible(true);
                $encryptedFields[] = $refProperty;

                if (PHP_VERSION_ID >= 80000) {
                    trigger_deprecation(
                        'odandb/doctrine-ciphersweet-encryption-bundle',
                        '0.10.5',
                        'The support of annotation is deprecated and will be remove in doctrine-ciphersweet-encryption-bundle 1.0'
                    );
                }
            }
        }

        $this->encryptedFieldCache[$className] = $encryptedFields;

        return $encryptedFields;
    }

    /**
     * Process (encrypt/decrypt) entities fields.
     */
    public function processFields(object $entity, EntityManagerInterface $em, $isEncryptOperation = true, $force = null): bool
    {
        $properties = $this->getEncryptedFields($entity, $em);
        $unitOfWork = $em->getUnitOfWork();

        $oid = spl_object_id($entity);

        $entityClassName = $em->getClassMetadata(get_class($entity))->getName();

        foreach ($properties as $refProperty) {
            $value = $refProperty->getValue($entity) ?? '';

            if ($value === null) {
                continue;
            }

            $context = $this->buildContext($entityClassName, $refProperty);

            if ($isEncryptOperation) {
                $value = $this->handleEncryptOperation($entity, $oid, $value, $refProperty, $context, $force);
            } else {
                $oldValue = $value;
                if (!$this->isValueEncrypted($oldValue)) {
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
                $unitOfWork->setOriginalEntityProperty(spl_object_id($entity), $refProperty->getName(), $value);
            }
        }

        return !empty($properties);
    }

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
     * @param object $entity
     * @param int $oid
     * @param mixed $value
     * @param \ReflectionProperty $refProperty
     * @param array $context
     * @param string|null $force
     * @return mixed|string|null
     *
     * @throws \Odandb\DoctrineCiphersweetEncryptionBundle\Exception\UndefinedGeneratorException
     * @throws \ReflectionException
     */
    private function handleEncryptOperation(object $entity, int $oid, $value, \ReflectionProperty $refProperty, array $context, ?string $force = null)
    {
        /**
         * @var null|IndexableField $indexableAnnotationConfig
         */
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

        if ('encrypt' === $force) {
            $originalValue = $value;
            $value = $this->storeValue($entity, $refProperty, $value, $storeBlindIndex, $filterBits, $indexableAnnotationConfig->fastIndexing ?? EncryptorInterface::DEFAULT_FAST_INDEXING);
            $this->storeIndexes($entity, $refProperty, $indexableAnnotationConfig, $originalValue);
        } else {
            if (isset($this->_originalValues[$oid][$refProperty->getName()])) {
                $oldValue = $this->_originalValues[$oid][$refProperty->getName()];

                if ($this->isValueEncrypted($oldValue)) {
                    $oldValue = $this->encryptor->decrypt($entityClassName, $refProperty->getName(), $oldValue, $filterBits, $indexableAnnotationConfig->fastIndexing ?? EncryptorInterface::DEFAULT_FAST_INDEXING);
                }
            } else {
                $oldValue = null;
            }

            if (($this->isValueEncrypted($oldValue) && $oldValue === $value) || (null === $oldValue && null === $value)) {
                $value = $oldValue;
            } else {
                $originalValue = $value;
                $value = $this->storeValue($entity, $refProperty, $value, $storeBlindIndex, $filterBits, $indexableAnnotationConfig->fastIndexing ?? EncryptorInterface::DEFAULT_FAST_INDEXING);
                $this->storeIndexes($entity, $refProperty, $indexableAnnotationConfig, $originalValue);
            }
        }

        return $value;
    }

    /**
     * @param int $oid
     * @param mixed $value
     * @param \ReflectionProperty $refProperty
     * @param array $context
     * @return string
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

    /**
     * @param null|string $value
     * @return bool
     */
    private function isValueEncrypted(?string $value): bool
    {
        return $value !== null && strpos($value, $this->encryptor->getPrefix()) === 0;
    }

    /**
     * @param object $entity
     * @param \ReflectionProperty $refProperty
     * @param $value
     * @param bool $storeBlindIndex
     * @param int $filterBits
     * @param bool $fastIndexing
     * @return mixed
     */
    private function storeValue(object $entity, \ReflectionProperty $refProperty, $value, bool $storeBlindIndex, int $filterBits, bool $fastIndexing = true)
    {
        if ($value === '') {
            return '';
        }

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
     * @param object $entity
     * @param \ReflectionProperty $refProperty
     * @param IndexableField|null $indexableAnnotationConfig
     * @param mixed $value
     * @throws \Odandb\DoctrineCiphersweetEncryptionBundle\Exception\UndefinedGeneratorException
     * @throws \ReflectionException
     */
    private function storeIndexes(object $entity, \ReflectionProperty $refProperty, ?IndexableField $indexableAnnotationConfig, $value): void
    {
        if ($indexableAnnotationConfig === null) {
            return;
        }

        $autoRefresh = $indexableAnnotationConfig->autoRefresh;
        if ($autoRefresh === false) {
            return;
        }

        if (is_string($value) === false) {
            throw new \TypeError("Value is supposed to be of type string in order to build related indexes.");
        }

        $this->indexableFieldsService->handleIndexableFieldsForEntity($entity, ['refProperty' => $refProperty, 'indexableConfig' => $indexableAnnotationConfig], true);
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

    /**
     * Adds entity to decoded registry.
     *
     * @param object $entity Some doctrine entity
     */
    private function addToDecodedRegistry($entity): void
    {
        $this->decodedRegistry[spl_object_id($entity)] = true;
    }

    /**
     * Listen a postLoad lifecycle event. Checking and decrypt entities
     * which have @EncryptedField annotations.
     */
    public function postLoad(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$this->hasInDecodedRegistry($entity) && $this->processFields($entity, $args->getObjectManager(), false)) {
            $this->addToDecodedRegistry($entity);
        }
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
}
