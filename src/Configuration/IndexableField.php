<?php

declare(strict_types=1);


namespace Odandb\DoctrineCiphersweetEncryptionBundle\Configuration;

use Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors\EncryptorInterface;

/**
 * The Encrypted class handles the @IndexableField annotation.
 *
 * @Annotation
 */
class IndexableField
{
    public bool $autoRefresh = true;
    public string $indexesEntityClass;
    public array $indexesGenerationMethods;
    public string $valuePreprocessMethod;
    public bool $fastIndexing = EncryptorInterface::DEFAULT_FAST_INDEXING;
}
