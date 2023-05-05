<?php

declare(strict_types=1);


namespace Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors;

use ParagonIE\CipherSweet\BlindIndex;
use ParagonIE\CipherSweet\CipherSweet;
use ParagonIE\CipherSweet\EncryptedField;

class CiphersweetEncryptor implements EncryptorInterface
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

    public function prepareForStorage(object $entity, string $fieldName, string $string, bool $index = true, int $filterBits = self::DEFAULT_FILTER_BITS, bool $fastIndexing = self::DEFAULT_FAST_INDEXING): array
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

    protected function doEncrypt(string $entitClassName, string $fieldName, string $string, bool $index = true, int $filterBits = self::DEFAULT_FILTER_BITS, bool $fastIndexing = self::DEFAULT_FAST_INDEXING): array
    {
        $encryptedField =  (new EncryptedField($this->engine, $entitClassName, $fieldName));
        if ($index) {
            $encryptedField->addBlindIndex(
                new BlindIndex($fieldName.'_bi', [], $filterBits, $fastIndexing)
            );
        }

        $result = $encryptedField->prepareForStorage($string);

        $this->cache[$entitClassName][$fieldName][$string] = $result[0];
        $this->cache[$entitClassName][$fieldName][$result[0]] = $string;

        if ($index) {
            $this->biCache[$entitClassName][$fieldName][$string] = $result[1][$fieldName.'_bi'];
        }

        return $result;
    }

    public function decrypt(string $entity_classname, string $fieldName, string $string, int $filterBits = self::DEFAULT_FILTER_BITS, bool $fastIndexing = self::DEFAULT_FAST_INDEXING): string
    {
        // If $string is not encrypted, we return it as is.
        if (!$this->isValueEncrypted($string)) {
            return $string;
        }

        if (isset($this->cache[$entity_classname][$fieldName][$string])) {
            return $this->cache[$entity_classname][$fieldName][$string];
        }

        return $this->doDecrypt($entity_classname, $fieldName, $string);
    }

    protected function doDecrypt(string $entity_classname, string $fieldName, string $string): string
    {
        $decryptedValue = (new EncryptedField($this->engine, $entity_classname, $fieldName))
            ->decryptValue($string);

        $this->cache[$entity_classname][$fieldName][$string] = $decryptedValue;
        $this->cache[$entity_classname][$fieldName][$decryptedValue] = $string;

        return $decryptedValue;
    }

    public function getBlindIndex($entityName, $fieldName, string $value, int $filterBits = self::DEFAULT_FILTER_BITS, bool $fastIndexing = self::DEFAULT_FAST_INDEXING): string
    {
        if (isset($this->biCache[$entityName][$fieldName][$value])) {
            return $this->biCache[$entityName][$fieldName][$value];
        }

        return $this->doGetBlindIndex($entityName, $fieldName, $value, $filterBits, $fastIndexing);
    }

    private function doGetBlindIndex($entityName, $fieldName, string $value, int $filterBits = self::DEFAULT_FILTER_BITS, bool $fastIndexing = self::DEFAULT_FAST_INDEXING): string
    {
        $index = (new EncryptedField($this->engine, $entityName, $fieldName))
            ->addBlindIndex(
                new BlindIndex($fieldName.'_bi', [], $filterBits, $fastIndexing)
            )
            ->getBlindIndex($value, $fieldName.'_bi');

        $this->biCache[$entityName][$fieldName][$value] = $index;

        return $index;
    }

    public function getPrefix(): string
    {
        return $this->engine->getBackend()->getPrefix();
    }

    public function isValueEncrypted(?string $value): bool
    {
        return $value !== null && strpos($value, $this->getPrefix()) === 0;
    }


}
