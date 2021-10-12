<?php

declare(strict_types=1);


namespace Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors;

use ParagonIE\CipherSweet\CipherSweet;

interface EncryptorInterface
{
    public const DEFAULT_FILTER_BITS = 32;

    public function __construct(CipherSweet $engine);

    public function prepareForStorage(object $entity, string $fieldName, string $string, bool $index = true, int $filterBits = self::DEFAULT_FILTER_BITS): array;

    public function decrypt(string $entity_classname, string $fieldName, string $string, int $filterBits = self::DEFAULT_FILTER_BITS): string;

    public function getBlindIndex($entityName, $fieldName, string $value, int $filterBits = self::DEFAULT_FILTER_BITS): string;

    public function getPrefix(): string;
}
