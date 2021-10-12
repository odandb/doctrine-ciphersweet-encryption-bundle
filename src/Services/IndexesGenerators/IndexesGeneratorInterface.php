<?php

declare(strict_types=1);


namespace Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexesGenerators;


interface IndexesGeneratorInterface
{
    public function generate(string $value): array;
}
