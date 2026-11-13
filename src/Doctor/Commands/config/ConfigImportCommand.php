<?php

declare(strict_types=1);

namespace Drush\Doctor\Commands\config;

use Drupal\Core\Command\Exception\UserAbortException;
use Drupal\Core\Config\ConfigImporterException;
use Drupal\Core\Config\ConfigImporterFactory;
use Drupal\Core\Config\ImportStorageTransformer;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * A copy of core's \Drupal\Core\Config\Command\ConfigImportCommand (core
 * commit 5ac1f0d7b5), registered with dr only on core versions that do not
 * have that command. Remove it once they all do.
 *
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Import configuration',
    aliases: ['cim'],
)]
final class ConfigImportCommand
{
    use StringTranslationTrait;
    use ConfigCommandTrait;

    public const string NAME = 'config:import';

    public function __construct(
        #[Autowire(service: 'config.storage')]
        private readonly StorageInterface $active,
        #[Autowire(service: 'config.storage.sync')]
        private readonly StorageInterface $sync,
        private readonly ImportStorageTransformer $transformer,
        private readonly ConfigImporterFactory $importerFactory,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Answer the confirmation question with yes.', shortcut: 'y')]
        bool $yes = false,
    ): int {
        // Do the import storage transformation.
        $import = $this->transformer->transform($this->sync);

        $comparer = new StorageComparer($import, $this->active);
        $comparer->createChangelist();

        if (!$comparer->hasChanges()) {
            $io->success((string) $this->t('There are no changes to import.'));
            return Command::SUCCESS;
        }

        $this->renderTable($comparer, $io);

        if (!$yes && !$io->confirm((string) $this->t('Import the listed configuration changes?'))) {
            throw new UserAbortException('Cancelled');
        }

        $importer = $this->importerFactory->get($comparer);

        if ($importer->alreadyImporting()) {
            $io->warning((string) $this->t('Another request may be synchronizing configuration already.'));
            return Command::FAILURE;
        }

        try {
            $importer->validate();
            $importer->import();
        } catch (ConfigImporterException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $errors = $importer->getErrors();
        if (empty($errors)) {
            $io->success((string) $this->t('The configuration was imported successfully.'));
            return Command::SUCCESS;
        }

        $io->error((string) $this->t('The configuration was imported with errors.'));
        foreach ($errors as $error) {
            $io->error((string) $error);
        }

        return Command::FAILURE;
    }
}
