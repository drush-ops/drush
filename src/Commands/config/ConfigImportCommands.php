<?php
namespace Drush\Commands\config;

use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\AnnotatedCommand\CommandData;
use Drupal\config\StorageReplaceDataWrapper;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\ConfigException;
use Drupal\Core\Config\FileStorage;
use Drush\Commands\DrushCommands;

class ConfigImportCommands extends DrushCommands {

  /**
   * Import config from a config directory.
   *
   * @command config-import
   * @param $label A config directory label (i.e. a key in \$config_directories array in settings.php).
   * @interact-config-label
   * @option preview Format for displaying proposed changes. Recognized values: list, diff.
   * @option source An arbitrary directory that holds the configuration files. An alternative to label argument
   * @option partial Allows for partial config imports from the source directory. Only updates and new configs will be processed with this flag (missing configs will not be deleted).
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases cim
   * @complete \Drush\Commands\core\ConfigCommands::completeLabels
   */
  public function import($label = NULL, $options = ['preview' => 'list', 'source' => '', 'partial' => FALSE]) {
    // Determine source directory.
    if ($target = $options['source']) {
      $source_dir = $target;
      $source_storage = new FileStorage($target);
    }
    else {
      $source_dir = \config_get_config_directory($label ?: CONFIG_SYNC_DIRECTORY);
      $source_storage = \Drupal::service('config.storage.sync');
    }

    // Determine $source_storage in partial case.
    /** @var \Drupal\Core\Config\StorageInterface $active_storage */
    $active_storage = \Drupal::service('config.storage');
    if (drush_get_option('partial')) {
      $replacement_storage = new StorageReplaceDataWrapper($active_storage);
      foreach ($source_storage->listAll() as $name) {
        $data = $source_storage->read($name);
        $replacement_storage->replaceData($name, $data);
      }
      $source_storage = $replacement_storage;
    }

    /** @var \Drupal\Core\Config\ConfigManagerInterface $config_manager */
    $config_manager = \Drupal::service('config.manager');
    $storage_comparer = new StorageComparer($source_storage, $active_storage, $config_manager);


    if (!$storage_comparer->createChangelist()->hasChanges()) {
      $this->logger()->notice(('There are no changes to import.'));
      return;
    }

    if ($options['preview'] == 'list') {
      $change_list = array();
      foreach ($storage_comparer->getAllCollectionNames() as $collection) {
        $change_list[$collection] = $storage_comparer->getChangelist(NULL, $collection);
      }
      ConfigCommands::configChangesTablePrint($change_list);
    }
    else {
      // Copy active storage to the temporary directory.
      $temp_dir = drush_tempdir();
      $temp_storage = new FileStorage($temp_dir);
      $source_dir_storage = new FileStorage($source_dir);
      foreach ($source_dir_storage->listAll() as $name) {
        if ($data = $active_storage->read($name)) {
          $temp_storage->write($name, $data);
        }
      }
      drush_shell_exec('diff -x %s -u %s %s', '*.git', $temp_dir, $source_dir);
      $output = drush_shell_exec_output();
      drush_print(implode("\n", $output));
    }

    if ($this->io()->confirm(dt('Import the listed configuration changes?'))) {
      return drush_op([$this, 'doImport'], $storage_comparer);
    }
  }

  // Copied from submitForm() at /core/modules/config/src/Form/ConfigSync.php
  public function doImport($storage_comparer) {
    $config_importer = new ConfigImporter(
      $storage_comparer,
      \Drupal::service('event_dispatcher'),
      \Drupal::service('config.manager'),
      \Drupal::lock(),
      \Drupal::service('config.typed'),
      \Drupal::moduleHandler(),
      \Drupal::service('module_installer'),
      \Drupal::service('theme_handler'),
      \Drupal::service('string_translation')
    );
    if ($config_importer->alreadyImporting()) {
      $this->logger()->warn('Another request may be synchronizing configuration already.');
    }
    else{
      try {
        // This is the contents of \Drupal\Core\Config\ConfigImporter::import.
        // Copied here so we can log progress.
        if ($config_importer->hasUnprocessedConfigurationChanges()) {
          $sync_steps = $config_importer->initialize();
          foreach ($sync_steps as $step) {
            $context = array();
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
        }
        else {
          $this->logger()->success('The configuration was imported successfully.');
        }
      }
      catch (ConfigException $e) {
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
  public function validate(CommandData $commandData) {
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
