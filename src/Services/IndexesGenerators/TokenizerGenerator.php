<?php

declare(strict_types=1);


namespace Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexesGenerators;


class TokenizerGenerator
{
    /**
     * @param string $string
     * @return array
     */
    public function generate(string $string): array
    {
        $string = trim(preg_replace(array('/[^a-zA-Z0-9\-]/', '/\s+/'), ' ', $string));
        return explode(' ', $string);
    }
}
