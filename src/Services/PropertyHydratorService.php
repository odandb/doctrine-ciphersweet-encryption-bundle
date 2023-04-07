<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Services;

use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

class PropertyHydratorService
{
    private PropertyInfoExtractorInterface $propertyInfoExtractor;
    private PropertyAccessorInterface $propertyAccessor;

    public function __construct(PropertyInfoExtractorInterface $propertyInfoExtractor, PropertyAccessorInterface $propertyAccessor)
    {
        $this->propertyInfoExtractor = $propertyInfoExtractor;
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * @param mixed $value
     */
    public function getMappedFieldValueAsString(object $entity, ?string $propertyName, $value): string
    {
        if ($propertyName !== null) {
            $value = $this->propertyAccessor->getValue($entity, $propertyName);
        }

        return (string) $value;
    }

    public function setValueToMappedField(object $entity, string $value, ?string $propertyName): void
    {
        if ($propertyName === null) {
            return;
        }

        $propertyInfoType = $this->propertyInfoExtractor->getTypes(get_class($entity), $propertyName)[0];
        $targetType = $propertyInfoType->getBuiltinType();

        if ($targetType !== Type::BUILTIN_TYPE_STRING) {
            settype($value, $targetType);
        }

        $this->propertyAccessor->setValue($entity, $propertyName, $value);
    }
}
