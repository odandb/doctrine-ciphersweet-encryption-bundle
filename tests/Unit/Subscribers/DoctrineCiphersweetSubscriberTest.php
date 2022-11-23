<?php

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Tests\Unit\Subscribers;

use Doctrine\ORM\EntityManagerInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors\EncryptorInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Subscribers\DoctrineCiphersweetSubscriber;
use Odandb\DoctrineCiphersweetEncryptionBundle\Tests\Model\MyEntity;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DoctrineCiphersweetSubscriberTest extends KernelTestCase
{
    public function testProcessFields()
    {
        static::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $encryptor = static::getContainer()->get(EncryptorInterface::class);
        $service = static::getContainer()->get(DoctrineCiphersweetSubscriber::class);
        $this->assertNotNull($service);

        $entity = new MyEntity('test');
        $service->processFields($entity, $em);

        $this->assertStringStartsWith($encryptor->getPrefix(), $entity->getAccountName());

        $service->processFields($entity, $em, false);
        $this->assertSame('test', $entity->getAccountName());
    }
}
