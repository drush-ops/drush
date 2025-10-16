<?php

declare(strict_types=1);

namespace Drush\Commands\config;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Site\Settings;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Path;

final class ConfigExportCommands extends DrushCommands
{
    const EXPORT = 'config:export';

    protected ?StorageInterface $configStorageSync;
    protected ?StorageInterface $configStorageExport;

    public function getConfigManager(): ConfigManagerInterface
    {
        return $this->configManager;
    }

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

    public function __construct(protected ConfigManagerInterface $configManager, protected StorageInterface $configStorage)
    {
        parent::__construct();
    }

    public static function create(ContainerInterface $container): self
    {
        $commandHandler = new self(
            $container->get('config.manager'),
            $container->get('config.storage')
        );

        if ($container->has('config.storage.export')) {
            $commandHandler->setExportStorage($container->get('config.storage.export'));
        }
        if ($container->has('config.storage.sync')) {
            $commandHandler->setConfigStorageSync($container->get('config.storage.sync'));
        }

        return $commandHandler;
    }

    /**
     * Export Drupal configuration to a directory.
     */
    #[CLI\Command(name: self::EXPORT, aliases: ['cex', 'config-export'])]
    #[CLI\Option(name: 'add', description: 'Run `git add -p` after exporting. This lets you choose which config changes to sync for commit.')]
    #[CLI\Option(name: 'commit', description: 'Run `git add -A` and `git commit` after exporting.  This commits everything that was exported without prompting.')]
    #[CLI\Option(name: 'message', description: 'Commit comment for the exported configuration.  Optional; may only be used with --commit.')]
    #[CLI\Option(name: 'destination', description: 'An arbitrary directory that should receive the exported files. A backup directory is used when no value is provided.')]
    #[CLI\Option(name: 'diff', description: 'Show preview as a diff, instead of a change list.')]
    #[CLI\Usage(name: 'drush config:export', description: 'Export configuration files to the site\'s config directory.')]
    #[CLI\Usage(name: 'drush config:export --destination', description: 'Export configuration; Save files in a backup directory named config-export.')]
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
        $sync_directory = Settings::get('config_sync_directory');

        // Prepare the configuration storage for the export.
        if ($sync_directory !== null && $destination_dir == Path::canonicalize($sync_directory)) {
            $target_storage = $this->getConfigStorageSync();
        } else {
            $target_storage = new FileStorage($destination_dir);
        }

        if (count(glob($destination_dir . '/*')) > 0) {
            // Retrieve a list of differences between the active and target configuration (if any).
            $config_comparer = new StorageComparer($this->getConfigStorageExport(), $target_storage);
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

            if (!$this->io()->confirm(dt('The .yml files in your export directory will be deleted and replaced with the active config.'), hint: dt('Target: !target', ['!target' => $destination_dir]))) {
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

    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: self::EXPORT)]
    public function validate($commandData): void
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
