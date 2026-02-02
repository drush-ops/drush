<?php

declare(strict_types=1);

namespace Drush\Commands\config;

use Drupal\Core\Config\ConfigDirectoryNotDefinedException;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Site\Settings;
use Drush\Drush;
use Drush\Exec\ExecTrait;
use Drush\Utils\FsUtils;
use RuntimeException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

trait ConfigTrait
{
    use ExecTrait;

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
     * Determine which configuration directory to use and return directory path.
     *
     * Directory path is determined based on the following precedence:
     *   1. User-provided $directory.
     *   2. Default sync directory
     *
     * @note: $directory param can be boolean.
     */
    public static function getDirectory(mixed $directory = null): string
    {
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
            // If a directory isn't specified, use the default sync directory.
            $return = Settings::get('config_sync_directory', false);
            if ($return === false) {
                throw new ConfigDirectoryNotDefinedException('The config sync directory is not defined in $settings["config_sync_directory"]');
            }
        }
        return Path::canonicalize($return);
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
                throw new \Exception(sprintf('Source not found for %s.', $name), $e->getCode(), $e);
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
        if (self::programExists('git')) {
            $prefix = ['git', 'diff', '--no-index', '--exit-code'];
            if ($output->isDecorated()) {
                $prefix[] = '--color=always';
            }
        }
        $args = array_merge($prefix, ['-u', $temp_destination_dir, $temp_source_dir]);
        $process = Drush::process($args);
        $process->run();
        // An exit code of 1 merely indicates that there is a diff.
        if ($process->getExitCode() >= 2) {
            throw new RuntimeException($process->getExitCodeText());
        }
        return $process->getOutput();
    }

    /**
     * Build a table of config changes.
     *
     * @param array $config_changes
     *   An array of changes keyed by collection.
     */
    public static function configChangesTable(array $config_changes, OutputInterface $output, $use_color = true): Table
    {
        $rows = [];
        foreach ($config_changes as $collection => $changes) {
            foreach ($changes as $change => $configs) {
                $color = match ($change) {
                    'delete' => '<fg=white;bg=red>',
                    'update' => '<fg=black;bg=yellow>',
                    'create' => '<fg=white;bg=green>',
                    default => "<fg=black;bg=cyan>",
                };
                if ($use_color) {
                    $prefix = $color;
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
}
