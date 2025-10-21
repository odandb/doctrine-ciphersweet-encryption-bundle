<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class TypeExtractorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('encryption.property_hydrator')) {
            return;
        }

        $propertyHydratorDefinition = $container->getDefinition('encryption.property_hydrator');

        // Use type_info.resolver if available (Symfony 7.1+), otherwise fall back to property_info
        $typeExtractorService = $container->has('type_info.resolver')
            ? new Reference('type_info.resolver')
            : new Reference('property_info');

        $propertyHydratorDefinition->replaceArgument(0, $typeExtractorService);
    }
}
