<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Command;

use Odandb\DoctrineCiphersweetEncryptionBundle\Exception\MissingPropertyFromReflectionException;
use Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexableFieldsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'odb:enc:indexes', description: 'Generates matching indexes')]
class GenerateIndexesCommand extends Command
{
    /** @deprecated  */
    protected static $defaultName = self::CONSOLE_CMD;
    /** @deprecated  */
    protected static $defaultDescription = 'Generates matching indexes';

    protected static string $defaultAlias = 'o:e:i';

    protected const CONSOLE_ENTRYPOINT = 'bin/console';
    protected const CONSOLE_CMD = 'odb:enc:indexes';
    protected const NB_RUNNING_PROCESSES = 5;
    protected const CHUNCKS = 50;
    protected const SUBPROCESS_TIMEOUT = 600; // Timeout in seconds

    protected SymfonyStyle $io;

    protected IndexableFieldsService $indexableFieldsService;

    public function __construct(IndexableFieldsService $indexableFieldsService)
    {
        $this->indexableFieldsService = $indexableFieldsService;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setAliases([self::$defaultAlias])
            ->addArgument('class', InputArgument::REQUIRED, 'The entity class having fields that need a complete indexes recalculation.')

            ->addOption('fieldnames', 'f', InputOption::VALUE_OPTIONAL, 'Fieldnames (separated by commas) to target if you don\'t want to flush the entire indexes.', null)
            ->addOption('ids', 'i', InputOption::VALUE_OPTIONAL, 'List of ids (separated by commas) if you don\'t want to flush the entire indexes for the targetted class.', null)
            ->addOption('purge', 'p', InputOption::VALUE_NONE, 'Purge existing indexes ?' )

            ->addOption('parallel', 'l', InputOption::VALUE_NONE, 'Can this run be executed in parallel ?' )
            ->addOption('nb-subprocess', 's', InputOption::VALUE_REQUIRED, 'In case of parallel mode, how many sub-processes can be run.', static::NB_RUNNING_PROCESSES)
            ->addOption('subprocess-timeout', 't', InputOption::VALUE_REQUIRED, 'Timeout of subprocesses', static::SUBPROCESS_TIMEOUT)
            ->addOption('chuncks', 'c', InputOption::VALUE_REQUIRED, 'In case of parallel execution, chuncks length of entity ids', static::CHUNCKS)
        ;
    }

    /**
     * @throws MissingPropertyFromReflectionException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $className = $input->getArgument('class');

        if (class_exists($className) === false) {
            $this->io->error(sprintf('The given classname %s does not exists', $className));
            return 1;
        }

        $fieldnames = $input->getOption('fieldnames');
        $ids = $input->getOption('ids');
        $purge = $input->getOption('purge');

        if ($input->getOption('parallel')) {
            $optionsInError = $this->validateParallelOptions($input);
            if ($optionsInError !== []) {
                $this->io->error([
                    sprintf('One or more options for parallel mode are incorrect : %s', implode(', ', $optionsInError)),
                    'They must be of type int and greater than 0.'
                ]);

                return 1;
            }

            $parallelConfig = [
                'nb_process' => (int) $input->getOption('nb-subprocess'),
                'timeout' => (int) $input->getOption('subprocess-timeout'),
                'chuncks' => (int) $input->getOption('chuncks'),
            ];
            $this->initAndRunFiltersGenerationSubProcesses($className, $parallelConfig);
        } else {
            $this->regenerateFiltersByFieldnameAndIds($className, $fieldnames, $ids, $purge);
        }

        return 0;
    }

    /**
     * @return array<int, string>
     */
    protected function validateParallelOptions(InputInterface $input): array
    {
        $optionsInError = [];

        foreach (['nb-subprocess', 'subprocess-timeout', 'chuncks'] as $optionName) {
            $value = $input->getOption($optionName);
            if (!is_numeric($value) || ((int) $value) < 1) {
                $optionsInError[]=$optionName;
            }
        }

        return $optionsInError;
    }

    /**
     * @param array{nb_process: int, timeout: int, chuncks: int} $parallelConfig
     *
     * @throws MissingPropertyFromReflectionException
     */
    protected function initAndRunFiltersGenerationSubProcesses(#[\SensitiveParameter] string $className, array $parallelConfig): void
    {

        $start = time();
        $chuncks = $this->indexableFieldsService->getChunksForMultiThread($className, $parallelConfig['chuncks']);
        $pools = [];

        $contexts = $this->indexableFieldsService->buildContext($className, null);

        $this->io->comment('Purging');
        $this->indexableFieldsService->purgeFiltersForContextAndIds($contexts, null);

        $phpBinaryPath = (new PhpExecutableFinder())->find();
        $i = 0;

        foreach ($chuncks as $chunck) {
            $process = new Process([$phpBinaryPath, static::CONSOLE_ENTRYPOINT, self::CONSOLE_CMD, $className, '--ids='.implode(',', $chunck)]);
            $process->setTimeout($parallelConfig['timeout']);
            $process->setTty(Process::isTtySupported());

            $pools[]=$process;
            $process->start();

            if (++$i % $parallelConfig['nb_process'] === 0) {
                $this->runProcesses($pools);
                $pools = [];
            }
        }

        if ($pools !== []) {
            $this->runProcesses($pools);
        }

        $this->io->success('Done in ' . (time() - $start).'s');
    }

    /**
     * @param array<int, Process> $pools
     */
    private function runProcesses(array $pools): void
    {
        $finishedProcesses = [];
        $isSomethingRunning = true;
        while ($isSomethingRunning) {
            $isSomethingRunning = false;
            foreach ($pools as $key => $process) {
                if ($process->isRunning()) {
                    $isSomethingRunning = true;
                } elseif (!isset($finishedProcesses[$key]) && $process->isTerminated()) {
                    if ($process->isSuccessful()) {
                        $this->io->writeln($process->getOutput());
                    } else {
                        $this->io->error($process->getErrorOutput());
                    }
                    $finishedProcesses[$key] = true;
                }

                $process->checkTimeout();
                usleep(200000);
            }
        }
    }

    /**
     * @throws MissingPropertyFromReflectionException
     */
    protected function regenerateFiltersByFieldnameAndIds(#[\SensitiveParameter] string $className, #[\SensitiveParameter] ?string $fieldnames, ?string $ids, bool $purge = false): void
    {
        $fieldnamesAr = $fieldnames !== null ? explode(',', $fieldnames) : null;
        $idsAr = $ids !== null ? explode(',', $ids) : null;

        $contexts = $this->indexableFieldsService->buildContext($className, $fieldnamesAr);

        if ($purge) {
            $this->io->comment('Purging');
            $this->indexableFieldsService->purgeFiltersForContextAndIds($contexts, $idsAr);
        }

        $this->io->comment('Generating Indexes');
        $this->indexableFieldsService->handleFilterableFieldsForChunck($className, $idsAr, $contexts);
        if ($idsAr !== null) {
            $this->io->success(sprintf('Done for %s class and %d ids', $className, count($idsAr)));
        } else {
            $this->io->success(sprintf('Done for %s class', $className));
        }
    }
}
