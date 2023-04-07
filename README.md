# Doctrine Ciphersweet Encryption Bundle

## Introduction

This bundle aims to make life easier to developers who want to set encrypted fields in their entities thanks to Ciphersweet library.
This bundle is inspired from the talk given at Afup ForumPHP : [REX sur le chiffrement de base de données](https://afup.org/talks/3455-rex-sur-le-chiffrement-de-base-de-donnees).
We also used the WIP public repository : https://github.com/PhilETaylor/doctrine-ciphersweet.

## Install

### Step 1: Download the Bundle

Add this repositories section on your composer.json project.

```composer
"repositories": [
    {
        "type": "vcs",
        "url": "git@github.com:odandb/doctrine-ciphersweet-encryption-bundle.git"
    }
],
```

And run

```console
composer require odandb/doctrine-ciphersweet-encryption-bundle
```

### Step 2: Enable the Bundle

```php
// config/bundle.php

return [
    // ...
    Odandb\DoctrineCiphersweetEncryptionBundle\OdandbDoctrineCiphersweetEncryptionBundle::class => ['all' => true]
];
```

## Usage

### 1. Setup DOCTRINE_CIPHERSWEET_KEY environment key

This bundle comes with several commands and annotations/attributes but first of all, you'll need to setup the `DOCTRINE_CIPHERSWEET_KEY` secret environment key.
First of all, you can just init a random one in your environment file.

```php
// .env

DOCTRINE_CIPHERSWEET_KEY=
```

Next you can generate your final key with this command : 
```console
php bin/console odb:enc:generate-string-key
```

You can even chain it with the [Symfony's secrets manager](https://symfony.com/doc/current/configuration/secrets.html) like this : 
```console
php bin/console odb:enc:generate-string-key | php bin/console secrets:set DOCTRINE_CIPHERSWEET_KEY -
```
Then remove the entry in your .env file

### 2. Add annotations / attributes to your entities

This bundle comes with 2 annotations / attributes in order to set encryption fields :

- **EncryptedField** : Marks the field as encrypted and will automatically use Ciphersweet library to encrypt/decrypt on onFlush and onLoad events
- **IndexableField** : Marks the field as searchable and several indexes can be generated in a separate table in order to search by terms starting by or ending by.

#### EncrytedField
This annotation/attribute comes with 3 options:

- (int) $filterBits : Number of bits used for encryption. (Default : `32`)
- (bool) $indexable : Activate a default index for exact search apart of the **IndexableField** annotation. If true, will try to set **index** string data into a field named with a "_bi" suffix. (Default : `true`)
- (string) $mappedTypedProperty: If you want to encrypt data other than strings (other types currenty supported : int, float, bool), you'll need to set a string field used by doctrine for persistance purpose instead of your raw field. Then use this parameter to automatically hydrate decrypted data into the target field. (Default : `null`) 

#### IndexableField
This annotation/attribute comes with 4 options:

- (string) $indexesEntityClass : Name of the entity class that will store the indexes (can be mutualized)
- (bool) $autoRefresh : Automatically regenerate related indexes to an entity upon persist or update event. (Default : `true`)
- (array) $indexesGenerationMethods : List of methods used to generate several searchable values from the original one. For example the `ValueStartingByGenerator` can take the value "abcdef" in order to generate indexes for ["a", "ab", "abc', "abcd", ...]. So that you can search entities with a field starting by those values. (Default : `[]`)
- (string) $valuePreprocessMethod : Before indexes generation, you may need to clean your input in order to reduce the number of indexes to generate (trim value, slug it, etc.). You can do it by setting this option. For the moment, the method mention can only by related to the current entity class. (Default : `null`)
- (bool) $fastIndexing : If true, will use a faster indexing method. (Default : `true`)

### 3. Generating indexes

To make the entities searchable, the library provides a feature called "Blind Index" which is a unique index calculated from the original value.
By default, we provide a default index field to every encrypted ones (using the $indexable option). If you need to setup a search of values starting by a term, you'll need the `IndexableField` annotation/attribute and set a dedicated indexes table.
This dedicated entity must implement the `Odandb\DoctrineCiphersweetEncryptionBundle\Entity\IndexedEntityInterface` and you can use the `Odandb\DoctrineCiphersweetEncryptionBundle\Entity\IndexedEntityTrait` to make your life easier.

Basically, this table will be composed of those columns :
- id
- fieldname
- targetEntityId
- indexBi

##### You must be careful with the indexation process :
- if you generate to many indexes for a given entity, this may expose the security of your system because hackers may be able to guess encrypted data
- the more indexes you need to generate, the longer it will take

##### How to generate indexes while not using $autoRefresh option :
The bundle comes with a Symfony indexes generation command :

```console
php bin/console odb:enc:indexes <className>
```

The classname to enter is the original entity classname, not the indexes one. If we refer to the full example section, it would be :
```console
php bin/console odb:enc:indexes App\Repository\MySecretEntity
```

If you need to speedup things (in case of high volume), you can even activate a parallel mode :
```console
php bin/console odb:enc:indexes App\Repository\MySecretEntity -l
```
In this mode, the command will split the work in smaller chuncks and start subprocesses for each one.
Refer to the command for more informations.

### 4. Here is a full example : 

```php
#[ORM\Entity(repositoryClass: App\Repository\MySecretEntityRepository::class)
#[ORM\Index(name: 'anum_blind_idx', columns: ['account_number_bi'])]
class MySecretEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 36)]
    private string $uuid;

    #[ORM\Column(type: 'string')]
    #[EncryptedField]
    #[IndexableField(indexesEntityClass: App\Entity\MySecretEntityIndexes::class, autoRefresh: false, indexesGenerationMethods: ['ValueStartingBy'], valuePreprocessMethod: 'cleanAccountNumber')]
    private string $accountNumber;

    #[ORM\Column(type: 'string', length: 10)]
    private string $accountNumberBi;
    
    private int $secretNumber;
    
    #[ORM\Column(type: 'string')]
    #[EncryptedField(mappedTypedProperty: 'secretNumber', indexable: false)]
    private string $secretNumberEncrypted;
    
    /**
     * @var Collection|null
     */
    #[ORM\OneToMany(targetEntity: MySecretEntityIndexes::class, mappedBy: 'targetEntity', cascade: ['persist'])]
    private ?Collection $indexes;
    
    /**
     * @param string $value
     * @return string
     */
    public function cleanAccountNumber(string $value): string
    {
        $value = trim($value);
        $value = ltrim($value, '0');

        return $value;
    }
    
    public function setMySecretEntityIndexes(array $indexes): self
    {
        $this->indexes = new ArrayCollection($indexes);
        return $this;
    }
    
    // ...
}

use Odandb\DoctrineCiphersweetEncryptionBundle\Entity\IndexedEntityInterface;
use Odandb\DoctrineCiphersweetEncryptionBundle\Entity\IndexedEntityTrait;

/**
 * Class storing indexes for MySecretEntity.
 */
#[ORM\Entity(repositoryClass: App\Repository\MySecretEntityIndexesRepository::class)
#[ORM\Index(name: 'blind_idx', columns: ['index_bi'])]
#[ORM\Index(name: 'field_and_blind_idx', columns: ['fieldname', 'index_bi'])]
class MySecretEntityIndexes implements IndexedEntityInterface
{
    use IndexedEntityTrait;

    /**
     * @var MySecretEntity|null
     */
    #[ORM\ManyToOne(targetEntity: App\Entity\MySecretEntity::class, inversedBy: 'indexes')]
    #[ORM\JoinColumn(name: 'target_entity_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    protected object $targetEntity;
}
```
