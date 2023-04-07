<?php

declare(strict_types=1);

if (isset($_ENV['BOOTSTRAP_CLEAR_CACHE_ENV'])) {
    // executes the "php bin/console cache:clear" command
    passthru(sprintf(
        'APP_ENV=%s php "%s/App/bin/console" cache:clear --no-warmup --quiet',
        $_ENV['BOOTSTRAP_CLEAR_CACHE_ENV'],
        __DIR__
    ));
}

use Odandb\DoctrineCiphersweetEncryptionBundle\Tests\App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

require __DIR__.'/App/config/bootstrap.php';


$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$output = new ConsoleOutput();
$application = new Application($kernel);
$application->setAutoExit(false);
$application->setCatchExceptions(false);

$runCommand = static function (string $name, array $options = []) use ($application): void {
    $input = new ArrayInput(array_merge(['command' => $name, '--env' => 'test'], $options));
    $input->setInteractive(false);
    $application->run($input);
};

$runCommand('doctrine:database:create', []);
$runCommand('doctrine:schema:drop', [
    '--force'         => true,
    '--full-database' => true,
]);
$runCommand('doctrine:schema:create', []);
