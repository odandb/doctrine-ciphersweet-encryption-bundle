<?php

declare(strict_types=1);


namespace Odandb\DoctrineCiphersweetEncryptionBundle\Tests\App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    private const CONFIG_EXTS = '.{yaml,yml}';

    public function registerBundles(): iterable
    {
        $contents = require $this->getProjectDir().'/config/bundles.php';
        foreach ($contents as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                yield new $class();
            }
        }
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__).'/App';
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->addResource(new FileResource($this->getProjectDir().'/config/bundles.php'));
        $container->setParameter('container.dumper.inline_class_loader', \PHP_VERSION_ID < 70400 || $this->debug);
        $container->setParameter('container.dumper.inline_factories', true);

        $loader->load($this->getProjectDir().'/config/services'.self::CONFIG_EXTS, 'glob');
        $loader->load($this->getProjectDir().'/config/{packages}/*'.self::CONFIG_EXTS, 'glob');

        if (PHP_VERSION_ID >= 80000) {
            $loader->load($this->getProjectDir().'/config/doctrine80'.self::CONFIG_EXTS, 'glob');
        } else {
            $loader->load($this->getProjectDir().'/config/doctrine74'.self::CONFIG_EXTS, 'glob');
        }

        $confDir = $this->getProjectDir().'/../../src/Resources/config';
        $loader->load($confDir.'/encryption-services'.self::CONFIG_EXTS, 'glob');
    }
}
