<?php

declare(strict_types=1);


namespace Odandb\DoctrineCiphersweetEncryptionBundle\Command;


use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors\EncryptorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'odb:enc:data', description: 'Encrypts or Decrypt data manually.')]
class EncryptionDataCommand extends Command
{
    /** @deprecated  */
    protected static $defaultName = 'odb:enc:data';

    /** @deprecated */
    protected static $defaultDescription = 'Encrypts or Decrypt data manually.';

    protected static string $defaultAlias = 'o:e:d';

    private EntityManagerInterface $entityManager;

    private EncryptorInterface $encryptor;

    public function __construct(EntityManagerInterface $entityManager, EncryptorInterface $encryptor)
    {
        $this->entityManager = $entityManager;
        $this->encryptor = $encryptor;

        parent::__construct();
    }

    public function configure(): void
    {
        $this
            ->setAliases([self::$defaultAlias])
            ->addOption('encrypt', null, InputOption::VALUE_NONE, 'Encrypt data')
            ->addOption('decrypt', null, InputOption::VALUE_NONE, 'Decrypt data')
            ->addOption('blind', 'b', InputOption::VALUE_OPTIONAL, 'Get the blind index on the encrypt process')
            ->addArgument('class', InputArgument::OPTIONAL, 'Class name of the entity')
            ->addArgument('field', InputArgument::OPTIONAL, 'Field name of the entity')
            ->addArgument('value', InputArgument::OPTIONAL, 'Value of the entity');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($input->getOption('encrypt')) {
            $encryptDecrypt = 'encrypt';
        } elseif ($input->getOption('decrypt')) {
            $encryptDecrypt = 'decrypt';
        } else {
            $encryptDecrypt = $io->choice('Do you want to encrypt or decrypt data?', ['encrypt', 'decrypt']);
        }

        $kernel = $this->getApplication()->getKernel();
        $io->comment(sprintf('<info>%s</info> data for the <info>%s</info> environment', ucfirst($encryptDecrypt), $kernel->getEnvironment()));

        $metas = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $metaData = [];
        foreach ($metas as $meta) {
            $metaData[$meta->getName()] = $meta;
        }

        $className = $input->getArgument('class');
        if ($className !== null && !class_exists($className)) {
            $io->error(sprintf('The class %s does not exist', $className));
            $className = null;
        }

        if ($className === null) {
            $className = $this->askClassName($metaData, $input, $output);
        }

        $fieldName = $input->getArgument('field');
        if ($fieldName !== null && !property_exists($className, $fieldName)) {
            $io->error(sprintf('The field %s does not exist', $fieldName));
            $fieldName = null;
        }

        if($fieldName === null) {
            $fieldName = $this->askFieldName($metaData[$className], $input, $output);
        }

        $blind = [];
        if ($encryptDecrypt === 'encrypt') {
            $value = $input->getArgument('value') ?? $io->ask('What is the value of the entity you want to encrypt ?');
            [$result, $blind] = $this->encryptor->prepareForStorage((new \ReflectionClass($className))->newInstanceWithoutConstructor(), $fieldName, $value, (bool) $input->getOption('blind'));
        } else {
            $value = $input->getArgument('value') ?? $io->ask('What is the value of the entity you want to decrypt ?');
            $result = $this->encryptor->decrypt($className, $fieldName, $value);
        }

        $io->success(sprintf('Result: [%s]', $result));
        if (!empty($blind)) {
            $io->success(sprintf('Blind: [%s]', implode(', ', $blind)));
        }

        return Command::SUCCESS;
    }

    private function askClassName(array $metaData, InputInterface $input, OutputInterface $output): string
    {
        $io = new SymfonyStyle($input, $output);
        $question = new Question('Please enter an entity className'. PHP_EOL );
        $question->setAutocompleterValues(array_keys($metaData));
        $io->newLine();

        $question->setNormalizer(static function ($value) use ($metaData) {
            $answer = $metaData[$value] ?? null;

            return $answer instanceof ClassMetadata ? $answer->getName() : null;
        });

        $question->setValidator(static function (?string $answer) {
            if (null === $answer) {
                throw new \RuntimeException(
                    'The className does not exists nor has encrypted fields in its definition.'
                );
            }

            return $answer;
        });
        $question->setMaxAttempts(2);

        $helper = $this->getHelper('question');
        return $helper->ask($input, $output, $question);
    }

    private function askFieldName(ClassMetadata $metaData, InputInterface $input, OutputInterface $output): ?string
    {
        $io = new SymfonyStyle($input, $output);
        $question = new Question('Please enter an existing property of the className'. PHP_EOL );
        $question->setAutocompleterValues(
            array_map(
                static function (\ReflectionProperty $property) {return $property->getName();},
                $metaData->getReflectionClass()->getProperties())
        );
        $io->newLine();

        $question->setValidator(static function ($answer) use ($metaData) {
            if ($answer === null) {
                throw new \RuntimeException(
                    'The fieldname is mandatory.'
                );
            }
            try {
                $metaData->getReflectionClass()->getProperty($answer);
            } catch (\ReflectionException $e) {
                throw new \RuntimeException(
                    'The fieldname does not exists.'
                );
            }

            return $answer;
        });
        $question->setMaxAttempts(2);

        $helper = $this->getHelper('question');
        return $helper->ask($input, $output, $question);
    }
}
