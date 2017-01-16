<?php
namespace Drush\Commands\config;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\FileStorage;
use Drush\Config\StorageWrapper;
use Drush\Commands\DrushCommands;

class ConfigExportCommands extends DrushCommands implements CustomEventAwareInterface {

  use ConfigTrait;

  /**
   * Export Drupal configuration to a directory.
   *
   * @command config-export
   * @interact-config-label
   * @param string $label A config directory label (i.e. a key in $config_directories array in settings.php).
   * @optionset-storage-filters
   * @option add Run `git add -p` after exporting. This lets you choose which config changes to sync for commit.
   * @option commit Run `git add -A` and `git commit` after exporting.  This commits everything that was exported without prompting.
   * @option message Commit comment for the exported configuration.  Optional; may only be used with --commit.
   * @option destination An arbitrary directory that should receive the exported files. An alternative to label argument.
   * @usage drush config-export --skip-modules=devel
   *   Export configuration; do not include the devel module in the exported configuration, regardless of whether or not it is enabled in the site.
   * @usage drush config-export --destination
   *   Export configuration; Save files in a backup directory named config-export.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases cex
   * @complete \Drush\Commands\core\ConfigCommands::completeLabels
   */
  public function export($label = NULL, $options = ['add' => FALSE, 'commit' => FALSE, 'message' => NULL, 'destination' => '']) {
    $destination_dir = $this->processDestination($label, $options);

    // Do the actual config export operation.
    $preview = $this->doExport($options, $destination_dir);

    // Do the VCS operations.
    $this->doAddCommit($options, $destination_dir, $preview);
  }

  function processDestination($label, $options) {
    // Determine which target directory to use.
    if ($target = $options['destination']) {
      if ($target === TRUE) {
        // User did not pass a specific value for --destination. Make one.
        /** @var drush_version_control_backup $backup */
        $backup = drush_include_engine('version_control', 'backup');
        $destination_dir = $backup->prepare_backup_dir('config-export');
      }
      else {
        $destination_dir = $target;
        // It is important to be able to specify a destination directory that
        // does not exist yet, for exporting on remote systems
        drush_mkdir($destination_dir);
      }
    }
    else {
      $destination_dir = \config_get_config_directory($label ?: CONFIG_SYNC_DIRECTORY);
    }
    return $destination_dir;
  }

