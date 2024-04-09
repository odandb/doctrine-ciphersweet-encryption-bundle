<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Tests\App\Model;

use Doctrine\ORM\Mapping as ORM;
use Odandb\DoctrineCiphersweetEncryptionBundle\Configuration\EncryptedField;
use Odandb\DoctrineCiphersweetEncryptionBundle\Configuration\IndexableField;
use Odandb\DoctrineCiphersweetEncryptionBundle\Entity\IndexedEntityAttributeTrait;
use Odandb\DoctrineCiphersweetEncryptionBundle\Entity\IndexedEntityInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Tests\App\Repository\MyEntityAttributeIndexesRepository;

#[ORM\Entity(repositoryClass: MyEntityAttributeIndexesRepository::class)]
class MyEntityAttributeIndexes implements IndexedEntityInterface
{
    use IndexedEntityAttributeTrait;

    #[ORM\ManyToOne(targetEntity: MyEntityAttribute::class)]
    #[ORM\JoinColumn(name: 'target_entity_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    protected object $targetEntity;
}
