<?php

declare(strict_types=1);

use Odandb\DoctrineCiphersweetEncryptionBundle\Tests\App\Kernel;
use Odandb\DoctrineCiphersweetEncryptionBundle\Tests\App\Model\MyEntityAttribute;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

require __DIR__ . '/../vendor/autoload.php';

// Clean up from previous runs
@exec('rm -rf ' . escapeshellarg(__DIR__ . '/App/var'));
@exec('mkdir ' . escapeshellarg(__DIR__ . '/App/var'));

// Create schema
$kernel = new Kernel('test', false);
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


$em = $kernel->getContainer()->get('doctrine')->getManager();
$entities = [];
for ($i = 0; $i < 4; ++$i) {
    $entities[] = $entity = new MyEntityAttribute('ODB' . $i, $i);
    $em->persist($entity);
}
$em->flush();
