<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\DependencyInjection;

use Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexesGenerators\IndexesGeneratorInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class DoctrineCiphersweetEncryptionExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.php');

        $container->registerForAutoconfiguration(IndexesGeneratorInterface::class)
            ->addTag('encryption.index_generator');
    }
}
