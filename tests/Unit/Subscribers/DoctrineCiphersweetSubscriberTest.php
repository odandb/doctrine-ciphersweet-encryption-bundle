<?php

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Tests\Unit\Subscribers;

use Doctrine\ORM\EntityManagerInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors\EncryptorInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Subscribers\DoctrineCiphersweetSubscriber;
use Odandb\DoctrineCiphersweetEncryptionBundle\Tests\App\Model\MyEntityAttribute;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DoctrineCiphersweetSubscriberTest extends KernelTestCase
{
    private ?EntityManagerInterface $em;
    private ?EncryptorInterface $encryptor;
    private ?DoctrineCiphersweetSubscriber $service;

    protected function setUp(): void
    {
        static::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->encryptor = static::getContainer()->get(EncryptorInterface::class);
        $this->service = static::getContainer()->get('encryption.subscriber');
    }

    public function testProcessFieldsAttributes()
    {
        $entity = new MyEntityAttribute('test');
        $this->service->processFields($entity, $this->em);

        $this->assertStringStartsWith($this->encryptor->getPrefix(), $entity->getAccountName());

        $this->service->processFields($entity, $this->em, false);
        $this->assertSame('test', $entity->getAccountName());
    }
}
