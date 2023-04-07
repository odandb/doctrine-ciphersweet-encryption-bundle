<?php

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Tests\Unit\Services\IndexesGenerators;

use Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexesGenerators\TokenizerGenerator;
use PHPUnit\Framework\TestCase;

class TokenizerGeneratorTest extends TestCase
{
    public function testGenerate(): void
    {
        $generator = new TokenizerGenerator();

        $this->assertEquals(['test'], $generator->generate('test'));
        $this->assertEquals(['test', 'test'], $generator->generate('test test'));
        $this->assertEquals(['test', 'test', 'test'], $generator->generate('test test test'));
        $this->assertEquals(['tes1-test', 'test', 'test'], $generator->generate('tes1-test test test'));
        $this->assertEquals(['tes1', 'test', 'test', 'test'], $generator->generate('tes1/test test test'));
    }
}
