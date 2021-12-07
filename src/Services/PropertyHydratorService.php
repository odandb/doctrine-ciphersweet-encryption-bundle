<?php

declare(strict_types=1);


namespace Odandb\DoctrineCiphersweetEncryptionBundle\Services;


use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

class PropertyHydratorService
{
    private PropertyInfoExtractorInterface $propertyInfoExtractor;

    private PropertyAccessorInterface $propertyAccessor;

    public function __construct(PropertyInfoExtractorInterface $propertyInfoExtractor, PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->propertyInfoExtractor = $propertyInfoExtractor;
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
    }

    /**
     * @param object $entity
     * @param string|null $propertyName
     * @param mixed $value
     * @return string
     */
    public function getMappedFieldValueAsString(object $entity, ?string $propertyName, $value): string
    {
        if ($propertyName !== null) {
            $value = $this->propertyAccessor->getValue($entity, $propertyName);
        }

        return (string) $value;
    }

    /**
     * @param object $entity
     * @param string $value
     * @param string|null $propertyName
     */
    public function setValueToMappedField(object $entity, string $value, ?string $propertyName): void
    {
        if ($propertyName !== null) {
            $propertyInfoType = $this->propertyInfoExtractor->getTypes(get_class($entity), $propertyName)[0];
            $targetType = $propertyInfoType->getBuiltinType();

            if ($targetType !== Type::BUILTIN_TYPE_STRING) {
                settype($value, $targetType);
            }

            $this->propertyAccessor->setValue($entity, $propertyName, $value);
        }
    }
}
