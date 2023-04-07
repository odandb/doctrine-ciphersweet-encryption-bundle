<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class IndexGeneratorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('encryption.indexes_generator')) {
            return;
        }

        $indexesGeneratorDefinition = $container->getDefinition('encryption.indexes_generator');

        $mapping = [];
        foreach ($container->findTaggedServiceIds('encryption.index_generator') as $id => $attributes) {
            $class = $container->getDefinition($id)->getClass();
            if (method_exists($class, 'getIndexKey')) {
                $mapping[$class::getIndexKey()] = new Reference($id);

                continue;
            }

            $mapping[$attributes['key']] = new Reference($id);
        }

        $services = $container->findTaggedServiceIds('odb.index_generator');
        if (\count($services) > 0) {
            trigger_deprecation(
                'odandb/doctrine-ciphersweet-encryption-bundle',
                '0.11',
                'The tag "odb.index_generator" is deprecated and will be remove in doctrine-ciphersweet-encryption-bundle 1.0'
            );

            foreach ($services as $id => $attributes) {
                $mapping[$attributes['key']] = new Reference($id);
            }
        }

        $indexesGeneratorDefinition->replaceArgument(0, ServiceLocatorTagPass::register($container, $mapping));
    }
}
