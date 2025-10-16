<?php

declare(strict_types=1);

namespace Drush\Commands\config;

use Drupal\config\StorageReplaceDataWrapper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigException;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\ImportStorageTransformer;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Style\DrushStyle;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Import config from the config directory.',
    aliases: ['cim', 'config-import'],
)]
#[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
#[CLI\HelpLinks(links: [HelpLinks::Deploy])]
final class ConfigImportCommand extends Command
{
    use AutowireTrait;
    use ConfigTrait;

    public const NAME = 'config:import';

    public function __construct(
        protected readonly ConfigManagerInterface $configManager,
        #[Autowire(service: 'config.storage')]
        protected readonly StorageInterface $configStorage,
        #[Autowire(service: 'cache.config')]
        protected readonly CacheBackendInterface $configCache,
        protected readonly ModuleHandlerInterface $moduleHandler,
        protected readonly EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: 'lock')]
        protected readonly LockBackendInterface $lock,
        protected readonly TypedConfigManagerInterface $configTyped,
        protected readonly ModuleInstallerInterface $moduleInstaller,
        protected readonly ThemeHandlerInterface $themeHandler,
        protected readonly TranslationInterface $stringTranslation,
        protected readonly ModuleExtensionList $moduleExtensionList,
        protected readonly ThemeExtensionList $themeExtensionList,
        #[Autowire(service: 'config.storage.sync')]
        protected ?StorageInterface $configStorageSync,
        protected ?ImportStorageTransformer $importStorageTransformer,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('diff', null, InputOption::VALUE_NONE, 'Show preview as a diff.')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'An arbitrary directory that holds the configuration files.')
            ->addOption('partial', null, InputOption::VALUE_NONE, 'Allows for partial config imports from the source directory. Only updates and new configs will be processed with this flag (missing configs will not be deleted). No config transformation happens.')
            ->addUsage('config:import --partial --source=/app/config')
            ->addUsage('cat tmp.yml | drush config:set --input-format=yaml user.mail ? -');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);

        // Validate options
        $this->validateOptions($input);

        // Determine source directory.
        $source_storage_dir = $this->getDirectory($input->getOption('source'));

        // Prepare the configuration storage for the import.
        if ($source_storage_dir === Path::canonicalize(Settings::get('config_sync_directory'))) {
            $source_storage = $this->configStorageSync;
        } else {
            $source_storage = new FileStorage($source_storage_dir);
        }

        // Determine $source_storage in partial case.
        $active_storage = $this->configStorage;
        if ($input->getOption('partial')) {
            $replacement_storage = new StorageReplaceDataWrapper($active_storage);
            foreach ($source_storage->listAll() as $name) {
                $data = $source_storage->read($name);
                $replacement_storage->replaceData($name, $data);
            }
            $source_storage = $replacement_storage;
        } elseif ($this->importStorageTransformer) {
            // Use the import transformer if it is available. (Drupal ^8.8)
            // Drupal core does not apply transformations for single imports.
            // And in addition the StorageReplaceDataWrapper is not compatible
            // with StorageCopyTrait::replaceStorageContents.
            $source_storage = $this->importStorageTransformer->transform($source_storage);
        }

        $storage_comparer = new StorageComparer($source_storage, $active_storage);
        if (!$storage_comparer->createChangelist()->hasChanges()) {
            $this->logger->notice('There are no changes to import.');
            return self::SUCCESS;
        }

        if (!$input->getOption('diff')) {
            $change_list = [];
            foreach ($storage_comparer->getAllCollectionNames() as $collection) {
                $change_list[$collection] = $storage_comparer->getChangelist(null, $collection);
            }
            $table = $this->configChangesTable($change_list, $output);
            $table->render();
        } else {
            $diff_output = $this->getDiff($active_storage, $source_storage, $output);
            $output->writeln($diff_output);
        }

        if (!$io->confirm('Import the listed configuration changes?')) {
            throw new \Exception('Import cancelled by user.');
        }

        $this->performImport($storage_comparer);
        return self::SUCCESS;
    }

    private function validateOptions(InputInterface $input): void
    {
        $msgs = [];

        if ($input->getOption('partial') && !$this->moduleHandler->moduleExists('config')) {
            $msgs[] = 'Enable the config module in order to use the --partial option.';
        }

        if ($source = $input->getOption('source')) {
            if (!file_exists($source)) {
                $msgs[] = 'The source directory does not exist.';
            }
            if (!is_dir($source)) {
                $msgs[] = 'The source is not a directory.';
            }
        }

        if ($msgs) {
            throw new \Exception(implode(' ', $msgs));
        }
    }

    // Copied from submitForm() at /core/modules/config/src/Form/ConfigSync.php
    private function performImport(StorageComparer $storage_comparer): void
    {
        $config_importer = new ConfigImporter(
            $storage_comparer,
            $this->eventDispatcher,
            $this->configManager,
            $this->lock,
            $this->configTyped,
            $this->moduleHandler,
            $this->moduleInstaller,
            $this->themeHandler,
            $this->stringTranslation,
            $this->moduleExtensionList,
            $this->themeExtensionList
        );

        if ($config_importer->alreadyImporting()) {
            $this->logger->warning('Another request may be synchronizing configuration already.');
        } else {
            try {
                // This is the contents of \Drupal\Core\Config\ConfigImporter::import.
                // Copied here so we can log progress.
                if ($config_importer->hasUnprocessedConfigurationChanges()) {
                    $sync_steps = $config_importer->initialize();
                    foreach ($sync_steps as $step) {
                        $context = [];
                        do {
                            $config_importer->doSyncStep($step, $context);
                            if (isset($context['message'])) {
                                $this->logger->notice(
                                    str_replace('Synchronizing', 'Synchronized', (string)$context['message'])
                                );
                            }
                        } while ($context['finished'] < 1);
                    }
                    // Clear the cache of the active config storage.
                    $this->configCache->deleteAll();
                }

                if ($config_importer->getErrors()) {
                    throw new ConfigException('Errors occurred during import');
                }

                $this->logger->notice('The configuration was imported successfully.');
            } catch (ConfigException $e) {
                // Return a negative result for UI purposes. We do not differentiate
                // between an actual synchronization error and a failed lock, because
                // concurrent synchronizations are an edge-case happening only when
                // multiple developers or site builders attempt to do it without
                // coordinating.
                $message = 'The import failed due to the following reasons:' . "\n";
                $message .= implode("\n", $config_importer->getErrors());

                throw new \Exception($message, $e->getCode(), $e);
            } finally {
                // Importing config might trigger batch operations (such as when installing and uninstalling modules).
                // @see \Drush\Commands\pm\PmCommands::install()
                if (batch_get()) {
                    $this->logger->notice('Running batch operations...');
                    drush_backend_batch_process();
                }
            }
        }
    }
}
