<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Services;

use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type as PropertyInfoType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

/**
 * @internal
 */
class PropertyHydratorService
{
    private object $typeExtractor;
    private PropertyAccessorInterface $propertyAccessor;
    private bool $useTypeInfo;

    public function __construct(object $typeExtractor, PropertyAccessorInterface $propertyAccessor)
    {
        $this->typeExtractor = $typeExtractor;
        $this->propertyAccessor = $propertyAccessor;
        $this->useTypeInfo = !($typeExtractor instanceof PropertyInfoExtractorInterface);
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

        if ($this->useTypeInfo) {
            $this->setValueWithTypeInfo($entity, $value, $propertyName);
        } else {
            $this->setValueWithPropertyInfo($entity, $value, $propertyName);
        }

        $this->propertyAccessor->setValue($entity, $propertyName, $value);
    }

    private function setValueWithTypeInfo(object $entity, string &$value, string $propertyName): void
    {
        /** @var TypeResolverInterface $typeResolver */
        $typeResolver = $this->typeExtractor;
        $type = $typeResolver->resolve(new \ReflectionProperty(get_class($entity), $propertyName));

        if ($type instanceof BuiltinType && !$type->isIdentifiedBy(TypeIdentifier::STRING)) {
            settype($value, $type->getTypeIdentifier()->value);
        }
    }

    private function setValueWithPropertyInfo(object $entity, string &$value, string $propertyName): void
    {
        /** @var PropertyInfoExtractorInterface $propertyInfoExtractor */
        $propertyInfoExtractor = $this->typeExtractor;
        $types = $propertyInfoExtractor->getTypes(get_class($entity), $propertyName);

        if ($types === null || count($types) === 0) {
            return;
        }

        $propertyInfoType = $types[0];
        $targetType = $propertyInfoType->getBuiltinType();

        if ($targetType !== PropertyInfoType::BUILTIN_TYPE_STRING) {
            settype($value, $targetType);
        }
    }
}
