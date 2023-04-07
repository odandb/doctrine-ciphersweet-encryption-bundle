<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Tests\App\Encryptors;

use Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors\CiphersweetEncryptor;
use Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors\EncryptorInterface;

class CiphersweetEncryptorObservable extends CiphersweetEncryptor implements EncryptorInterface
{
    public array $callsCount = [
        'encrypt' => 0,
        'decrypt' => 0,
    ];

    protected function doEncrypt(string $entitClassName, string $fieldName, string $string, bool $index = true, int $filterBits = self::DEFAULT_FILTER_BITS, bool $fastIndexing = self::DEFAULT_FAST_INDEXING): array
    {
        $this->callsCount['encrypt']++;
        return parent::doEncrypt($entitClassName, $fieldName, $string, $index, $filterBits, $fastIndexing);
    }

    protected function doDecrypt(string $entityClassName, string $fieldName, string $string): string
    {
        $this->callsCount['decrypt']++;
        return parent::doDecrypt($entityClassName, $fieldName, $string);
    }
}
