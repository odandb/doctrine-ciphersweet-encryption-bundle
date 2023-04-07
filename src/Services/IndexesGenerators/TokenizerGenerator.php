<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexesGenerators;

class TokenizerGenerator implements IndexesGeneratorInterface
{
    public function generate(string $value): array
    {
        $value = trim(preg_replace(array('/[^a-zA-Z0-9\-]/', '/\s+/'), ' ', $value));
        return explode(' ', $value);
    }

    public static function getIndexKey(): string
    {
        return 'TokenizerGenerator';
    }
}
