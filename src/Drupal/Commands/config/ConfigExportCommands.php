<?php

namespace Drush\Drupal\Commands\config;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Site\Settings;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\Console\Output\BufferedOutput;
use Webmozart\PathUtil\Path;

class ConfigExportCommands extends DrushCommands
{
    /**
     * @var ConfigManagerInterface
     */
    protected $configManager;

    /**
     * @var StorageInterface
     */
    protected $configStorage;

    /**
     * @var StorageInterface
     */
    protected $configStorageSync;

    /**
     * @var StorageInterface
     */
    protected $configStorageExport;

    public function getConfigManager(): ConfigManagerInterface
    {
        return $this->configManager;
    }

    /**
     * @param StorageInterface $exportStorage
     */
    public function setExportStorage(StorageInterface $exportStorage): void
    {
        $this->configStorageExport = $exportStorage;
    }

    public function getConfigStorageExport(): StorageInterface
    {
        if (isset($this->configStorageExport)) {
            return $this->configStorageExport;
        }
        return $this->configStorage;
    }

    public function getConfigStorage(): StorageInterface
    {
        // @todo: deprecate this method.
        return $this->getConfigStorageExport();
    }

    public function getConfigStorageSync(): StorageInterface
    {
        return $this->configStorageSync;
    }

    public function setConfigStorageSync(?StorageInterface $syncStorage): void
    {
        $this->configStorageSync = $syncStorage;
    }

    /**
     * @param ConfigManagerInterface $configManager
     * @param StorageInterface $configStorage
     * @param StorageInterface $configStorageSync
     */
    public function __construct(ConfigManagerInterface $configManager, StorageInterface $configStorage)
    {
        parent::__construct();
        $this->configManager = $configManager;
        $this->configStorage = $configStorage;
    }

    /**
     * Export Drupal configuration to a directory.
     *
     * @command config:export
     * @option add Run `git add -p` after exporting. This lets you choose which config changes to sync for commit.
     * @option commit Run `git add -A` and `git commit` after exporting.  This commits everything that was exported without prompting.
     * @option message Commit comment for the exported configuration.  Optional; may only be used with --commit.
     * @option destination An arbitrary directory that should receive the exported files. A backup directory is used when no value is provided.
     * @option diff Show preview as a diff, instead of a change list.
     * @usage drush config:export
     *   Export configuration files to the site's config directory.
     * @usage drush config:export --destination
     *   Export configuration; Save files in a backup directory named config-export.
     * @aliases cex,config-export
     */
    public function export($options = ['add' => false, 'commit' => false, 'message' => self::REQ, 'destination' => self::OPT, 'diff' => false, 'format' => null]): array
    {
        // Get destination directory.
        $destination_dir = ConfigCommands::getDirectory($options['destination']);

        // Do the actual config export operation.
        $preview = $this->doExport($options, $destination_dir);

        // Do the VCS operations.
        $this->doAddCommit($options, $destination_dir, $preview);

        return ['destination-dir' => $destination_dir];
    }

    public function doExport($options, $destination_dir)
    {
        // Prepare the configuration storage for the export.
        if ($destination_dir ==  Path::canonicalize(Settings::get('config_sync_directory'))) {
            $target_storage = $this->getConfigStorageSync();
        } else {
            $target_storage = new FileStorage($destination_dir);
        }

        if (count(glob($destination_dir . '/*')) > 0) {
            // Retrieve a list of differences between the active and target configuration (if any).
            $config_comparer = new StorageComparer($this->getConfigStorageExport(), $target_storage, $this->getConfigManager());
            if (!$config_comparer->createChangelist()->hasChanges()) {
                $this->logger()->notice(dt('The active configuration is identical to the configuration in the export directory (!target).', ['!target' => $destination_dir]));
                return;
            }
            $preamble = "Differences of the active config to the export directory:\n";

            if ($options['diff']) {
                $diff = ConfigCommands::getDiff($target_storage, $this->getConfigStorageExport(), $this->output());
                $this->logger()->notice($preamble . $diff);
            } else {
                $change_list = [];
                foreach ($config_comparer->getAllCollectionNames() as $collection) {
                    $change_list[$collection] = $config_comparer->getChangelist(null, $collection);
                }
                // Print a table with changes in color, then re-generate again without
                // color to place in the commit comment.
                $bufferedOutput = new BufferedOutput();
                $table = ConfigCommands::configChangesTable($change_list, $bufferedOutput, false);
                $table->render();
                $preview = $bufferedOutput->fetch();
                $this->logger()->notice($preamble . $preview);
            }

            if (!$this->io()->confirm(dt('The .yml files in your export directory (!target) will be deleted and replaced with the active config.', ['!target' => $destination_dir]))) {
                throw new UserAbortException();
            }

            // Only delete .yml files, and not .htaccess or .git.
            $target_storage->deleteAll();

            // Also delete collections.
            foreach ($target_storage->getAllCollectionNames() as $collection_name) {
                $target_collection = $target_storage->createCollection($collection_name);
                $target_collection->deleteAll();
            }
        }

        // Write all .yml files.
        ConfigCommands::copyConfig($this->getConfigStorageExport(), $target_storage);

        $this->logger()->success(dt('Configuration successfully exported to !target.', ['!target' => $destination_dir]));
        return isset($preview) ? $preview : 'No existing configuration to diff against.';
    }

    public function doAddCommit($options, $destination_dir, $preview): void
    {
        // Commit or add exported configuration if requested.
        if ($options['commit']) {
            // There must be changed files at the destination dir; if there are not, then
            // we will skip the commit step.
            $process = $this->processManager()->process(['git', 'status', '--porcelain', '.'], $destination_dir);
            $process->mustRun();
            $uncommitted_changes = $process->getOutput();
            if (!empty($uncommitted_changes)) {
                $process = $this->processManager()->process(['git', 'add', '-A', '.'], $destination_dir);
                $process->mustRun();
                $comment_file = drush_save_data_to_temp_file($options['message'] ?: 'Exported configuration.' . $preview);
                $process = $this->processManager()->process(['git', 'commit', "--file=$comment_file"], $destination_dir);
                $process->mustRun();
            }
        } elseif ($options['add']) {
            $this->processManager()->process(['git', 'add', '-p',  $destination_dir])->run();
        }
    }

    /**
     * @hook validate config-export
     * @param CommandData $commandData
     */
    public function validate(CommandData $commandData): void
    {
        $destination = $commandData->input()->getOption('destination');

        if ($destination === true) {
            // We create a dir in command callback. No need to validate.
            return;
        }

        if (!empty($destination)) {
            // TODO: evaluate %files et. al. in destination
            // $commandData->input()->setOption('destination', $destination);
            if (!file_exists($destination)) {
                $parent = dirname($destination);
                if (!is_dir($parent)) {
                    throw new \Exception('The destination parent directory does not exist.');
                }
                if (!is_writable($parent)) {
                    throw new \Exception('The destination parent directory is not writable.');
                }
            } else {
                if (!is_dir($destination)) {
                    throw new \Exception('The destination is not a directory.');
                }
                if (!is_writable($destination)) {
                    throw new \Exception('The destination directory is not writable.');
                }
            }
        }
    }
}
