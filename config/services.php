<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;


use Doctrine\ORM\EntityManagerInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Command\CheckEncryptionCommand;
use Odandb\DoctrineCiphersweetEncryptionBundle\Command\EncryptionDataCommand;
use Odandb\DoctrineCiphersweetEncryptionBundle\Command\EncryptionKeyStringProviderGeneratorCommand;
use Odandb\DoctrineCiphersweetEncryptionBundle\Command\FieldIndexPlannerCommand;
use Odandb\DoctrineCiphersweetEncryptionBundle\Command\GenerateIndexesCommand;
use Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors\CiphersweetEncryptor;
use Odandb\DoctrineCiphersweetEncryptionBundle\Encryptors\EncryptorInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Services\EncryptedFieldsService;
use Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexableFieldsService;
use Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexesGenerator;
use Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexesGenerators\IndexesGeneratorInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexesGenerators\TokenizerGenerator;
use Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexesGenerators\ValueEndingByGenerator;
use Odandb\DoctrineCiphersweetEncryptionBundle\Services\IndexesGenerators\ValueStartingByGenerator;
use Odandb\DoctrineCiphersweetEncryptionBundle\Services\PropertyHydratorService;
use Odandb\DoctrineCiphersweetEncryptionBundle\Subscribers\DoctrineCiphersweetSubscriber;
use ParagonIE\CipherSweet\CipherSweet;
use ParagonIE\CipherSweet\KeyProvider\StringProvider;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->instanceof(IndexesGeneratorInterface::class)
            ->tag('encryption.index_generator')

        // Paragon
        ->set('encryption.paragon.string_provider', StringProvider::class)
            ->args([
                env('DOCTRINE_CIPHERSWEET_KEY')
            ])
        ->set('encryption.paragon.cipher_sweet', CipherSweet::class)
            ->args([
                service('encryption.paragon.string_provider')
            ])

        // Command
        ->set('encryption.console.check_encryption', CheckEncryptionCommand::class)
            ->args([
                service(EntityManagerInterface::class),
                service('encryption.encrypted_fields')
            ])
            ->tag('console.command')
        ->set('encryption.console.encryption_data', EncryptionDataCommand::class)
            ->args([
                service(EntityManagerInterface::class),
                service(EncryptorInterface::class)
            ])
            ->tag('console.command')
        ->set('encryption.console.key_string_provider_generator', EncryptionKeyStringProviderGeneratorCommand::class)
            ->tag('console.command')
        ->set('encryption.console.field_index_planner', FieldIndexPlannerCommand::class)
            ->tag('console.command')
        ->set('encryption.console.generate_indexes', GenerateIndexesCommand::class)
            ->args([
                service('encryption.indexable_field')
            ])
            ->tag('console.command')

        // Encryptors
        ->set('encryption.encryptor.cipher_sweet', CiphersweetEncryptor::class)
            ->args([
                service('encryption.paragon.cipher_sweet')
            ])
        ->alias(EncryptorInterface::class, 'encryption.encryptor.cipher_sweet')

        // Indexes Generators
        ->set('encryption.indexes_generator', IndexesGenerator::class)
            ->public()
            ->args([
                abstract_arg('All services with tag "encryption.index_generator" are stored in a service locator by IndexGeneratorPass'),
                service(EncryptorInterface::class)
            ])
        ->set('encryption.indexes_generator.tokenizer', TokenizerGenerator::class)
            ->tag('encryption.index_generator', ['key' => 'TokenizerGenerator'])
        ->set('encryption.indexes_generator.value_starting_by', ValueStartingByGenerator::class)
            ->tag('encryption.index_generator', ['key' => 'ValueStartingByGenerator'])
        ->set('encryption.indexes_generator.value_ending_by', ValueEndingByGenerator::class)
            ->tag('encryption.index_generator', ['key' => 'ValueEndingByGenerator'])

        ->set('encryption.indexable_field', IndexableFieldsService::class)
            ->args([
                service('annotation_reader')->nullOnInvalid(), // @deprecated
                service(EntityManagerInterface::class),
                service('encryption.indexes_generator'),
                service('property_accessor')
            ])
        ->alias(IndexableFieldsService::class, 'encryption.indexable_field')

        // Property
        ->set('encryption.property_hydrator', PropertyHydratorService::class)
            ->args([
                service('property_info'),
                service('property_accessor')
            ])

        ->set('encryption.subscriber', DoctrineCiphersweetSubscriber::class)
            ->args([
                service('annotation_reader')->nullOnInvalid(), // @deprecated
                service('encryption.encrypted_fields'),
                service(EncryptorInterface::class),
                service('encryption.indexable_field'),
                service('encryption.property_hydrator')
            ])
            ->tag('doctrine.event_listener', ['event' => 'postLoad'])
            ->tag('doctrine.event_listener', ['event' => 'onFlush'])
            ->tag('doctrine.event_listener', ['event' => 'postFlush'])
            ->tag('doctrine.event_listener', ['event' => 'onClear'])

        ->set('encryption.encrypted_fields', EncryptedFieldsService::class)
            ->args([
                service('annotation_reader')->nullOnInvalid(), // @deprecated
            ])
    ;
};
