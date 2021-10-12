<?php

declare(strict_types=1);


namespace Odandb\DoctrineCiphersweetEncryptionBundle\Configuration;

/**
 * The Encrypted class handles the @EncryptedField annotation.
 *
 * @Annotation
 */
class EncryptedField
{
    /** @var int */
    public int $filterBits = 32;

    public string $mappedTypedProperty;

    /** @var bool */
    public bool $indexable = true;
}
