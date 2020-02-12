<?php
namespace Drush\Drupal\Commands\config;

use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\AnnotatedCommand\CommandData;
use Drupal\config\StorageReplaceDataWrapper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageCacheInterface;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\ConfigException;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Webmozart\PathUtil\Path;

class ConfigImportCommands extends DrushCommands
{

    /**
     * @var ConfigManagerInterface
     */
    protected $configManager;

    protected $configStorage;

    protected $configStorageSync;

    protected $configCache;

    protected $eventDispatcher;

    protected $lock;

    protected $configTyped;

    protected $moduleInstaller;

    protected $themeHandler;

    protected $stringTranslation;

    protected $importStorageTransformer;

    /**
     * @var \Drupal\Core\Extension\ModuleHandlerInterface
     */
    protected $moduleHandler;

    /**
     * @return ConfigManagerInterface
     */
    public function getConfigManager()
    {
        return $this->configManager;
    }

    /**
     * @return StorageInterface
     */
    public function getConfigStorage()
    {
        return $this->configStorage;
    }

    /**
     * @return StorageInterface
     */
    public function getConfigStorageSync()
    {
        return $this->configStorageSync;
    }

    /**
     * @return \Drupal\Core\Cache\CacheBackendInterface
     */
    public function getConfigCache()
    {
        return $this->configCache;
    }

    /**
     * @return \Drupal\Core\Extension\ModuleHandlerInterface
     */
    public function getModuleHandler()
    {
        return $this->moduleHandler;
    }

    /**
     * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }

    /**
     * @return \Drupal\Core\Lock\LockBackendInterface
     */
    public function getLock()
    {
        return $this->lock;
    }

    /**
     * @return \Drupal\Core\Config\TypedConfigManagerInterface
     */
    public function getConfigTyped()
    {
        return $this->configTyped;
    }

    /**
     * @return \Drupal\Core\Extension\ModuleInstallerInterface
     */
    public function getModuleInstaller()
    {
        return $this->moduleInstaller;
    }

    /**
     * @return \Drupal\Core\Extension\ThemeHandlerInterface
     */
    public function getThemeHandler()
    {
        return $this->themeHandler;
    }

    /**
     * @return \Drupal\Core\StringTranslation\TranslationInterface
     */
    public function getStringTranslation()
    {
        return $this->stringTranslation;
    }

    /**
     * @param \Drupal\Core\Config\ImportStorageTransformer $importStorageTransformer
     */
    public function setImportTransformer($importStorageTransformer)
    {
        $this->importStorageTransformer = $importStorageTransformer;
    }

    /**
     * @return bool
     */
    public function hasImportTransformer()
    {
        return isset($this->importStorageTransformer);
    }

    /**
     * @return \Drupal\Core\Config\ImportStorageTransformer
     */
    public function getImportTransformer()
    {
        return $this->importStorageTransformer;
    }


    /**
     * @param ConfigManagerInterface $configManager
     * @param StorageInterface $configStorage
     * @param StorageInterface $configStorageSync
     */
    public function __construct(
        ConfigManagerInterface $configManager,
        StorageInterface $configStorage,
        StorageInterface $configStorageSync,
        CacheBackendInterface $configCache,
        ModuleHandlerInterface $moduleHandler,
        EventDispatcherInterface $eventDispatcher,
        LockBackendInterface $lock,
        TypedConfigManagerInterface $configTyped,
        ModuleInstallerInterface $moduleInstaller,
        ThemeHandlerInterface $themeHandler,
        TranslationInterface $stringTranslation
    ) {
        parent::__construct();
        $this->configManager = $configManager;
        $this->configStorage = $configStorage;
        $this->configStorageSync = $configStorageSync;
        $this->configCache = $configCache;
        $this->moduleHandler = $moduleHandler;
        $this->eventDispatcher = $eventDispatcher;
        $this->lock = $lock;
        $this->configTyped = $configTyped;
        $this->moduleInstaller = $moduleInstaller;
        $this->themeHandler = $themeHandler;
        $this->stringTranslation = $stringTranslation;
    }

    /**
     * Import config from a config directory.
     *
     * This command is invoked through a wrapper command because it requires to
     * be bootstrapped using the UpdateKernel.
     *
     * @see \Drush\Commands\config\ConfigImportCommands::import()
     */
    public function import($label = null, $options = ['preview' => 'list', 'source' => self::REQ, 'partial' => false, 'diff' => false])
    {
        // Determine source directory.

        $source_storage_dir = ConfigCommands::getDirectory($label, $options['source']);

        // Prepare the configuration storage for the import.
        if ($source_storage_dir == Path::canonicalize(\drush_config_get_config_directory())) {
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

        if ($options['preview'] == 'list' && !$options['diff']) {
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
    public function doImport($storage_comparer)
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
            $this->getStringTranslation()
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
                throw new \Exception($message);
            }
        }
    }
}
