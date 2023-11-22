<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors;

use ParagonIE\CipherSweet\BlindIndex;
use ParagonIE\CipherSweet\CipherSweet;
use ParagonIE\CipherSweet\EncryptedField;
use ParagonIE\CipherSweet\Exception\BlindIndexNameCollisionException;
use ParagonIE\CipherSweet\Exception\BlindIndexNotFoundException;
use ParagonIE\CipherSweet\Exception\CipherSweetException;
use ParagonIE\CipherSweet\Exception\CryptoOperationException;
use Symfony\Contracts\Service\ResetInterface;

class CiphersweetEncryptor implements EncryptorInterface, ResetInterface
{
    private CipherSweet $engine;

    private array $cache;
    private array $biCache;

    public function __construct(CipherSweet $engine)
    {
        $this->engine = $engine;
        $this->cache = [];
        $this->biCache = [];
    }

    /**
     * {@inheritdoc}
     *
     * @throws CipherSweetException
     * @throws CryptoOperationException
     * @throws BlindIndexNotFoundException
     * @throws BlindIndexNameCollisionException
     * @throws \SodiumException
     */
    public function prepareForStorage(#[\SensitiveParameter] object $entity, #[\SensitiveParameter] string $fieldName, #[\SensitiveParameter] string $string, bool $index = true, int $filterBits = self::DEFAULT_FILTER_BITS, bool $fastIndexing = self::DEFAULT_FAST_INDEXING): array
    {
        $entitClassName = \get_class($entity);

        if ($this->isValueEncrypted($string)) {
            // If the value is already encrypted and there is no need to get blind index,
            // We return it as is.
            if (!$index) {
                return [$string, []];
            }

            // Otherwise, we try to decrypt it and we generate the corresponding Blind Index
            $decryptedString = $this->decrypt($entitClassName, $fieldName, $string, $filterBits, $fastIndexing);
            return [$string, [$fieldName.'_bi' => $this->getBlindIndex($entitClassName, $fieldName, $decryptedString, $filterBits, $fastIndexing)]];
        }

        $output = [];
        if (isset($this->cache[$entitClassName][$fieldName][$string])) {
            $output[] = $this->cache[$entitClassName][$fieldName][$string];
            if ($index) {
                $output[] = [$fieldName.'_bi' => $this->getBlindIndex($entitClassName, $fieldName, $string, $filterBits, $fastIndexing)];
            } else {
                $output[] = [];
            }

            return $output;
        }

        return $this->doEncrypt($entitClassName, $fieldName, $string, $index, $filterBits, $fastIndexing);
    }

    /**
     * @throws CipherSweetException
     * @throws CryptoOperationException
     * @throws BlindIndexNotFoundException
     * @throws BlindIndexNameCollisionException
     * @throws \SodiumException
     */
    protected function doEncrypt(#[\SensitiveParameter] string $entitClassName, #[\SensitiveParameter] string $fieldName, #[\SensitiveParameter] string $string, bool $index = true, int $filterBits = self::DEFAULT_FILTER_BITS, bool $fastIndexing = self::DEFAULT_FAST_INDEXING): array
    {
        $encryptedField =  (new EncryptedField($this->engine, $entitClassName, $fieldName));
        if ($index) {
            $encryptedField->addBlindIndex(
                new BlindIndex($fieldName.'_bi', [], $filterBits, $fastIndexing)
            );
        }

        $result = $encryptedField->prepareForStorage($string);

        // Cache for encrypt/decrypt
        $this->cache[$entitClassName][$fieldName][$string] = $result[0];
        $this->cache[$entitClassName][$fieldName][$result[0]] = $string;

        // Cache blind index
        if ($index) {
            $this->biCache[$entitClassName][$fieldName][$string] = $result[1][$fieldName.'_bi'];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @throws CipherSweetException
     * @throws CryptoOperationException
     */
    public function decrypt(#[\SensitiveParameter] string $entityClassName, #[\SensitiveParameter] string $fieldName, #[\SensitiveParameter] string $string, int $filterBits = self::DEFAULT_FILTER_BITS, bool $fastIndexing = self::DEFAULT_FAST_INDEXING): string
    {
        // If $string is not encrypted, we return it as is.
        if (!$this->isValueEncrypted($string)) {
            return $string;
        }

        if (isset($this->cache[$entityClassName][$fieldName][$string])) {
            return $this->cache[$entityClassName][$fieldName][$string];
        }

        return $this->doDecrypt($entityClassName, $fieldName, $string);
    }

    /**
     * @throws CipherSweetException
     * @throws CryptoOperationException
     */
    protected function doDecrypt(#[\SensitiveParameter] string $entityClassName, #[\SensitiveParameter] string $fieldName, #[\SensitiveParameter] string $string): string
    {
        $decryptedValue = (new EncryptedField($this->engine, $entityClassName, $fieldName))
            ->decryptValue($string);

        $this->cache[$entityClassName][$fieldName][$string] = $decryptedValue;
        $this->cache[$entityClassName][$fieldName][$decryptedValue] = $string;

        return $decryptedValue;
    }

    /**
     * {@inheritdoc}
     *
     * @throws CryptoOperationException
     * @throws CipherSweetException
     * @throws BlindIndexNotFoundException
     * @throws BlindIndexNameCollisionException
     * @throws \SodiumException
     */
    public function getBlindIndex(#[\SensitiveParameter] string $entityName, #[\SensitiveParameter] string $fieldName, #[\SensitiveParameter] string $value, int $filterBits = self::DEFAULT_FILTER_BITS, bool $fastIndexing = self::DEFAULT_FAST_INDEXING): string
    {
        if (isset($this->biCache[$entityName][$fieldName][$value])) {
            return $this->biCache[$entityName][$fieldName][$value];
        }

        return $this->doGetBlindIndex($entityName, $fieldName, $value, $filterBits, $fastIndexing);
    }

    /**
     * @throws CryptoOperationException
     * @throws CipherSweetException
     * @throws BlindIndexNotFoundException
     * @throws BlindIndexNameCollisionException
     * @throws \SodiumException
     */
    protected function doGetBlindIndex(#[\SensitiveParameter] string $entityName, #[\SensitiveParameter] string $fieldName, #[\SensitiveParameter] string $value, int $filterBits = self::DEFAULT_FILTER_BITS, bool $fastIndexing = self::DEFAULT_FAST_INDEXING): string
    {
        $index = (new EncryptedField($this->engine, $entityName, $fieldName))
            ->addBlindIndex(
                new BlindIndex($fieldName.'_bi', [], $filterBits, $fastIndexing)
            )
            ->getBlindIndex($value, $fieldName.'_bi');

        $this->biCache[$entityName][$fieldName][$value] = $index;

        return $index;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrefix(): string
    {
        return $this->engine->getBackend()->getPrefix();
    }

    public function isValueEncrypted(#[\SensitiveParameter] ?string $value): bool
    {
        return $value !== null && str_starts_with($value, $this->getPrefix());
    }

    public function reset(): void
    {
        $this->cache = [];
        $this->biCache = [];
    }
}
