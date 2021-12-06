<?php

declare(strict_types=1);


namespace Odandb\DoctrineCiphersweetEncryptionBundle\Services;


use Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors\EncryptorInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Exception\UndefinedGeneratorException;
use Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexesGenerators\IndexesGeneratorInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexesGenerators\ValueStartingByGenerator;
use Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexesGenerators\ValueEndingByGenerator;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class IndexesGenerator implements ServiceSubscriberInterface
{
    protected EncryptorInterface $encryptor;
    protected ContainerInterface $container;

    public function __construct(ContainerInterface $container, EncryptorInterface $encryptor)
    {
        $this->container = $container;
        $this->encryptor = $encryptor;
    }

    /**
     * @required
     */
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public static function getSubscribedServices(): array
    {
        return [
            'ValueStartingByGenerator' => '?'.ValueStartingByGenerator::class,
            'ValueEndingByGenerator' => '?'.ValueEndingByGenerator::class,
        ];
    }

    public function generateAndEncryptFilters(string $value, array $methods): array
    {
        $possibleValuesAr = [$value];

        foreach ($methods as $method) {
            $method .= 'Generator';

            if (!$this->container->has($method)) {
                throw new UndefinedGeneratorException(sprintf("No generator found for method %s", $method));
            }

            $generator = $this->container->get($method);
            if ($generator instanceof IndexesGeneratorInterface === false) {
                throw new \TypeError(sprintf("The generator is not an instance of %s", IndexesGeneratorInterface::class));
            }

            $possibleValues = $generator->generate($value);
            array_push($possibleValuesAr, ...$possibleValues);
        }

        return $possibleValuesAr;
    }

    /**
     * @param string $entityName
     * @param string $fieldname
     * @param string[] $possibleValues
     * @param bool $fastIndexing
     * @return array
     */
    public function generateBlindIndexesFromPossibleValues(string $entityName, string $fieldname, array $possibleValues, bool $fastIndexing): array
    {
        $possibleValues = array_unique($possibleValues);

        $indexes = [];
        foreach ($possibleValues as $pvalue) {
            if ($pvalue === '' || $pvalue === null) {
                continue;
            }
            $indexes[] = $this->encryptor->getBlindIndex($entityName, $fieldname, $pvalue, EncryptorInterface::DEFAULT_FILTER_BITS, $fastIndexing);
        }

        return $indexes;
    }
}
