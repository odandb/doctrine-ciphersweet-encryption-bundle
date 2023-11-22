<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors;

use ParagonIE\CipherSweet\CipherSweet;

interface EncryptorInterface
{
    public const DEFAULT_FILTER_BITS = 32;
    public const DEFAULT_FAST_INDEXING = true;

    public function __construct(CipherSweet $engine);

    /**
     * Encrypt a value and calculate this blind indices
     *
     * @return array{0:string, 1: array<string, string>}
     */
    public function prepareForStorage(#[\SensitiveParameter] object $entity, #[\SensitiveParameter] string $fieldName, #[\SensitiveParameter] string $string, bool $index = true, int $filterBits = self::DEFAULT_FILTER_BITS, bool $fastIndexing = self::DEFAULT_FAST_INDEXING): array;

    /**
     * Decrypt a value
     */
    public function decrypt(#[\SensitiveParameter] string $entityClassName, #[\SensitiveParameter] string $fieldName, #[\SensitiveParameter] string $string, int $filterBits = self::DEFAULT_FILTER_BITS, bool $fastIndexing = self::DEFAULT_FAST_INDEXING): string;

    /**
     * Get the blind index of the field
     */
    public function getBlindIndex(#[\SensitiveParameter] string $entityName, #[\SensitiveParameter] string $fieldName, #[\SensitiveParameter] string $value, int $filterBits = self::DEFAULT_FILTER_BITS, bool $fastIndexing = self::DEFAULT_FAST_INDEXING): string;

    /**
     * Get the prefix of the encryptor
     */
    public function getPrefix(): string;

    public function isValueEncrypted(#[\SensitiveParameter] ?string $value): bool;
}
