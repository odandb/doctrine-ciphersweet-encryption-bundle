<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Tests\App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Odandb\DoctrineCiphersweetEncryptionBundle\OdandbDoctrineCiphersweetEncryptionBundle;
use Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        if (self::VERSION_ID < 60200) {
            yield new SensioFrameworkExtraBundle();
        }
        yield new OdandbDoctrineCiphersweetEncryptionBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(__DIR__ . '/config.yaml');
        $loader->load(__DIR__ . '/doctrine.yaml');

        if (self::VERSION_ID < 60200) {
            $loader->load(__DIR__ . '/deprecated.yaml');
        }
    }
}
