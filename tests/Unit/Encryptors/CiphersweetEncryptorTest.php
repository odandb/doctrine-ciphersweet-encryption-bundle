<?php

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Tests\Unit\Encryptors;

use Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors\CiphersweetEncryptor;
use Odandb\DoctrineCiphersweetEncryptionBundle\Tests\Encryptors\CiphersweetEncryptorObservable;
use Odandb\DoctrineCiphersweetEncryptionBundle\Tests\Model\Annotations\MyEntity;
use ParagonIE\CipherSweet\Backend\ModernCrypto;
use ParagonIE\CipherSweet\CipherSweet;
use ParagonIE\CipherSweet\KeyProvider\RandomProvider;
use PHPUnit\Framework\TestCase;

class CiphersweetEncryptorTest extends TestCase
{
    private CiphersweetEncryptor $encryptor;

    protected function setUp(): void
    {
        $backend = new ModernCrypto();
        $engine = new CipherSweet(new RandomProvider($backend), $backend);
        $this->encryptor = new CiphersweetEncryptorObservable($engine);
    }

    public function testGetBlindIndex()
    {
        $bi = $this->encryptor->getBlindIndex('my_entity', 'account_name', 'test');
        $this->assertSame(8, mb_strlen($bi));
    }

    public function testPrepareForStorage()
    {
        $this->encryptor->prepareForStorage(new MyEntity('132456'), 'account_name', 'test1');
        $this->encryptor->prepareForStorage(new MyEntity('132456'), 'account_name', 'test1');
        $this->encryptor->prepareForStorage(new MyEntity('132456'), 'account_name', 'test1');
        $result = $this->encryptor->prepareForStorage(new MyEntity('132456'), 'account_name', 'test1');
        $this->assertSame(2, count($result));
        $this->assertSame(65, mb_strlen($result[0]));
        $this->assertSame(8, mb_strlen($result[1]['account_name_bi']));

        $this->assertSame(1, $this->encryptor->callsCount['encrypt']);
    }

    public function testGetPrefix()
    {
        $this->assertSame('nacl:', $this->encryptor->getPrefix());
    }

    public function testDecrypt()
    {
        [$encryptedString] = $this->encryptor->prepareForStorage(new MyEntity('132456'), 'account_name', 'test');

        $this->encryptor->decrypt(MyEntity::class, 'account_name', $encryptedString);
        $this->encryptor->decrypt(MyEntity::class, 'account_name', $encryptedString);
        $this->encryptor->decrypt(MyEntity::class, 'account_name', $encryptedString);

        $result = $this->encryptor->decrypt(MyEntity::class, 'account_name', $encryptedString);
        $this->assertSame('test', $result);
        $this->assertSame(0, $this->encryptor->callsCount['decrypt'], 'doDecrypt is never called because cache is set upon prepareForStorage call');
    }

    public function testDecryptNonEncryptedValue()
    {
        [$encryptedString] = $this->encryptor->prepareForStorage(new MyEntity('132456'), 'account_name', 'test');

        $decryptedString = $this->encryptor->decrypt(MyEntity::class, 'account_name', $encryptedString);
        $untouchedString = $this->encryptor->decrypt(MyEntity::class, 'account_name', $decryptedString);

        $this->assertSame($decryptedString, $untouchedString);
        $this->assertSame(1, $this->encryptor->callsCount['encrypt']);
        $this->assertSame(0, $this->encryptor->callsCount['decrypt'], 'doDecrypt is never called either because of cache set upon prepareForStorage call or we detect value is already decrypted');
    }

    public function testEncryptAlreadyEncryptedValue()
    {
        [$encryptedString] = $this->encryptor->prepareForStorage(new MyEntity('132456'), 'account_name', 'test', false);
        [$unTouchedEncryptedString] = $this->encryptor->prepareForStorage(new MyEntity('132456'), 'account_name', 'test', false);
        [$unTouchedEncryptedStringBis, $bi] = $this->encryptor->prepareForStorage(new MyEntity('132456'), 'account_name', 'test', true);

        $this->assertSame($encryptedString, $unTouchedEncryptedString);
        $this->assertSame($encryptedString, $unTouchedEncryptedStringBis);
        $this->assertSame(8, mb_strlen($bi['account_name_bi']));
        $this->assertSame(1, $this->encryptor->callsCount['encrypt']);
        $this->assertSame(0, $this->encryptor->callsCount['decrypt']);
    }
}
