<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Services;

use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

/**
 * @internal
 */
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
    public function getMappedFieldValueAsString(#[\SensitiveParameter] object $entity, #[\SensitiveParameter] ?string $propertyName, #[\SensitiveParameter] $value): string
    {
        if ($propertyName !== null) {
            $value = $this->propertyAccessor->getValue($entity, $propertyName);
        }

        return (string) $value;
    }

    public function setValueToMappedField(#[\SensitiveParameter] object $entity, #[\SensitiveParameter] string $value, #[\SensitiveParameter] ?string $propertyName): void
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
