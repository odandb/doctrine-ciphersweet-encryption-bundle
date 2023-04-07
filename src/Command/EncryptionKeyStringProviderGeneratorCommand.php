<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Command;

use ParagonIE\ConstantTime\Hex;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'odb:enc:generate-string-key', description: 'Generate default encryption key for StringProvider (one of the different key provider managed by Ciphersweet library).')]
class EncryptionKeyStringProviderGeneratorCommand extends Command
{
    /** @deprecated  */
    protected static $defaultName = 'odb:enc:generate-string-key';
    /** @deprecated  */
    protected static $defaultDescription = 'Generate default encryption key for StringProvider (one of the different key provider managed by Ciphersweet library).';

    protected static string $defaultAlias = 'o:e:g';

    protected function configure(): void
    {
        $this
            ->setAliases([self::$defaultAlias])
        ;
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // SI pas de mode verbose, on fait un simple write pour pouvoir
        // chainer le retour de cette commande avec d'autres commandes
        if ($input->getOption('verbose') === false) {
            $io->write(Hex::encode(random_bytes(32)));
        } else {
            // Sinon on affiche le message avec du style
            $io->success("Please use : ".Hex::encode(random_bytes(32)));
        }

        return 0;
    }
}
