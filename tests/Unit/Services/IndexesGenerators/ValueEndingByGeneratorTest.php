<?php

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Tests\Unit\Services\IndexesGenerators;

use Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexesGenerators\ValueEndingByGenerator;
use PHPUnit\Framework\TestCase;

class ValueEndingByGeneratorTest extends TestCase
{
    public function testGenerate(): void
    {
        $generator = new ValueEndingByGenerator();

        $this->assertEquals(['t', 'st', 'est', 'test'], $generator->generate('test'));
        $this->assertEquals(['t'], $generator->generate('t'));
        $this->assertEquals([], $generator->generate(''));
    }
}
