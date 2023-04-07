<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Entity;

interface IndexedEntityInterface
{
    public function setTargetEntity(?object $targetEntity): self;

    public function getIndexBi(): string;

    public function setIndexBi(string $index): self;

    public function getFieldname(): string;

    public function setFieldname(string $fieldname): self;
}
