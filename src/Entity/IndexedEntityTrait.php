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
     * @var object
     *
     * @ORM\JoinColumn(name="target_entity_id", referencedColumnName="id", onDelete="CASCADE")
     * onDeleteCascade -> si on supprime $targetEntity, on cascade la suppression jusqu'à la liste de EntityFilters
     */
    protected object $targetEntity;

    /**
     * @ORM\Column(type="string", length=10)
     */
    protected string $indexBi;

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getFieldname(): string
    {
        return $this->fieldname;
    }

    /**
     * @param string $fieldname
     * @return IndexedEntityTrait
     */
    public function setFieldname(string $fieldname): self
    {
        $this->fieldname = $fieldname;
        return $this;
    }

    /**
     * @return object
     */
    public function getTargetEntity(): object
    {
        return $this->targetEntity;
    }

    /**
     * @param object|null $targetEntity
     * @return $this
     */
    public function setTargetEntity(?object $targetEntity): self
    {
        $this->targetEntity = $targetEntity;
        return $this;
    }

    /**
     * @return string
     */
    public function getIndexBi(): string
    {
        return $this->indexBi;
    }

    /**
     * @param string $indexBi
     * @return self
     */
    public function setIndexBi(string $indexBi): self
    {
        $this->indexBi = $indexBi;
        return $this;
    }
}
