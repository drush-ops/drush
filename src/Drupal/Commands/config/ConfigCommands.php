<?php
namespace Drush\Drupal\Commands\config;

use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Input\StdinAwareInterface;
use Consolidation\AnnotatedCommand\Input\StdinAwareTrait;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteProcess\Util\Escape;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Exec\ExecTrait;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Drush\Utils\FsUtils;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;
use Webmozart\PathUtil\Path;

class ConfigCommands extends DrushCommands implements StdinAwareInterface, SiteAliasManagerAwareInterface
{
    use StdinAwareTrait;
    use ExecTrait;
    use SiteAliasManagerAwareTrait;

    /**
     * @var ConfigFactoryInterface
     */
    protected $configFactory;

    /**
     * @var StorageInterface
     */
    protected $configStorageExport;

    /**
     * @var \Drupal\Core\Config\ImportStorageTransformer
     */
    protected $importStorageTransformer;

    /**
     * @return ConfigFactoryInterface
     */
    public function getConfigFactory()
    {
        return $this->configFactory;
    }


    /**
     * ConfigCommands constructor.
     * @param ConfigFactoryInterface $configFactory
     * @param \Drupal\Core\Config\StorageInterface $configStorage
     */
    public function __construct($configFactory, StorageInterface $configStorage)
    {
        parent::__construct();
        $this->configFactory = $configFactory;
        $this->configStorage = $configStorage;
    }

    /**
     * @param \Drupal\Core\Config\StorageInterface $exportStorage
     */
    public function setExportStorage(StorageInterface $exportStorage)
    {
        $this->configStorageExport = $exportStorage;
    }

