<?php

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Tests\Unit\Commands;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class EncryptionDataCommandTest extends KernelTestCase
{
    public function testEncryptAndDecryptDataCommand()
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);
        $command = $application->find('odb:enc:data');

        $commandTester = new CommandTester($command);
        // Equals to a user inputting "This", "That" and hitting ENTER
        // This can be used for answering two separated questions for instance

        $className = 'Odandb\\DoctrineCiphersweetEncryptionBundle\\Tests\\Model\\Annotations\\MyEntity';

        $commandTester->setInputs([$className, 'accountName', 'Test']);
        $commandTester->execute(['command' => $command->getName(), '--encrypt' => true]);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('[brng:', $output);
    }

    public function testEncryptAndDecryptDataCommandOnAttribute()
    {
        if (PHP_VERSION_ID < 80000) {
            $this->markTestSkipped('require PHP 8.0');
        }

        $kernel = static::createKernel();
        $application = new Application($kernel);
        $command = $application->find('odb:enc:data');

        $commandTester = new CommandTester($command);
        // Equals to a user inputting "This", "That" and hitting ENTER
        // This can be used for answering two separated questions for instance

        $className = 'Odandb\\DoctrineCiphersweetEncryptionBundle\\Tests\\Model\\Attributes\\MyEntityAttribute';

        $commandTester->setInputs([$className, 'accountName', 'Test']);
        $commandTester->execute(['command' => $command->getName(), '--encrypt' => true]);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('[brng:', $output);
    }
}
