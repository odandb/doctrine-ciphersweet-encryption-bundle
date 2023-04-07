<?php

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Odandb\DoctrineCiphersweetEncryptionBundle\Services\EncryptedFieldsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'odb:enc:check', description: 'Command to check encrypted data is not corrupted.')]
class CheckEncryptionCommand extends Command
{
    /** @deprecated  */
    protected static $defaultName = 'odb:enc:check';
    /** @deprecated  */
    protected static $defaultDescription = 'Command to check encrypted data is not corrupted.';

    protected static string $defaultAlias = 'o:e:c';

    private EntityManagerInterface $entityManager;

    private EncryptedFieldsService $encryptedFieldsService;

    public function __construct(EntityManagerInterface $entityManager, EncryptedFieldsService $encryptedFieldsService)
    {
        $this->entityManager = $entityManager;
        $this->encryptedFieldsService = $encryptedFieldsService;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setAliases([self::$defaultAlias])
            ->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Interactive mode. It will ask you to choose an entity class to check')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $metas = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $metaToCheck = [];
        foreach ($metas as $meta) {
            if ($this->encryptedFieldsService->getEncryptedFields($meta) !== []) {
                $metaToCheck[$meta->getName()] = $meta;
            }
        }

        if ($input->getOption('interactive')) {
            $question = new Question('Please enter an entity className'. PHP_EOL );
            $question->setAutocompleterValues(array_keys($metaToCheck));
            $io->newLine();

            $question->setNormalizer(static function ($value) use ($metaToCheck) {
                // $value can be null here
                return $metaToCheck[$value] ?? null;
            });

            $question->setValidator(static function ($answer) use ($metaToCheck) {
                if ($answer instanceof ClassMetadata === false) {
                    throw new \RuntimeException(
                        'The className does not exists nor has encrypted fields in its definition.'
                    );
                }

                return $answer;
            });
            $question->setMaxAttempts(2);

            $helper = $this->getHelper('question');
            $metaClassName = $helper->ask($input, $output, $question);
            if (!$this->checkEncryption($io, $metaClassName)) {
                return Command::FAILURE;
            }

        } else {
            foreach ($metaToCheck as $meta) {
                if (!$this->checkEncryption($io, $meta)) {
                    return Command::FAILURE;
                }
            }
        }

        return Command::SUCCESS;
    }

    private function checkEncryption(SymfonyStyle $io, ClassMetadata $classMetadata): bool
    {
        $progress = $io->createProgressBar(
            $this->entityManager->createQueryBuilder()->select('count(a)')->from($classMetadata->getName(), 'a')->getQuery()->getSingleScalarResult()
        );

        $io->title(sprintf('Check encryption for %s', $classMetadata->getName()));
        $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s% -- [n°%id%]');
        $progress->start();
        $previousIdentifier = null;
        try {
            foreach ($this->entityManager->createQueryBuilder()
                         ->select('a')->from($classMetadata->getName(), 'a')
                         ->getQuery()->toIterable() as $item) {
                $identifierValue = $this->getIdentifierValue($classMetadata, $item);
                $progress->setMessage($identifierValue, 'id');
                $progress->advance();
                $previousIdentifier = $identifierValue;
            }

            $progress->finish();

            $io->newLine();
            $io->success('Done!');
        } catch (\Throwable $e) {
            $progress->finish();

            $io->newLine();
            $io->error($e->getMessage());

            if (null !== $previousIdentifier) {
                $io->error(sprintf('Previous item : %s [%d]', $classMetadata->getName(), $previousIdentifier));
            }

            return false;
        }

        return true;
    }

    private function getIdentifierValue(ClassMetadata $classMetadata, $item): string
    {
        $identifierValues = $classMetadata->getIdentifierValues($item);
        if ($identifierValues === []) {
            return '';
        }

        return (string) array_values($identifierValues)[0];
    }
}