    /**
     * @return StorageInterface
     */
    public function getConfigStorageExport()
    {
        if (isset($this->configStorageExport)) {
            return $this->configStorageExport;
        }
        return $this->configStorage;
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
     * Display a config value, or a whole configuration object.
     *
     * @command config:get
     * @validate-config-name
     * @interact-config-name
     * @param $config_name The config object name, for example <info>system.site</info>.
     * @param $key The config key, for example <info>page.front</info>. Optional.
     * @option source The config storage source to read. Additional labels may be defined in settings.php.
     * @option include-overridden Apply module and settings.php overrides to values.
     * @usage drush config:get system.site
     *   Displays the system.site config.
     * @usage drush config:get system.site page.front
     *   Gets system.site:page.front value.
     * @aliases cget,config-get
     */
    public function get($config_name, $key = '', $options = ['format' => 'yaml', 'source' => 'active', 'include-overridden' => false])
    {
        // Displaying overrides only applies to active storage.
        $factory = $this->getConfigFactory();
        $config = $options['include-overridden'] ? $factory->get($config_name) : $factory->getEditable($config_name);
        $value = $config->get($key);
        // @todo If the value is TRUE (for example), nothing gets printed. Is this yaml formatter's fault?
        return $key ? ["$config_name:$key" => $value] : $value;
    }

    /**
     * Set config value directly. Does not perform a config import.
     *
     * @command config:set
     * @validate-config-name
     * @todo @interact-config-name deferred until we have interaction for key.
     * @param $config_name The config object name, for example <info>system.site</info>.
     * @param $key The config key, for example <info>page.front</info>.
     * @param $value The value to assign to the config key. Use <info>-</info> to read from STDIN.
     * @option input-format Format to parse the object. Recognized values: <info>string</info>, <info>yaml</info>
     * @option value The value to assign to the config key (if any).
     * @hidden-options value
     * @usage drush config:set system.site page.front '/path/to/page'
     *   Sets the given URL path as value for the config item with key <info>page.front</info> of <info>system.site</info> config object.
     * @usage drush config:set system.site '[]'
     *   Sets the given key to an empty array.
     * @aliases cset,config-set
     */
    public function set($config_name, $key, $value = null, $options = ['input-format' => 'string', 'value' => self::REQ])
    {
        // This hidden option is a convenient way to pass a value without passing a key.
        $data = $options['value'] ?: $value;

        if (!isset($data)) {
            throw new \Exception(dt('No config value specified.'));
        }

        $config = $this->getConfigFactory()->getEditable($config_name);
        // Check to see if config key already exists.
        $new_key = $config->get($key) === null;

        // Special flag indicating that the value has been passed via STDIN.
        if ($data === '-') {
            $data = $this->stdin()->contents();
        }


        // Special handling for empty array.
        if ($data == '[]') {
            $data = [];
        }

        // Now, we parse the value.
        switch ($options['input-format']) {
            case 'yaml':
                $parser = new Parser();
                $data = $parser->parse($data, true);
        }

        if (is_array($data) && !empty($data) && $this->io()->confirm(dt('Do you want to update or set multiple keys on !name config.', ['!name' => $config_name]))) {
            foreach ($data as $data_key => $value) {
                $config->set("$key.$data_key", $value);
            }
            return $config->save();
        } else {
            $confirmed = false;
            if ($config->isNew() && $this->io()->confirm(dt('!name config does not exist. Do you want to create a new config object?', ['!name' => $config_name]))) {
                $confirmed = true;
            } elseif ($new_key && $this->io()->confirm(dt('!key key does not exist in !name config. Do you want to create a new config key?', ['!key' => $key, '!name' => $config_name]))) {
                $confirmed = true;
            } elseif ($this->io()->confirm(dt('Do you want to update !key key in !name config?', ['!key' => $key, '!name' => $config_name]))) {
                $confirmed = true;
            }
            if ($confirmed && !$this->getConfig()->simulate()) {
                return $config->set($key, $data)->save();
            }
        }
    }

    /**
     * Open a config file in a text editor. Edits are imported after closing editor.
     *
     * @command config:edit
     * @validate-config-name
     * @interact-config-name
     * @param $config_name The config object name, for example <info>system.site</info>.
     * @optionset_get_editor
     * @allow_additional_options config-import
     * @hidden-options source,partial
     * @usage drush config:edit image.style.large
     *   Edit the image style configurations.
     * @usage drush config:edit
     *   Choose a config file to edit.
     * @usage drush --bg config-edit image.style.large
     *   Return to shell prompt as soon as the editor window opens.
     * @aliases cedit,config-edit
     * @validate-module-enabled config
     */
    public function edit($config_name, $options = [])
    {
        $config = $this->getConfigFactory()->get($config_name);
        $active_storage = $config->getStorage();
        $contents = $active_storage->read($config_name);

        // Write tmp YAML file for editing
        $temp_dir = drush_tempdir();
        $temp_storage = new FileStorage($temp_dir);
        $temp_storage->write($config_name, $contents);

        // Note that `getEditor()` returns a string that contains a
        // %s placeholder for the config file path.
        $exec = self::getEditor();
        $cmd = sprintf($exec, Escape::shellArg($temp_storage->getFilePath($config_name)));
        $process = $this->processManager()->shell($cmd);
        $process->setTty(true);
        $process->mustRun();

        // Perform import operation if user did not immediately exit editor.
        if (!$options['bg']) {
            $redispatch_options = Drush::redispatchOptions() + ['strict' => 0, 'partial' => true, 'source' => $temp_dir];
            $self = $this->siteAliasManager()->getSelf();
            $process = $this->processManager()->drush($self, 'config-import', [], $redispatch_options);
            $process->mustRun($process->showRealtime());
        }
    }

    /**
     * Delete a configuration key, or a whole object.
     *
     * @command config:delete
     * @validate-config-name
     * @interact-config-name
     * @param $config_name The config object name, for example "system.site".
     * @param $key A config key to clear, for example "page.front".
     * @usage drush config:delete system.site
     *   Delete the the system.site config object.
     * @usage drush config:delete system.site page.front
     *   Delete the 'page.front' key from the system.site object.
     * @aliases cdel,config-delete
     */
    public function delete($config_name, $key = null)
    {
        $config = $this->getConfigFactory()->getEditable($config_name);
        if ($key) {
            if ($config->get($key) === null) {
                throw new \Exception(dt('Configuration key !key not found.', ['!key' => $key]));
            }
            $config->clear($key)->save();
        } else {
            $config->delete();
        }
    }

    /**
     * Display status of configuration (differences between the filesystem configuration and database configuration).
     *
     * @command config:status
     * @option state  A comma-separated list of states to filter results.
     * @option prefix Prefix The config prefix. For example, <info>system</info>. No prefix will return all names in the system.
     * @option string $label A config directory label (i.e. a key in $config_directories array in settings.php).
     * @usage drush config:status
     *   Display configuration items that need to be synchronized.
     * @usage drush config:status --state=Identical
     *   Display configuration items that are in default state.
     * @usage drush config:status --state='Only in sync dir' --prefix=node.type.
     *   Display all content types that would be created in active storage on configuration import.
     * @usage drush config:status --state=Any --format=list
     *   List all config names.
     * @usage drush config:status 2>&1 | grep "No differences"
     *   Check there are no differences between database and exported config. Useful for CI.
     * @field-labels
     *   name: Name
     *   state: State
     * @default-fields name,state
     * @aliases cst,config-status
     * @filter-default-field name
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     */
    public function status($options = ['state' => 'Only in DB,Only in sync dir,Different', 'prefix' => self::REQ, 'label' => self::REQ])
    {
        $config_list = array_fill_keys(
            $this->configFactory->listAll($options['prefix']),
            'Identical'
        );

        $directory = $this->getDirectory($options['label']);
        $storage = $this->getStorage($directory);
        $state_map = [
            'create' => 'Only in DB',
            'update' => 'Different',
            'delete' => 'Only in sync dir',
        ];
        foreach ($this->getChanges($storage) as $collection) {
            foreach ($collection as $operation => $configs) {
                foreach ($configs as $config) {
                    if (!$options['prefix'] || strpos($config, $options['prefix']) === 0) {
                        $config_list[$config] = $state_map[$operation];
                    }
                }
            }
        }

        if ($options['state']) {
            $allowed_states = explode(',', $options['state']);
            if (!in_array('Any', $allowed_states)) {
                $config_list = array_filter($config_list, function ($state) use ($allowed_states) {
                     return in_array($state, $allowed_states);
                });
            }
        }

        ksort($config_list);

        $rows = [];
        $color_map = [
            'Only in DB' => 'green',
            'Only in sync dir' => 'red',
            'Different' => 'yellow',
            'Identical' => 'white',
        ];

        foreach ($config_list as $config => $state) {
            if ($options['format'] == 'table' && $state != 'Identical') {
                $state = "<fg={$color_map[$state]};options=bold>$state</>";
            }
            $rows[$config] = [
                'name' => $config,
                'state' => $state,
            ];
        }

        if (!$rows) {
            $this->logger()->notice(dt('No differences between DB and sync directory.'));

            // Suppress output if there are no differences and we are using the
            // human readable "table" formatter so that we not uselessly output
            // empty table headers.
            if ($options['format'] === 'table') {
                return null;
            }
        }

        return new RowsOfFields($rows);
    }

    /**
     * Determine which configuration directory to use and return directory path.
     *
     * Directory path is determined based on the following precedence:
     *   1. User-provided $directory.
     *   2. Directory path corresponding to $label (mapped via $config_directories in settings.php).
     *   3. Default sync directory
     *
     * @param string $label
     *   A configuration directory label.
     * @param string $directory
     *   A configuration directory.
     */
    public static function getDirectory($label, $directory = null)
    {
        $return = null;
        // If the user provided a directory, use it.
        if (!empty($directory)) {
            if ($directory === true) {
                // The user did not pass a specific directory, make one.
                $return = FsUtils::prepareBackupDir('config-import-export');
            } else {
                // The user has specified a directory.
                drush_mkdir($directory);
                $return = $directory;
            }
        } else {
            // If a directory isn't specified, use the label argument or default sync directory.
            $return = \drush_config_get_config_directory($label ?: 'sync');
        }
        return Path::canonicalize($return);
    }

    /**
     * Returns the difference in configuration between active storage and target storage.
     */
    public function getChanges($target_storage)
    {
        if ($this->hasImportTransformer()) {
            $target_storage = $this->getImportTransformer()->transform($target_storage);
        }

        $config_comparer = new StorageComparer($this->configStorage, $target_storage);

        $change_list = [];
        if ($config_comparer->createChangelist()->hasChanges()) {
            foreach ($config_comparer->getAllCollectionNames() as $collection) {
                $change_list[$collection] = $config_comparer->getChangelist(null, $collection);
            }
        }
        return $change_list;
    }

    /**
     * Get storage corresponding to a configuration directory.
     */
    public function getStorage($directory)
    {
        if ($directory == Path::canonicalize(\drush_config_get_config_directory())) {
            return \Drupal::service('config.storage.sync');
        } else {
            return new FileStorage($directory);
        }
    }

    /**
     * Build a table of config changes.
     *
     * @param array $config_changes
     *   An array of changes keyed by collection.
     *
     * @return Table A Symfony table object.
     */
    public static function configChangesTable(array $config_changes, OutputInterface $output, $use_color = true)
    {
        $rows = [];
        foreach ($config_changes as $collection => $changes) {
            foreach ($changes as $change => $configs) {
                switch ($change) {
                    case 'delete':
                        $colour = '<fg=white;bg=red>';
                        break;
                    case 'update':
                        $colour = '<fg=black;bg=yellow>';
                        break;
                    case 'create':
                        $colour = '<fg=white;bg=green>';
                        break;
                    default:
                        $colour = "<fg=black;bg=cyan>";
                        break;
                }
                if ($use_color) {
                    $prefix = $colour;
                    $suffix = '</>';
                } else {
                    $prefix = $suffix = '';
                }
                foreach ($configs as $config) {
                    $rows[] = [
                        $collection,
                        $config,
                        $prefix . ucfirst($change) . $suffix,
                    ];
                }
            }
        }
        $table = new Table($output);
        $table->setHeaders(['Collection', 'Config', 'Operation']);
        $table->addRows($rows);
        return $table;
    }

    /**
     * @hook interact @interact-config-name
     */
    public function interactConfigName($input, $output)
    {
        if (empty($input->getArgument('config_name'))) {
            $config_names = $this->getConfigFactory()->listAll();
            $choice = $this->io()->choice('Choose a configuration', drush_map_assoc($config_names));
            $input->setArgument('config_name', $choice);
        }
    }

    /**
     * @hook interact @interact-config-label
     */
    public function interactConfigLabel(InputInterface $input, ConsoleOutputInterface $output)
    {
        if (drush_drupal_major_version() >= 9) {
            // Nothing to do.
            return;
        }

        global $config_directories;

        $option_name = $input->hasOption('destination') ? 'destination' : 'source';
        if (empty($input->getArgument('label') && empty($input->getOption($option_name)))) {
            $choices = drush_map_assoc(array_keys($config_directories));
            unset($choices[CONFIG_ACTIVE_DIRECTORY]);
            if (count($choices) >= 2) {
                $label = $this->io()->choice('Choose a '. $option_name, $choices);
                $input->setArgument('label', $label);
            }
        }
    }

    /**
     * Validate that a config name is valid.
     *
     * If the argument to be validated is not named $config_name, pass the
     * argument name as the value of the annotation.
     *
     * @hook validate @validate-config-name
     * @param \Consolidation\AnnotatedCommand\CommandData $commandData
     * @return \Consolidation\AnnotatedCommand\CommandError|null
     */
    public function validateConfigName(CommandData $commandData)
    {
        $arg_name = $commandData->annotationData()->get('validate-config-name', null) ?: 'config_name';
        $config_name = $commandData->input()->getArgument($arg_name);
        $config = \Drupal::config($config_name);
        if ($config->isNew()) {
            $msg = dt('Config !name does not exist', ['!name' => $config_name]);
            return new CommandError($msg);
        }
    }

    /**
     * Copies configuration objects from source storage to target storage.
     *
     * @param StorageInterface $source
     *   The source config storage service.
     * @param StorageInterface $destination
     *   The destination config storage service.
     * @throws \Exception
     */
    public static function copyConfig(StorageInterface $source, StorageInterface $destination)
    {
        // Make sure the source and destination are on the default collection.
        if ($source->getCollectionName() != StorageInterface::DEFAULT_COLLECTION) {
            $source = $source->createCollection(StorageInterface::DEFAULT_COLLECTION);
        }
        if ($destination->getCollectionName() != StorageInterface::DEFAULT_COLLECTION) {
            $destination = $destination->createCollection(StorageInterface::DEFAULT_COLLECTION);
        }

        // Export all the configuration.
        foreach ($source->listAll() as $name) {
            try {
                $destination->write($name, $source->read($name));
            } catch (\TypeError $e) {
                throw new \Exception(dt('Source not found for @name.', ['@name' => $name]));
            }
        }

        // Export configuration collections.
        foreach ($source->getAllCollectionNames() as $collection) {
            $source = $source->createCollection($collection);
            $destination = $destination->createCollection($collection);
            foreach ($source->listAll() as $name) {
                $destination->write($name, $source->read($name));
            }
        }
    }

    /**
     * Get diff between two config sets.
     *
     * @param StorageInterface $destination_storage
     * @param StorageInterface $source_storage
     * @param OutputInterface $output
     * @return array|bool
     *   An array of strings containing the diff.
     */
    public static function getDiff(StorageInterface $destination_storage, StorageInterface $source_storage, OutputInterface $output)
    {
        // Copy active storage to a temporary directory.
        $temp_destination_dir = drush_tempdir();
        $temp_destination_storage = new FileStorage($temp_destination_dir);
        self::copyConfig($destination_storage, $temp_destination_storage);

        // Copy source storage to a temporary directory as it could be
        // modified by the partial option or by decorated sync storages.
        $temp_source_dir = drush_tempdir();
        $temp_source_storage = new FileStorage($temp_source_dir);
        self::copyConfig($source_storage, $temp_source_storage);

        $prefix = ['diff'];
        if (self::programExists('git') && $output->isDecorated()) {
            $prefix = ['git', 'diff', '--color=always'];
        }
        $args = array_merge($prefix, ['-u', $temp_destination_dir, $temp_source_dir]);
        $process = Drush::process($args);
        $process->run();
        return $process->getOutput();
    }
}
