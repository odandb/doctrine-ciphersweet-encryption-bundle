<?php

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Tests\Unit\Services\IndexesGenerators;

use Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexesGenerators\ValueStartingByGenerator;
use PHPUnit\Framework\TestCase;

class ValueStartingByGeneratorTest extends TestCase
{
    public function testGenerate(): void
    {
        $generator = new ValueStartingByGenerator();

        $this->assertEquals(['t', 'te', 'tes', 'test'], $generator->generate('test'));
        $this->assertEquals(['t'], $generator->generate('t'));
        $this->assertEquals([], $generator->generate(''));
    }
}
