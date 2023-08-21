<?php

declare(strict_types=1);

namespace Drush\Commands\config;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\core\DocsCommands;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Drush\Attributes as CLI;
use Drupal\Core\Config\ImportStorageTransformer;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Drupal\config\StorageReplaceDataWrapper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigException;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ConfigImportCommands extends DrushCommands
{
    const IMPORT = 'config:import';
    protected ?StorageInterface $configStorageSync;
    protected ?ImportStorageTransformer $importStorageTransformer;

    public function getConfigManager(): ConfigManagerInterface
    {
        return $this->configManager;
    }

    public function getConfigStorage(): StorageInterface
    {
        return $this->configStorage;
    }

    public function getConfigStorageSync(): StorageInterface
    {
        return $this->configStorageSync;
    }

    public function setConfigStorageSync(?StorageInterface $syncStorage): void
    {
        $this->configStorageSync = $syncStorage;
    }

    public function getConfigCache(): CacheBackendInterface
    {
        return $this->configCache;
    }

    public function getModuleHandler(): ModuleHandlerInterface
    {
        return $this->moduleHandler;
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    public function getLock(): LockBackendInterface
    {
        return $this->lock;
    }

    public function getConfigTyped(): TypedConfigManagerInterface
    {
        return $this->configTyped;
    }

    public function getModuleInstaller(): ModuleInstallerInterface
    {
        return $this->moduleInstaller;
    }

    public function getThemeHandler(): ThemeHandlerInterface
    {
        return $this->themeHandler;
    }

    public function getStringTranslation(): TranslationInterface
    {
        return $this->stringTranslation;
    }

    public function setImportTransformer(ImportStorageTransformer $importStorageTransformer): void
    {
        $this->importStorageTransformer = $importStorageTransformer;
    }

    public function hasImportTransformer(): bool
    {
        return isset($this->importStorageTransformer);
    }

    public function getImportTransformer(): ?ImportStorageTransformer
    {
        return $this->importStorageTransformer;
    }

    public function getModuleExtensionList(): ModuleExtensionList
    {
        return $this->moduleExtensionList;
    }

    public function __construct(
        protected ConfigManagerInterface $configManager,
        protected StorageInterface $configStorage,
        protected CacheBackendInterface $configCache,
        protected ModuleHandlerInterface $moduleHandler,
        protected EventDispatcherInterface $eventDispatcher,
        protected LockBackendInterface $lock,
        protected TypedConfigManagerInterface $configTyped,
        protected ModuleInstallerInterface $moduleInstaller,
        protected ThemeHandlerInterface $themeHandler,
        protected TranslationInterface $stringTranslation,
        protected ModuleExtensionList $moduleExtensionList
    ) {
        parent::__construct();
    }

    public static function create(ContainerInterface $container): self
    {
        $commandHandler = new static(
            $container->get('config.manager'),
            $container->get('config.storage'),
            $container->get('cache.config'),
            $container->get('module_handler'),
            $container->get('event_dispatcher'),
            $container->get('lock'),
            $container->get('config.typed'),
            $container->get('module_installer'),
            $container->get('theme_handler'),
            $container->get('string_translation'),
            $container->get('extension.list.module'),
        );

        if ($container->has('config.import_transformer')) {
            $commandHandler->setImportTransformer($container->get('config.import_transformer'));
        }
        if ($container->has('config.storage.sync')) {
            $commandHandler->setConfigStorageSync($container->get('config.storage.sync'));
        }

        return $commandHandler;
    }

    /**
     * Import config from the config directory.
     */
    #[CLI\Command(name: self::IMPORT, aliases: ['cim', 'config-import'])]
    #[CLI\Option(name: 'diff', description: 'Show preview as a diff.')]
    #[CLI\Option(name: 'source', description: 'An arbitrary directory that holds the configuration files.')]
    #[CLI\Option(name: 'partial', description: 'Allows for partial config imports from the source directory. Only updates and new configs will be processed with this flag (missing configs will not be deleted). No config transformation happens.')]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    #[CLI\Usage(name: 'drush config:import', description: 'Update Drupal\'s configuration so it matches the contents of the config directory.')]
    #[CLI\Usage(name: 'drush config:import --partial --source=/app/config', description: 'Import from the /app/config directory which typically contains one or a few yaml files.')]
    #[CLI\Usage(name: 'cat tmp.yml | drush config:set --input-format=yaml user.mail ? -', description: 'Update the <info>user.mail</info> config object in its entirety.')]
    #[CLI\Topics(topics: [DocsCommands::DEPLOY])]
    public function import(array $options = ['source' => self::REQ, 'partial' => false, 'diff' => false])
    {
        // Determine source directory.
        $source_storage_dir = ConfigCommands::getDirectory($options['source']);

        // Prepare the configuration storage for the import.
        if ($source_storage_dir === Path::canonicalize(Settings::get('config_sync_directory'))) {
            $source_storage = $this->getConfigStorageSync();
        } else {
            $source_storage = new FileStorage($source_storage_dir);
        }

        // Determine $source_storage in partial case.
        $active_storage = $this->getConfigStorage();
        if ($options['partial']) {
            $replacement_storage = new StorageReplaceDataWrapper($active_storage);
            foreach ($source_storage->listAll() as $name) {
                $data = $source_storage->read($name);
                $replacement_storage->replaceData($name, $data);
            }
            $source_storage = $replacement_storage;
        } elseif ($this->hasImportTransformer()) {
            // Use the import transformer if it is available. (Drupal ^8.8)
            // Drupal core does not apply transformations for single imports.
            // And in addition the StorageReplaceDataWrapper is not compatible
            // with StorageCopyTrait::replaceStorageContents.
            $source_storage = $this->getImportTransformer()->transform($source_storage);
        }

        $config_manager = $this->getConfigManager();
        $storage_comparer = new StorageComparer($source_storage, $active_storage, $config_manager);


        if (!$storage_comparer->createChangelist()->hasChanges()) {
            $this->logger()->notice(('There are no changes to import.'));
            return;
        }

        if (!$options['diff']) {
            $change_list = [];
            foreach ($storage_comparer->getAllCollectionNames() as $collection) {
                $change_list[$collection] = $storage_comparer->getChangelist(null, $collection);
            }
            $table = ConfigCommands::configChangesTable($change_list, $this->output());
            $table->render();
        } else {
            $output = ConfigCommands::getDiff($active_storage, $source_storage, $this->output());

            $this->output()->writeln($output);
        }

        if (!$this->io()->confirm(dt('Import the listed configuration changes?'))) {
            throw new UserAbortException();
        }
        return drush_op([$this, 'doImport'], $storage_comparer);
    }

    // Copied from submitForm() at /core/modules/config/src/Form/ConfigSync.php
    public function doImport($storage_comparer): void
    {
        $config_importer = new ConfigImporter(
            $storage_comparer,
            $this->getEventDispatcher(),
            $this->getConfigManager(),
            $this->getLock(),
            $this->getConfigTyped(),
            $this->getModuleHandler(),
            $this->getModuleInstaller(),
            $this->getThemeHandler(),
            $this->getStringTranslation(),
            $this->getModuleExtensionList()
        );
        if ($config_importer->alreadyImporting()) {
            $this->logger()->warning('Another request may be synchronizing configuration already.');
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
                                $this->logger()->notice(str_replace('Synchronizing', 'Synchronized', (string)$context['message']));
                            }

                            // Installing and uninstalling modules might trigger
                            // batch operations. Let's process them here.
                            // @see \Drush\Commands\pm\PmCommands::install()
                            if ($step === 'processExtensions' && batch_get()) {
                                drush_backend_batch_process();
                            }
                        } while ($context['finished'] < 1);
                    }
                    // Clear the cache of the active config storage.
                    $this->getConfigCache()->deleteAll();
                }
                if ($config_importer->getErrors()) {
                    throw new ConfigException('Errors occurred during import');
                } else {
                    $this->logger()->success('The configuration was imported successfully.');
                }
            } catch (ConfigException $e) {
                // Return a negative result for UI purposes. We do not differentiate
                // between an actual synchronization error and a failed lock, because
                // concurrent synchronizations are an edge-case happening only when
                // multiple developers or site builders attempt to do it without
                // coordinating.
                $message = 'The import failed due to the following reasons:' . "\n";
                $message .= implode("\n", $config_importer->getErrors());

                watchdog_exception('config_import', $e);
                throw new \Exception($message, $e->getCode(), $e);
            }
        }
    }

    /**
     * Validate partial and source options.
     */
    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: self::IMPORT)]
    public function validate(CommandData $commandData)
    {
        $msgs = [];
        if ($commandData->input()->getOption('partial') && !\Drupal::moduleHandler()->moduleExists('config')) {
            $msgs[] = 'Enable the config module in order to use the --partial option.';
        }

        if ($source = $commandData->input()->getOption('source')) {
            if (!file_exists($source)) {
                $msgs[] = 'The source directory does not exist.';
            }
            if (!is_dir($source)) {
                $msgs[] = 'The source is not a directory.';
            }
        }

        if ($msgs) {
            return new CommandError(implode(' ', $msgs));
        }
    }
}
