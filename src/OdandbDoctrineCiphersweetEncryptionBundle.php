<?php

declare(strict_types=1);


namespace Odandb\DoctrineCiphersweetEncryptionBundle;


use Odandb\DoctrineCiphersweetEncryptionBundle\DependencyInjection\DoctrineCiphersweetEncryptionExtension;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class OdandbDoctrineCiphersweetEncryptionBundle extends Bundle
{
    /**
     * Overridden to allow for the custom extension alias.
     */
    public function getContainerExtension(): Extension
    {
        if (null === $this->extension) {
            $this->extension = new DoctrineCiphersweetEncryptionExtension();
        }

        return $this->extension;
    }
}
