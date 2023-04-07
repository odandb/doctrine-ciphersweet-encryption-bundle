<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

trait IndexedEntityTrait
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected int $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected string $fieldname;

    /**
     * onDeleteCascade -> If we delete $targetEntity, we need to delete cascade to the list EntityFilters
     *
     * @ORM\JoinColumn(name="target_entity_id", referencedColumnName="id", onDelete="CASCADE")
     */
    protected object $targetEntity;

    /**
     * @ORM\Column(type="string", length=10)
     */
    protected string $indexBi;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFieldname(): string
    {
        return $this->fieldname;
    }

    public function setFieldname(string $fieldname): self
    {
        $this->fieldname = $fieldname;
        return $this;
    }

    public function getTargetEntity(): object
    {
        return $this->targetEntity;
    }

    public function setTargetEntity(?object $targetEntity): self
    {
        $this->targetEntity = $targetEntity;
        return $this;
    }

    public function getIndexBi(): string
    {
        return $this->indexBi;
    }

    public function setIndexBi(string $indexBi): self
    {
        $this->indexBi = $indexBi;
        return $this;
    }
}
