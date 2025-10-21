<?php

declare(strict_types=1);


namespace Odandb\DoctrineCiphersweetEncryptionBundle\Services;


use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\Mapping\ClassMetadata;
use Odandb\DoctrineCiphersweetEncryptionBundle\Configuration\EncryptedField;

class EncryptedFieldsService
{
    /** @deprecated */
    private ?Reader $annReader;

    public function __construct(?Reader $annReader)
    {
        $this->annReader = $annReader;
    }

    /**
     * @param ClassMetadata $meta
     *
     * @return \ReflectionProperty[]
     */
    public function getEncryptedFields(ClassMetadata $meta): array
    {
        $encryptedFields = [];

        // ORM 3.4+ uses getPropertyAccessors(), older versions use getReflectionProperties()
        $properties = method_exists($meta, 'getPropertyAccessors')
            ? $meta->getPropertyAccessors()
            : $meta->getReflectionProperties();

        foreach ($properties as $property) {
            // ORM 3.4+ returns PropertyAccessor objects, older versions return ReflectionProperty directly
            $refProperty = method_exists($property, 'getUnderlyingReflector')
                ? $property->getUnderlyingReflector()
                : $property;

            if (PHP_VERSION_ID >= 80000 && isset($refProperty->getAttributes(EncryptedField::class)[0])) {
                $refProperty->setAccessible(true);
                $encryptedFields[] = $refProperty;

                continue;
            }

            /** @var \ReflectionProperty $refProperty */
            if (null !== $this->annReader && $this->annReader->getPropertyAnnotation($refProperty, EncryptedField::class)) {
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

        return $encryptedFields;
    }
}
