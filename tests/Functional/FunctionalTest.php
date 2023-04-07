<?php

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Tests\Functional;

use Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors\EncryptorInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FunctionalTest extends WebTestCase
{
    private ?KernelBrowser $client;
    private ?EncryptorInterface $encryptor;

    protected function setUp() : void
    {
        $this->client = self::createClient();

        $this->encryptor = static::getContainer()->get(EncryptorInterface::class);
    }

    public function testEntityListDecrypt(): void
    {
        static::getContainer()->get('services_resetter')->reset();

        $this->client->request('GET', '/entities');

        self::assertResponseIsSuccessful();
        self::assertSame(8, $this->encryptor->callsCount['decrypt'], 'Decrypt method is not called the required number of times'); // 4 entities loaded with 2 fields

        $json = json_decode($this->client->getResponse()->getContent(), true);
        self::assertIsArray($json['data']);
        self::assertIsInt($json['data'][0]['accountNumber']);
    }

    public function testCreateEntityEncrypt(): void
    {
        static::getContainer()->get('services_resetter')->reset();

        $this->client->request('POST', '/entity');

        self::assertResponseIsSuccessful();
        self::assertSame(2, $this->encryptor->callsCount['encrypt'], 'Encrypt method is not called the required number of times'); // 1 entity loaded with 2 fields
    }

    public function testUpdateEntityDecryptAndEncrypt(): void
    {
        static::getContainer()->get('services_resetter')->reset();

        $this->client->request('PUT', '/entity/1');

        self::assertResponseIsSuccessful();
        self::assertSame(2, $this->encryptor->callsCount['decrypt'], 'Decrypt method is not called the required number of times'); // 1 entity loaded with 2 fields
        self::assertSame(1, $this->encryptor->callsCount['encrypt'], 'Encrypt method is not called the required number of times'); // 1 field edited
    }
}
