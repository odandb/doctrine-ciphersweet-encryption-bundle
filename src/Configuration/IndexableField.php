<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Configuration;

use Attribute;
use Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors\EncryptorInterface;

/**
 * The Encrypted class handles the @IndexableField annotation.
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"PROPERTY","ANNOTATION"})
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class IndexableField
{
    /** @readonly  */
    public string $indexesEntityClass;

    /** @readonly  */
    public bool $autoRefresh = true;

    /**
     * @readonly
     * @var array<int, string>
     */
    public array $indexesGenerationMethods = [];

    /** @readonly  */
    public ?string $valuePreprocessMethod = null;

    /** @readonly  */
    public bool $fastIndexing = EncryptorInterface::DEFAULT_FAST_INDEXING;

    public function __construct(
        string $indexesEntityClass,
        bool $autoRefresh = true,
        array $indexesGenerationMethods = [],
        ?string $valuePreprocessMethod = null,
        bool $fastIndexing = EncryptorInterface::DEFAULT_FAST_INDEXING
    ) {
        $this->indexesEntityClass = $indexesEntityClass;
        $this->autoRefresh = $autoRefresh;
        $this->indexesGenerationMethods = $indexesGenerationMethods;
        $this->valuePreprocessMethod = $valuePreprocessMethod;
        $this->fastIndexing = $fastIndexing;
    }
}
