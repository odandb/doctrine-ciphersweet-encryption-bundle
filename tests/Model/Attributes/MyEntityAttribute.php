<?php

declare(strict_types=1);


namespace Odandb\DoctrineCiphersweetEncryptionBundle\Tests\Model\Attributes;


use Doctrine\ORM\Mapping as ORM;
use Odandb\DoctrineCiphersweetEncryptionBundle\Configuration\EncryptedField;
use Odandb\DoctrineCiphersweetEncryptionBundle\Tests\Repository\MyEntityRepositoryAttribute;

#[ORM\Entity(repositoryClass: MyEntityRepositoryAttribute::class)]
class MyEntityAttribute
{
    #[ORM\Column(type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    protected ?int $id = null;

    #[ORM\Column(type: 'string')]
    #[EncryptedField]
    private string $accountName;

    #[ORM\Column(type: 'string', length: 10)]
    private string $accountNameBi;

    public function __construct(string $accountName)
    {
        $this->accountName = $accountName;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccountName(): string
    {
        return $this->accountName;
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
}
