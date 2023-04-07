<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Services;

use Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors\EncryptorInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Exception\UndefinedGeneratorException;
use Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexesGenerators\IndexesGeneratorInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

class IndexesGenerator
{
    protected EncryptorInterface $encryptor;
    protected ServiceLocator $container;

    public function __construct(ServiceLocator $container, EncryptorInterface $encryptor)
    {
        $this->container = $container;
        $this->encryptor = $encryptor;
    }

    /**
     * Generates all possible search for the value
     *
     * @param string[] $methods
     *
     * @return string[]
     *
     * @throws UndefinedGeneratorException
     */
    public function generateAndEncryptFilters(string $value, array $methods): array
    {
        $possibleValuesAr = [$value];

        foreach ($methods as $method) {
            $method .= 'Generator';

            if (!$this->container->has($method)) {
                throw new UndefinedGeneratorException(sprintf("No generator found for method %s", $method));
            }

            $generator = $this->container->get($method);
            if (!$generator instanceof IndexesGeneratorInterface) {
                throw new \TypeError(sprintf("The generator is not an instance of %s", IndexesGeneratorInterface::class));
            }

            $possibleValues = $generator->generate($value);
            array_push($possibleValuesAr, ...$possibleValues);
        }

        return $possibleValuesAr;
    }

    /**
     * Generates all blind indexes for the all possible values
     *
     * @param string[] $possibleValues
     *
     * @return array<int, string>
     */
    public function generateBlindIndexesFromPossibleValues(string $entityName, string $fieldName, array $possibleValues, bool $fastIndexing): array
    {
        $possibleValues = array_unique($possibleValues);

        $indexes = [];
        foreach ($possibleValues as $pvalue) {
            if ($pvalue === '' || $pvalue === null) {
                continue;
            }
            $indexes[] = $this->encryptor->getBlindIndex($entityName, $fieldName, $pvalue, EncryptorInterface::DEFAULT_FILTER_BITS, $fastIndexing);
        }

        return $indexes;
    }
}
