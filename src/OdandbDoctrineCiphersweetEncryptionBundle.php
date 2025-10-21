<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle;

use Odandb\DoctrineCiphersweetEncryptionBundle\DependencyInjection\Compiler\IndexGeneratorPass;
use Odandb\DoctrineCiphersweetEncryptionBundle\DependencyInjection\Compiler\TypeExtractorPass;
use Odandb\DoctrineCiphersweetEncryptionBundle\DependencyInjection\DoctrineCiphersweetEncryptionExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class OdandbDoctrineCiphersweetEncryptionBundle extends Bundle
{
    /**
     * Overridden to allow for the custom extension alias.
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new DoctrineCiphersweetEncryptionExtension();
        }

        return $this->extension;
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new IndexGeneratorPass());
        $container->addCompilerPass(new TypeExtractorPass());
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
