<?php

declare(strict_types=1);


namespace Odandb\DoctrineCiphersweetEncryptionBundle\Tests\Repository;


use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Odandb\DoctrineCiphersweetEncryptionBundle\Tests\Model\Attributes\MyEntityAttribute;

class MyEntityRepositoryAttribute extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MyEntityAttribute::class);
    }
}
