<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Tests\App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Odandb\DoctrineCiphersweetEncryptionBundle\Tests\App\Model\MyEntityAttributeIndexes;

class MyEntityAttributeIndexesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MyEntityAttributeIndexes::class);
    }
}
