<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Tests\App\Model;

use Doctrine\ORM\Mapping as ORM;
use Odandb\DoctrineCiphersweetEncryptionBundle\Configuration\EncryptedField;
use Odandb\DoctrineCiphersweetEncryptionBundle\Configuration\IndexableField;
use Odandb\DoctrineCiphersweetEncryptionBundle\Tests\App\Repository\MyEntityAttributeRepository;

#[ORM\Entity(repositoryClass: MyEntityAttributeRepository::class)]
class MyEntityAttribute
{
    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    protected ?int $id = null;

    #[ORM\Column(type: 'string')]
    #[EncryptedField]
    #[IndexableField(indexesEntityClass: MyEntityAttributeIndexes::class, indexesGenerationMethods: ['ValueStartingBy'], valuePreprocessMethod: 'cleanAccountNumber')]
    private string $accountName;

    #[ORM\Column(type: 'string', length: 10)]
    private string $accountNameBi;

    #[ORM\Column(type: 'string', nullable: true)]
    #[EncryptedField(indexable: false, mappedTypedProperty: 'accountNumberType')]
    private ?string $accountNumber = null;

    private ?int $accountNumberType;

    public function __construct(string $accountName, ?int $accountNumberType = null)
    {
        $this->accountName = $accountName;
        $this->accountNumberType = $accountNumberType;
        $this->accountNumber = null !== $this->accountNumberType ? (string) $this->accountNumberType : null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccountName(): string
    {
        return $this->accountName;
    }

    public static function cleanAccountNumber(string $value): string
    {
        return trim($value);
    }

    public function setAccountName(string $accountName): void
    {
        $this->accountName = $accountName;
    }

    public function getAccountNameBi(): string
    {
        return $this->accountNameBi;
    }

    public function setAccountNameBi(string $accountNameBi): self
    {
        $this->accountNameBi = $accountNameBi;

        return $this;
    }

    public function getAccountNumber(): string
    {
        return $this->accountNumber;
    }

    public function setAccountNumber(string $accountNumber): self
    {
        $this->accountNumber = $accountNumber;

        return $this;
    }

    public function getAccountNumberType(): int
    {
        return $this->accountNumberType;
    }

    public function setAccountNumberType(int $accountNumberType): self
    {
        $this->accountNumberType = $accountNumberType;
        $this->accountNumber = (string) $this->accountNumberType;

        return $this;
    }
}
