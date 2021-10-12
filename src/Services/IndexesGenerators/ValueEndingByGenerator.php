<?php

declare(strict_types=1);


namespace Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexesGenerators;

class ValueEndingByGenerator implements IndexesGeneratorInterface
{
    /**
     * @param string $value
     * @return array
     */
    public function generate(string $value): array
    {
        $possibleValues[] = $value;

        for($i=1, $len = mb_strlen($value); $i < $len; $i++) {
            $possibleValues[] = mb_substr($value, 0, -$i);
        }

        return $possibleValues;
    }
}
