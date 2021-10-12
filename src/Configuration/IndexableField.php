<?php

declare(strict_types=1);


namespace Odandb\DoctrineCiphersweetEncryptionBundle\Configuration;

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
}
