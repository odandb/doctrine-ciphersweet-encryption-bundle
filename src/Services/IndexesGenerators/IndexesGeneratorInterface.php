<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexesGenerators;

/**
 * @method static string getIndexKey()
 */
interface IndexesGeneratorInterface
{
    /**
     * @return array<int, string>
     */
    public function generate(string $value): array;
}
