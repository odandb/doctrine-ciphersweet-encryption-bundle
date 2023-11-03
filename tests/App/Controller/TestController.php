<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Tests\App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Tests\App\Model\MyEntityAttribute;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class TestController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function entityList(): JsonResponse
    {
        $entities = $this->em->createQueryBuilder()
            ->select('e')
            ->from(MyEntityAttribute::class, 'e')
            ->getQuery()
            ->getResult()
        ;

        $data = [];
        /** @var MyEntityAttribute $entity */
        foreach ($entities as $key => $entity) {
            $data[$key]['accountName'] = $entity->getAccountName();
            $data[$key]['accountNumber'] = $entity->getAccountNumberType();
        }

        return new JsonResponse(['data' => $data]);
    }

    public function createEntity(): JsonResponse
    {
        $entity = new MyEntityAttribute('test', 1305);

        $this->em->persist($entity);
        $this->em->flush();

        return new JsonResponse();
    }

    public function updateEntity(MyEntityAttribute $entity): JsonResponse
    {
        $entity->setAccountNumberType(1994);

        $this->em->flush();

        return new JsonResponse();
    }
}
