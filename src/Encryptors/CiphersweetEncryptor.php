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
        $entitClassName= \get_class($entity);
        if (isset($this->cache[$entitClassName][$fieldName][$string])) {
            return $this->cache[$entitClassName][$fieldName][$string];
        }

        $encryptedField =  (new EncryptedField($this->engine, $entitClassName, $fieldName));
        if ($index) {
            $encryptedField->addBlindIndex(
                new BlindIndex($fieldName.'_bi', [], $filterBits, $fastIndexing)
            );
        }

        $result = $encryptedField->prepareForStorage($string);

        $this->cache[$entitClassName][$fieldName][$string] = $result;

        return $result;
    }

    public function decrypt(string $entity_classname, string $fieldName, string $string, int $filterBits = self::DEFAULT_FILTER_BITS, bool $fastIndexing = self::DEFAULT_FAST_INDEXING): string
    {
        return (new EncryptedField($this->engine, $entity_classname, $fieldName))
            ->addBlindIndex(
                new BlindIndex($fieldName.'_bi', [], $filterBits, $fastIndexing)
            )
            ->decryptValue($string);
    }

    public function getBlindIndex($entityName, $fieldName, string $value, int $filterBits = self::DEFAULT_FILTER_BITS, bool $fastIndexing = self::DEFAULT_FAST_INDEXING): string
    {
        if (isset($this->biCache[$entityName][$fieldName][$value])) {
            return $this->biCache[$entityName][$fieldName][$value];
        }

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
}
