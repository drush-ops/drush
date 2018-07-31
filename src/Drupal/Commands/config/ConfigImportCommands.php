<?php
namespace Drush\Drupal\Commands\config;

use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\AnnotatedCommand\CommandData;
use Drupal\config\StorageReplaceDataWrapper;
use Drupal\Core\Config\ConfigManagerInterface;
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
use Symfony\Component\Yaml\Parser;
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

    protected $eventDispatcher;

    protected $lock;

    protected $configTyped;

    protected $moduleInstaller;

    protected $themeHandler;

    protected $stringTranslation;

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
     * @param ConfigManagerInterface $configManager
     * @param StorageInterface $configStorage
     * @param StorageInterface $configStorageSync
     */
    public function __construct(ConfigManagerInterface $configManager, StorageInterface $configStorage, StorageInterface $configStorageSync, ModuleHandlerInterface $moduleHandler, EventDispatcherInterface $eventDispatcher, LockBackendInterface $lock, TypedConfigManagerInterface $configTyped, ModuleInstallerInterface $moduleInstaller, ThemeHandlerInterface $themeHandler, TranslationInterface $stringTranslation)
    {
        parent::__construct();
        $this->configManager = $configManager;
        $this->configStorage = $configStorage;
        $this->configStorageSync = $configStorageSync;
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
     * @command config:import
     * @param $label A config directory label (i.e. a key in \$config_directories array in settings.php).
     * @interact-config-label
     * @option diff Show preview as a diff.
     * @option preview Deprecated. Format for displaying proposed changes. Recognized values: list, diff.
     * @option source An arbitrary directory that holds the configuration files. An alternative to label argument
     * @option partial Allows for partial config imports from the source directory. Only updates and new configs will be processed with this flag (missing configs will not be deleted).
     * @aliases cim,config-import
     */
    public function import($label = null, $options = ['preview' => 'list', 'source' => self::REQ, 'partial' => false, 'diff' => false])
    {
        // Determine source directory.

        $source_storage_dir = ConfigCommands::getDirectory($label, $options['source']);

        // Prepare the configuration storage for the import.
        if ($source_storage_dir == Path::canonicalize(\config_get_config_directory(CONFIG_SYNC_DIRECTORY))) {
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

            $this->output()->writeln(implode("\n", $output));
        }

        if ($this->io()->confirm(dt('Import the listed configuration changes?'))) {
            return drush_op([$this, 'doImport'], $storage_comparer);
        }
    }

    /**
     * Import single config file from a config directory.
     *
     * @command config:import:single
     * @param $file A config file (in the sync directory).
     * @interact-config-file
     * @param $label A config directory label (i.e. a key in \$config_directories array in settings.php).
     * @interact-config-label
     * @aliases cis,config-import-single
     *
     * @return bool
     */
    public function importSingle($file, $label = null)
    {
        // Determine source directory.
        $source_storage_dir = ConfigCommands::getDirectory($label, NULL);

        // Prepare the configuration storage for the import.
        if ($source_storage_dir == Path::canonicalize(\config_get_config_directory(CONFIG_SYNC_DIRECTORY))) {
            $source_storage = $this->getConfigStorageSync();
        } else {
            $source_storage = new FileStorage($source_storage_dir);
        }
        // Determine $source_storage in partial case.
        $active_storage = $this->getConfigStorage();

        try {
            $source_storage = new StorageReplaceDataWrapper($active_storage);
            $configFile = Path::canonicalize($source_storage_dir) . '/' . $file;

            if (!file_exists($configFile)) {
                $this->logger()->error('File is missing.');
                return FALSE;
            }

            $name = Path::getFilenameWithoutExtension($configFile);
            $ymlFile = new Parser();
            $value = $ymlFile->parse(file_get_contents($configFile));
            $source_storage->delete($name);
            $source_storage->write($name, $value);

            $config_manager = $this->getConfigManager();
            $storage_comparer = new StorageComparer($source_storage, $active_storage, $config_manager);

            // @todo Check the changeList

            if ($this->io()->confirm(dt('Import the listed configuration changes?'))) {
                return drush_op([$this, 'doImport'], $storage_comparer);
            }
        }
        catch (\Exception $e) {
            $this->logger()->error($e->getMessage());
            return FALSE;
        }
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

    /**
     * @hook validate config-import
     * @param \Consolidation\AnnotatedCommand\CommandData $commandData
     * @return \Consolidation\AnnotatedCommand\CommandError|null
     */
    public function validate(CommandData $commandData)
    {
        $msgs = [];
        if ($commandData->input()->getOption('partial') && !$this->getModuleHandler()->moduleExists('config')) {
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
