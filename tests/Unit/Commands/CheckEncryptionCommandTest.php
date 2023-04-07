<?php

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Tests\Unit\Commands;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Bundle\FrameworkBundle\Console\Application;

class CheckEncryptionCommandTest extends KernelTestCase
{
    public function testCheckEncryptionDataCommand()
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);
        $command = $application->find('odb:enc:check');

        $commandTester = new CommandTester($command);
        // Equals to a user inputting "This", "That" and hitting ENTER
        // This can be used for answering two separated questions for instance

        $className = 'Odandb\\DoctrineCiphersweetEncryptionBundle\\Tests\\Model\\Attributes\\MyEntityAttribute';

        $commandTester->setInputs([$className]);
        $commandTester->execute(['command' => $command->getName(), '--interactive']);
        $commandTester->assertCommandIsSuccessful();
    }
}
