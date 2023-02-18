<?php

namespace Drush\Drupal\Commands\config;

use Drupal\Core\Config\ConfigDirectoryNotDefinedException;
use Drupal\Core\Config\ImportStorageTransformer;
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
use Drupal\Core\Site\Settings;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Exec\ExecTrait;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Drush\Utils\FsUtils;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Parser;

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
     * @var StorageInterface
     */
    protected $configStorage;

    /**
     * @var ImportStorageTransformer
     */
    protected $importStorageTransformer;

    public function getConfigFactory(): ConfigFactoryInterface
    {
        return $this->configFactory;
    }


    /**
     * ConfigCommands constructor.
     * @param ConfigFactoryInterface $configFactory
     * @param StorageInterface $configStorage
     */
    public function __construct($configFactory, StorageInterface $configStorage)
    {
        parent::__construct();
        $this->configFactory = $configFactory;
        $this->configStorage = $configStorage;
    }

    /**
     * @param StorageInterface $exportStorage
     */
    public function setExportStorage(StorageInterface $exportStorage): void
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

    public function setImportTransformer(ImportStorageTransformer $importStorageTransformer): void
    {
        $this->importStorageTransformer = $importStorageTransformer;
    }

    public function hasImportTransformer(): bool
    {
        return isset($this->importStorageTransformer);
    }

    public function getImportTransformer(): ImportStorageTransformer
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
     * @complete configComplete
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
     * Save a config value directly. Does not perform a config import.
     *
     * @command config:set
     * @validate-config-name
     * @todo @interact-config-name deferred until we have interaction for key.
     * @param $config_name The config object name, for example <info>system.site</info>.
     * @param $key The config key, for example <info>page.front</info>. Use <info>?</info> if you are updating multiple top-level keys.
     * @param $value The value to assign to the config key. Use <info>-</info> to read from Stdin.
     * @option input-format Format to parse the object. Recognized values: <info>string</info>, <info>yaml</info>. Since JSON is a subset of YAML, $value may be in JSON format.
     * @usage drush config:set system.site name MySite
     *   Sets a value for the key <info>name</info> of <info>system.site</info> config object.
     * @usage drush config:set system.site page.front '/path/to/page'
     *   Sets the given URL path as value for the config item with key <info>page.front</info> of <info>system.site</info> config object.
     * @usage drush config:set system.site '[]'
     *   Sets the given key to an empty array.
     * @usage drush config:set --input-format=yaml user.role.authenticated permissions [foo,bar]
     *   Use a sequence as value for the key <info>permissions</info> of <info>user.role.authenticated</info> config object.
     * @usage drush config:set --input-format=yaml system.site page {403: '403', front: home}
     *   Use a mapping as value for the key <info>page</info> of <info>system.site</info> config object.
     * @usage drush config:set --input-format=yaml user.role.authenticated ? "{label: 'Auth user', weight: 5}"
     *   Update two top level keys (label, weight) in the <info>system.site</info> config object.
     * @aliases cset,config-set
     * @complete configComplete
     */
    public function set($config_name, $key, $value, $options = ['input-format' => 'string'])
    {
        $data = $value;

        if (!isset($data)) {
            throw new \Exception(dt('No config value specified.'));
        }

        // Special flag indicating that the value has been passed via STDIN.
        if ($data === '-') {
            $data = $this->stdin()->contents();
        }

        // Special handling for empty array.
        if ($data == '[]') {
            $data = [];
        }

        // Parse the value if needed.
        switch ($options['input-format']) {
            case 'yaml':
                $parser = new Parser();
                $data = $parser->parse($data, true);
        }

        $config = $this->getConfigFactory()->getEditable($config_name);
        // Check to see if config key already exists.
        $new_key = $config->get($key) === null;
        $simulate = $this->getConfig()->simulate();

        if ($key == '?' && !empty($data) && $this->io()->confirm(dt('Do you want to update or set multiple keys on !name config.', ['!name' => $config_name]))) {
            foreach ($data as $data_key => $val) {
                $config->set($data_key, $val);
            }
            return $simulate ? self::EXIT_SUCCESS : $config->save();
        } else {
            $confirmed = false;
            if ($config->isNew() && $this->io()->confirm(dt('!name config does not exist. Do you want to create a new config object?', ['!name' => $config_name]))) {
                $confirmed = true;
            } elseif ($new_key && $this->io()->confirm(dt('!key key does not exist in !name config. Do you want to create a new config key?', ['!key' => $key, '!name' => $config_name]))) {
                $confirmed = true;
            } elseif ($this->io()->confirm(dt('Do you want to update !key key in !name config?', ['!key' => $key, '!name' => $config_name]))) {
                $confirmed = true;
            }
            if ($confirmed && !$simulate) {
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
     * @complete configComplete
     */
    public function edit($config_name, $options = []): void
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
        $exec = self::getEditor($options['editor']);
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
     * Delete a configuration key, or a whole object(s).
     *
     * @command config:delete
     * @validate-config-name
     * @interact-config-name
     * @param $config_name The config object name(s). Delimit multiple with commas.
     * @param $key A config key to clear, May not be used with multiple config names.
     * @usage drush config:delete system.site,system.rss
     *   Delete the system.site and system.rss config objects.
     * @usage drush config:delete system.site page.front
     *   Delete the 'page.front' key from the system.site object.
     * @aliases cdel,config-delete
     * @complete configComplete
     */
    public function delete($config_name, $key = null): void
    {
        if ($key) {
            $config = $this->getConfigFactory()->getEditable($config_name);
            if ($config->get($key) === null) {
                throw new \Exception(dt('Configuration key !key not found.', ['!key' => $key]));
            }
            $config->clear($key)->save();
        } else {
            $names = StringUtils::csvToArray($config_name);
            foreach ($names as $name) {
                $config = $this->getConfigFactory()->getEditable($name);
                $config->delete();
            }
        }
    }

    /**
     * Display status of configuration (differences between the filesystem configuration and database configuration).
     *
     * @command config:status
     * @option state  A comma-separated list of states to filter results.
     * @option prefix Prefix The config prefix. For example, <info>system</info>. No prefix will return all names in the system.
     * @usage drush config:status
     *   Display configuration items that need to be synchronized.
     * @usage drush config:status --state=Identical
     *   Display configuration items that are in default state.
     * @usage drush config:status --state='Only in sync dir' --prefix=node.type.
     *   Display all content types that would be created in active storage on configuration import.
     * @usage drush config:status --state=Any --format=list
     *   List all config names.
     * @usage drush config:status 2>&amp;1 | grep "No differences"
     *   Check there are no differences between database and exported config. Useful for CI.
     * @field-labels
     *   name: Name
     *   state: State
     * @default-fields name,state
     * @aliases cst,config-status
     * @filter-default-field name
     */
    public function status($options = ['state' => 'Only in DB,Only in sync dir,Different', 'prefix' => self::REQ]): ?RowsOfFields
    {
        $config_list = array_fill_keys(
            $this->configFactory->listAll($options['prefix']),
            'Identical'
        );

        $directory = $this->getDirectory();
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
     *   2. Default sync directory
     *
     * @param string $directory
     *   A configuration directory.
     */
    public static function getDirectory($directory = null): string
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
            // If a directory isn't specified, use default sync directory.
            $return = Settings::get('config_sync_directory', false);
            if ($return === false) {
                throw new ConfigDirectoryNotDefinedException('The config sync directory is not defined in $settings["config_sync_directory"]');
            }
        }
        return Path::canonicalize($return);
    }

    /**
     * Returns the difference in configuration between active storage and target storage.
     */
    public function getChanges($target_storage): array
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
        if ($directory == Path::canonicalize(Settings::get('config_sync_directory'))) {
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
    public static function configChangesTable(array $config_changes, OutputInterface $output, $use_color = true): Table
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

    public function configComplete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('config_name')) {
            $suggestions->suggestValues($this->getConfigFactory()->listAll());
        }
    }

    /**
     * @hook interact @interact-config-name
     */
    public function interactConfigName($input, $output): void
    {
        if (empty($input->getArgument('config_name'))) {
            $config_names = $this->getConfigFactory()->listAll();
            $choice = $this->io()->choice('Choose a configuration', drush_map_assoc($config_names));
            $input->setArgument('config_name', $choice);
        }
    }

    /**
     * Validate that a config name is valid.
     *
     * If the argument to be validated is not named $config_name, pass the
     * argument name as the value of the annotation.
     *
     * @hook validate @validate-config-name
     * @param CommandData $commandData
     * @return CommandError|null
     */
    public function validateConfigName(CommandData $commandData)
    {
        $arg_name = $commandData->annotationData()->get('validate-config-name', null) ?: 'config_name';
        $config_name = $commandData->input()->getArgument($arg_name);
        $names = StringUtils::csvToArray($config_name);
        foreach ($names as $name) {
            $config = \Drupal::config($name);
            if ($config->isNew()) {
                $msg = dt('Config !name does not exist', ['!name' => $name]);
                return new CommandError($msg);
            }
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
    public static function copyConfig(StorageInterface $source, StorageInterface $destination): void
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
    public static function getDiff(StorageInterface $destination_storage, StorageInterface $source_storage, OutputInterface $output): string
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