  public function doExport($options, $destination_dir) {
    $commit = $options['commit'];
    $storage_filters = $this->getStorageFilters($options);
    if (count(glob($destination_dir . '/*')) > 0) {
      // Retrieve a list of differences between the active and target configuration (if any).
      $target_storage = new FileStorage($destination_dir);
      /** @var \Drupal\Core\Config\StorageInterface $active_storage */
      $active_storage = \Drupal::service('config.storage');
      $comparison_source = $active_storage;

      // If the output is being filtered, then write a temporary copy before doing
      // any comparison.
      if (!empty($storage_filters)) {
        $tmpdir = drush_tempdir();
        drush_copy_dir($destination_dir, $tmpdir, FILE_EXISTS_OVERWRITE);
        $comparison_source = new FileStorage($tmpdir);
        $comparison_source_filtered = new StorageWrapper($comparison_source, $storage_filters);
        foreach ($active_storage->listAll() as $name) {
          // Copy active storage to our temporary active store.
          if ($existing = $active_storage->read($name)) {
            $comparison_source_filtered->write($name, $existing);
          }
        }
      }

      $config_comparer = new StorageComparer($comparison_source, $target_storage, \Drupal::service('config.manager'));
      if (!$config_comparer->createChangelist()->hasChanges()) {
        $this->logger()->notice(dt('The active configuration is identical to the configuration in the export directory (!target).', array('!target' => $destination_dir)));
        return;
      }

      drush_print("Differences of the active config to the export directory:\n");
      $change_list = array();
      foreach ($config_comparer->getAllCollectionNames() as $collection) {
        $change_list[$collection] = $config_comparer->getChangelist(NULL, $collection);
      }
      // Print a table with changes in color, then re-generate again without
      // color to place in the commit comment.
      ConfigCommands::configChangesTablePrint($change_list);
      $tbl = ConfigCommands::configChangesTableFormat($change_list);
      $preview = $tbl->getTable();
      if (!stristr(PHP_OS, 'WIN')) {
        $preview = str_replace("\r\n", PHP_EOL, $preview);
      }

      if (!$commit && !drush_confirm(dt('The .yml files in your export directory (!target) will be deleted and replaced with the active config.', array('!target' => $destination_dir)))) {
        return drush_user_abort();
      }
      // Only delete .yml files, and not .htaccess or .git.
      drush_scan_directory($destination_dir, '/\.yml$/', array('.', '..'), 'unlink');
    }

    // Write all .yml files.
    $source_storage = \Drupal::service('config.storage');
    $destination_storage = new FileStorage($destination_dir);
    // If there are any filters, then attach them to the destination storage
    if (!empty($storage_filters)) {
      $destination_storage = new StorageWrapper($destination_storage, $storage_filters);
    }
    foreach ($source_storage->listAll() as $name) {
      $destination_storage->write($name, $source_storage->read($name));
    }

    // Export configuration collections.
    foreach (\Drupal::service('config.storage')->getAllCollectionNames() as $collection) {
      $source_storage = $source_storage->createCollection($collection);
      $destination_storage = $destination_storage->createCollection($collection);
      foreach ($source_storage->listAll() as $name) {
        $destination_storage->write($name, $source_storage->read($name));
      }
    }

    $this->logger()->success(dt('Configuration successfully exported to !target.', array('!target' => $destination_dir)));
    drush_backend_set_result($destination_dir);
    return $preview;
  }

  public function doAddCommit($options, $destination_dir, $preview) {
    // Commit or add exported configuration if requested.
    if ($options['commit']) {
      // There must be changed files at the destination dir; if there are not, then
      // we will skip the commit step.
      $result = drush_shell_cd_and_exec($destination_dir, 'git status --porcelain .');
      if (!$result) {
        throw new \Exception(dt("`git status` failed."));
      }
      $uncommitted_changes = drush_shell_exec_output();
      if (!empty($uncommitted_changes)) {
        $result = drush_shell_cd_and_exec($destination_dir, 'git add -A .');
        if (!$result) {
          throw new \Exception(dt("`git add -A` failed."));
        }
        $comment_file = drush_save_data_to_temp_file($options['message'] ?: 'Exported configuration.'. $preview);
        $result = drush_shell_cd_and_exec($destination_dir, 'git commit --file=%s', $comment_file);
        if (!$result) {
          throw new \Exception(dt("`git commit` failed.  Output:\n\n!output", array('!output' => implode("\n", drush_shell_exec_output()))));
        }
      }
    }
    elseif ($options['add']) {
      drush_shell_exec_interactive('git add -p %s', $destination_dir);
    }
  }

  /**
   * @hook validate config-export
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   */
  public function validate(CommandData $commandData) {
    $destination = $commandData->input()->getOption('destination');

    if ($destination === TRUE) {
      // We create a dir in command callback. No need to validate.
      return;
    }

    if (!empty($destination)) {
      $additional = array();
      $values = drush_sitealias_evaluate_path($destination, $additional, TRUE);
      if (!isset($values['path'])) {
        throw new \Exception('The destination directory could not be evaluated.');
      }
      $destination = $values['path'];
      $commandData->input()->setOption('destination', $destination);
      if (!file_exists($destination)) {
        $parent = dirname($destination);
        if (!is_dir($parent)) {
          throw new \Exception('The destination parent directory does not exist.');
        }
        if (!is_writable($parent)) {
          throw new \Exception('The destination parent directory is not writable.');
        }
      }
      else {
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