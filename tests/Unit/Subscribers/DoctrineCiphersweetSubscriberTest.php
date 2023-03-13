<?php

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Tests\Unit\Subscribers;

use Doctrine\ORM\EntityManagerInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors\EncryptorInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Subscribers\DoctrineCiphersweetSubscriber;
use Odandb\DoctrineCiphersweetEncryptionBundle\Tests\Model\Annotations\MyEntity;
use Odandb\DoctrineCiphersweetEncryptionBundle\Tests\Model\Attributes\MyEntityAttribute;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DoctrineCiphersweetSubscriberTest extends KernelTestCase
{
    private ?EntityManagerInterface $em;
    private ?EncryptorInterface $encryptor;
    private ?DoctrineCiphersweetSubscriber $service;

    public function setUp(): void
    {
        static::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->encryptor = static::getContainer()->get(EncryptorInterface::class);
        $this->service = static::getContainer()->get(DoctrineCiphersweetSubscriber::class);
    }

    /**
     * @group legacy
     */
    public function testProcessFieldsAnnotations()
    {
        $entity = new MyEntity('test');
        $this->service->processFields($entity, $this->em);

        $this->assertStringStartsWith($this->encryptor->getPrefix(), $entity->getAccountName());

        $this->service->processFields($entity, $this->em, false);
        $this->assertSame('test', $entity->getAccountName());
    }

    public function testProcessFieldsAttributes()
    {
        if (PHP_VERSION_ID < 80000) {
            $this->markTestSkipped('require PHP 8.0');
        }

        $entity = new MyEntityAttribute('test');
        $this->service->processFields($entity, $this->em);

        $this->assertStringStartsWith($this->encryptor->getPrefix(), $entity->getAccountName());

        $this->service->processFields($entity, $this->em, false);
        $this->assertSame('test', $entity->getAccountName());
    }
}
