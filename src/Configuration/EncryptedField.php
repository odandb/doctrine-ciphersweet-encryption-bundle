<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Configuration;

use Attribute;
use Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors\EncryptorInterface;

/**
 * The Encrypted class handles the @EncryptedField annotation.
 *
 * @Annotation
 * @NamedArgumentConstructor
 * @Target({"PROPERTY","ANNOTATION"})
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class EncryptedField
{
    /** @readonly  */
    public int $filterBits = EncryptorInterface::DEFAULT_FILTER_BITS;

    /** @readonly  */
    public bool $indexable = true;

    /** @readonly  */
    public ?string $mappedTypedProperty = null;

    public function __construct(
        int $filterBits = EncryptorInterface::DEFAULT_FILTER_BITS,
        bool $indexable = true,
        ?string $mappedTypedProperty = null
    ) {
        $this->filterBits = $filterBits;
        $this->indexable = $indexable;
        $this->mappedTypedProperty = $mappedTypedProperty;
    }
}
