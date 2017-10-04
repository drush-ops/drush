<?php

/**
 * @file
 *   Provides Configuration Management commands.
 */

use Drupal\config\StorageReplaceDataWrapper;
use Drush\Log\LogLevel;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\ConfigException;
use Drupal\Core\Config\FileStorage;
use Drupal\Component\Utility\NestedArray;
use Drush\Config\StorageWrapper;
use Drush\Config\CoreExtensionFilter;
use Symfony\Component\Yaml\Parser;

/**
 * Implementation of hook_drush_help().
 */
function config_drush_help($section) {
  switch ($section) {
    case 'meta:config:title':
      return dt('Config commands');
    case 'meta:config:summary':
      return dt('Interact with the configuration system.');
  }
}

/**
 * Implementation of hook_drush_command().
 */
function config_drush_command() {
  $deps = array('drupal dependencies' => array('config'));
  $items['config-get'] = array(
    'description' => 'Display a config value, or a whole configuration object.',
    'arguments' => array(
      'config-name' => 'The config object name, for example "system.site".',
      'key' => 'The config key, for example "page.front". Optional.',
    ),
    'required-arguments' => 1,
    'options' => array(
      'source' => array(
        'description' => 'The config storage source to read. Additional labels may be defined in settings.php',
        'example-value' => 'sync',
        'value' => 'required',
      ),
      'include-overridden' => array(
        'description' => 'Include overridden values.',
      )
    ),
    'examples' => array(
      'drush config-get system.site' => 'Displays the system.site config.',
      'drush config-get system.site page.front' => 'gets system.site:page.front value.',
    ),
    'outputformat' => array(
      'default' => 'yaml',
      'pipe-format' => 'var_export',
    ),
    'aliases' => array('cget'),
    'core' => array('8+'),
  );

  $items['config-set'] = array(
    'description' => 'Set config value directly. Does not perform a config import.',
    'arguments' => array(
      'config-name' => 'The config object name, for example "system.site".',
      'key' => 'The config key, for example "page.front".',
      'value' => 'The value to assign to the config key. Use \'-\' to read from STDIN.',
    ),
    'options' => array(
      'format' => array(
        'description' => 'Format to parse the object. Use "string" for string (default), and "yaml" for YAML.',
        'example-value' => 'yaml',
        'value' => 'required',
      ),
      // A convenient way to pass a multiline value within a backend request.
      'value' => array(
        'description' => 'The value to assign to the config key (if any).',
        'hidden' => TRUE,
      ),
    ),
    'examples' => array(
      'drush config-set system.site page.front node' => 'Sets system.site:page.front to "node".',
    ),
    'aliases' => array('cset'),
    'core' => array('8+'),
  );

  $items['config-export'] = array(
    'description' => 'Export configuration to a directory.',
    'core' => array('8+'),
    'aliases' => array('cex'),
    'arguments' => array(
      'label' => "A config directory label (i.e. a key in \$config_directories array in settings.php). Defaults to 'sync'",
    ),
    'options' => array(
      'add' => 'Run `git add -p` after exporting. This lets you choose which config changes to sync for commit.',
      'commit' => 'Run `git add -A` and `git commit` after exporting.  This commits everything that was exported without prompting.',
      'message' => 'Commit comment for the exported configuration.  Optional; may only be used with --commit or --push.',
      'push' => 'Run `git push` after committing.  Implies --commit.',
      'remote' => array(
        'description' => 'The remote git branch to use to push changes.  Defaults to "origin".',
        'example-value' => 'origin',
      ),
      'branch' => array(
        'description' => 'Make commit on provided working branch. Ignored if used without --commit or --push.',
        'example-value' => 'branchname',
      ),
      'destination' => 'An arbitrary directory that should receive the exported files. An alternative to label argument.',
    ),
    'examples' => array(
      'drush config-export --destination' => 'Export configuration; Save files in a backup directory named config-export.',
    ),
  );

  $items['config-import'] = array(
    'description' => 'Import config from a config directory.',
    'arguments' => array(
      'label' => "A config directory label (i.e. a key in \$config_directories array in settings.php). Defaults to 'sync'",
    ),
    'options' => array(
      'preview' => array(
        'description' => 'Format for displaying proposed changes. Recognized values: list, diff. Defaults to list.',
        'example-value' => 'list',
      ),
      'source' => array(
        'description' => 'An arbitrary directory that holds the configuration files. An alternative to label argument',
      ),
      'partial' => array(
        'description' => 'Allows for partial config imports from the source directory. Only updates and new configs will be processed with this flag (missing configs will not be deleted).',
      ),
    ),
    'core' => array('8+'),
    'examples' => array(
      'drush config-import --partial' => 'Import configuration; do not remove missing configuration.',
    ),
    'aliases' => array('cim'),
  );

  $items['config-list'] = array(
    'description' => 'List config names by prefix.',
    'core' => array('8+'),
    'aliases' => array('cli'),
    'arguments' => array(
      'prefix' => 'The config prefix. For example, "system". No prefix will return all names in the system.',
    ),
    'examples' => array(
      'drush config-list system' => 'Return a list of all system config names.',
      'drush config-list "image.style"' => 'Return a list of all image styles.',
      'drush config-list --format="json"' => 'Return all config names as json.',
    ),
    'outputformat' => array(
      'default' => 'list',
      'pipe-format' => 'var_export',
      'output-data-type' => 'format-list',
    ),
  );

  $items['config-edit'] = $deps + array(
    'description' => 'Open a config file in a text editor. Edits are imported into active configuration after closing editor.',
    'core' => array('8+'),
    'aliases' => array('cedit'),
    'arguments' => array(
      'config-name' => 'The config object name, for example "system.site".',
    ),
    'global-options' => array('editor', 'bg'),
    'allow-additional-options' => array('config-import'),
    'examples' => array(
      'drush config-edit image.style.large' => 'Edit the image style configurations.',
      'drush config-edit' => 'Choose a config file to edit.',
      'drush config-edit --choice=2' => 'Edit the second file in the choice list.',
      'drush --bg config-edit image.style.large' => 'Return to shell prompt as soon as the editor window opens.',
    ),
  );

  $items['config-delete'] = array(
    'description' => 'Delete a configuration object.',
    'core' => array('8+'),
    'aliases' => array('cdel'),
    'arguments' => array(
      'config-name' => 'The config object name, for example "system.site".',
      'key' => 'A config key to clear, for example "page.front".',
    ),
    'required-arguments' => 1,
  );

  $items['config-pull'] = array(
    'description' => 'Export and transfer config from one environment to another.',
    // 'core' => array('8+'), Operates on remote sites so not possible to declare this locally.
    'drush dependencies' => array('config', 'core'), // core-rsync, core-execute.
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'aliases' => array('cpull'),
    'arguments' => array(
      'source' => 'A site-alias or the name of a subdirectory within /sites whose config you want to copy from.',
      'target' => 'A site-alias or the name of a subdirectory within /sites whose config you want to replace.',
    ),
    'required-arguments' => TRUE,
    'allow-additional-options' => array(), // Most options from config-export and core-rsync unusable.
    'examples' => array(
      'drush config-pull @prod @stage' => "Export config from @prod and transfer to @stage.",
      'drush config-pull @prod @self --label=vcs' => "Export config from @prod and transfer to the 'vcs' config directory of current site.",
    ),
    'options' => array(
      'safe' => 'Validate that there are no git uncommitted changes before proceeding',
      'label' => "A config directory label (i.e. a key in \$config_directories array in settings.php). Defaults to 'sync'",
      'runner' => 'Where to run the rsync command; defaults to the local site. Can also be "source" or "destination".',
    ),
    'topics' => array('docs-aliases', 'docs-config-exporting'),
  );

  return $items;
}

/**
 * Implements hook_drush_help_alter().
 */
function config_drush_help_alter(&$command) {
  // Hide additional-options which are for internal use only.
  if ($command['command'] == 'config-edit') {
    $command['options']['source']['hidden'] = TRUE;
    $command['options']['partial']['hidden'] = TRUE;
  }
}

/**
 * Config list command callback
 *
 * @param string $prefix
 *   The config prefix to retrieve, or empty to return all.
 */
function drush_config_list($prefix = '') {
  $names = \Drupal::configFactory()->listAll($prefix);

  if (empty($names)) {
    // Just in case there is no config.
    if (!$prefix) {
      return drush_set_error(dt('No config storage names found.'));
    }
    else {
      return drush_set_error(dt('No config storage names found matching @prefix', array('@prefix' => $prefix)));
    }
  }

  return $names;
}

/**
 * Config get command callback.
 *
 * @param $config_name
 *   The config name.
 * @param $key
 *   The config key.
 */
function drush_config_get($config_name, $key = NULL) {
  if (!isset($key)) {
    return drush_config_get_object($config_name);
  }
  else {
    return drush_config_get_value($config_name, $key);
  }
}

/**
 * Config delete command callback.
 *
 * @param $config_name
 *   The config name.
 * @param $key
 *   A config key to clear, for example "page.front".
 */
function drush_config_delete($config_name, $key = null) {
  $config =\Drupal::service('config.factory')->getEditable($config_name);
  if ($config->isNew()) {
    return drush_set_error('DRUSH_CONFIG_ERROR', dt('Configuration name not recognized. Use config-list to see all names.'));
  }
  else {
    if ($key) {
      if ($config->get($key) === null) {
        return drush_set_error('DRUSH_CONFIG_ERROR', dt('Configuration key !key not found.', array('!key' => $key)));
      }
      $config->clear($key)->save();
    }
    else {
      $config->delete();
    }
  }
}

/**
 * Config set command callback.
 *
 * @param $config_name
 *   The config name.
 * @param $key
 *   The config key.
 * @param $data
 *    The data to save to config.
 */
function drush_config_set($config_name, $key = NULL, $data = NULL) {
  // This hidden option is a convenient way to pass a value without passing a key.
  $data = drush_get_option('value', $data);

  if (!isset($data)) {
    return drush_set_error('DRUSH_CONFIG_ERROR', dt('No config value specified.'));
  }

  $config = \Drupal::configFactory()->getEditable($config_name);
  // Check to see if config key already exists.
  if ($config->get($key) === NULL) {
    $new_key = TRUE;
  }
  else {
    $new_key = FALSE;
  }

  // Special flag indicating that the value has been passed via STDIN.
  if ($data === '-') {
    $data = stream_get_contents(STDIN);
  }

  // Now, we parse the value.
  switch (drush_get_option('format', 'string')) {
    case 'yaml':
      $parser = new Parser();
      $data = $parser->parse($data, TRUE);
  }

  if (is_array($data) && drush_confirm(dt('Do you want to update or set multiple keys on !name config.', array('!name' => $config_name)))) {
    foreach ($data as $key => $value) {
      $config->set($key, $value);
    }
    return $config->save();
  }
  else {
    $confirmed = FALSE;
    if ($config->isNew() && drush_confirm(dt('!name config does not exist. Do you want to create a new config object?', array('!name' => $config_name)))) {
      $confirmed = TRUE;
    }
    elseif ($new_key && drush_confirm(dt('!key key does not exist in !name config. Do you want to create a new config key?', array('!key' => $key, '!name' => $config_name)))) {
      $confirmed = TRUE;
    }
    elseif (drush_confirm(dt('Do you want to update !key key in !name config?', array('!key' => $key, '!name' => $config_name)))) {
      $confirmed = TRUE;
    }
    if ($confirmed && !drush_get_context('DRUSH_SIMULATE')) {
      return $config->set($key, $data)->save();
    }
  }
}

/*
 * If provided $destination is not TRUE and not empty, make sure it is writable.
 */
function drush_config_export_validate() {
  $destination = drush_get_option('destination');
  if ($destination === TRUE) {
    // We create a dir in command callback. No need to validate.
    return;
  }

  if (!empty($destination)) {
    $additional = array();
    $values = drush_sitealias_evaluate_path($destination, $additional, TRUE);
    if (!isset($values['path'])) {
      return drush_set_error('config_export_target', 'The destination directory could not be evaluated.');
    }
    $destination = $values['path'];
    drush_set_option('destination', $destination);
    if (!file_exists($destination)) {
      $parent = dirname($destination);
      if (!is_dir($parent)) {
        return drush_set_error('config_export_target', 'The destination parent directory does not exist.');
      }
      if (!is_writable($parent)) {
        return drush_set_error('config_export_target', 'The destination parent directory is not writable.');
      }
    }
    else {
      if (!is_dir($destination)) {
        return drush_set_error('config_export_target', 'The destination is not a directory.');
      }
      if (!is_writable($destination)) {
        return drush_set_error('config_export_target', 'The destination directory is not writable.');
      }
    }
  }
}

/**
 * Command callback: Export config to specified directory (usually sync).
 */
function drush_config_export($destination = NULL) {
  global $config_directories;

  // Determine which target directory to use.
  if ($target = drush_get_option('destination')) {
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
    $choices = drush_map_assoc(array_keys($config_directories));
    unset($choices[CONFIG_ACTIVE_DIRECTORY]);
    if (!isset($destination) && count($choices) >= 2) {
      $destination = drush_choice($choices, 'Choose a destination.');
      if (empty($destination)) {
        return drush_user_abort();
      }
    }
    elseif (!isset($destination)) {
      $destination = CONFIG_SYNC_DIRECTORY;
    }
    $destination_dir = config_get_config_directory($destination);
  }

  // Prepare a new branch, if applicable
  $remote = drush_get_option('push', FALSE);
  $original_branch = FALSE;
  $branch = FALSE;
  if ($remote) {
    // Get the branch that we're on at the moment
    $result = drush_shell_cd_and_exec($destination_dir, 'git rev-parse --abbrev-ref HEAD');
    if (!$result) {
      return drush_set_error('DRUSH_CONFIG_EXPORT_NO_GIT', dt("The drush config-export command requires that the selected configuration directory !dir be under git revision control when using --commit or --push options.", array('!dir' => $destination_dir)));
    }
    $output = drush_shell_exec_output();
    $original_branch = $output[0];
    $branch = drush_get_option('branch', FALSE);
    if (!$branch) {
      $branch = $original_branch;
    }
    if ($branch != $original_branch) {
      // Switch to the working branch; create it if it does not exist.
      // We do NOT want to use -B here, as we do NOT want to reset the
      // branch if it already exists.
      $result = drush_shell_cd_and_exec($destination_dir, 'git checkout %s', $branch);
      if (!$result) {
        $result = drush_shell_cd_and_exec($destination_dir, 'git checkout -b %s', $branch);
      }
    }
  }

  // Do the actual config export operation
  $result = _drush_config_export($destination, $destination_dir, $branch);

  // Regardless of the result of the export, reset to our original branch.
  if ($branch != $original_branch) {
    drush_shell_cd_and_exec($destination_dir, 'git checkout %s', $original_branch);
  }

  return $result;
}

function _drush_config_export($destination, $destination_dir, $branch) {
  $commit = drush_get_option('commit');
  $comment = drush_get_option('message', 'Exported configuration.');
  if (count(glob($destination_dir . '/*')) > 0) {
    // Retrieve a list of differences between the active and target configuration (if any).
    if ($destination == CONFIG_SYNC_DIRECTORY) {
      $target_storage = \Drupal::service('config.storage.sync');
    }
    else {
      $target_storage = new FileStorage($destination_dir);
    }
    /** @var \Drupal\Core\Config\StorageInterface $active_storage */
    $active_storage = \Drupal::service('config.storage');
    $comparison_source = $active_storage;

    $config_comparer = new StorageComparer($comparison_source, $target_storage, \Drupal::service('config.manager'));
    if (!$config_comparer->createChangelist()->hasChanges()) {
      return drush_log(dt('The active configuration is identical to the configuration in the export directory (!target).', array('!target' => $destination_dir)), LogLevel::OK);
    }

    drush_print("Differences of the active config to the export directory:\n");
    $change_list = array();
    foreach ($config_comparer->getAllCollectionNames() as $collection) {
      $change_list[$collection] = $config_comparer->getChangelist(NULL, $collection);
    }
    // Print a table with changes in color, then re-generate again without
    // color to place in the commit comment.
    _drush_print_config_changes_table($change_list);
    $tbl = _drush_format_config_changes_table($change_list);
    $output = $tbl->getTable();
    if (!stristr(PHP_OS, 'WIN')) {
      $output = str_replace("\r\n", PHP_EOL, $output);
    }
    $comment .= "\n\n$output";

    if (!$commit && !drush_confirm(dt('The .yml files in your export directory (!target) will be deleted and replaced with the active config.', array('!target' => $destination_dir)))) {
      return drush_user_abort();
    }
    // Only delete .yml files, and not .htaccess or .git.
    $target_storage->deleteAll();
  }

  // Write all .yml files.
  $source_storage = \Drupal::service('config.storage');
  if ($destination == CONFIG_SYNC_DIRECTORY) {
    $destination_storage = \Drupal::service('config.storage.sync');
  }
  else {
    $destination_storage = new FileStorage($destination_dir);
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

  drush_log(dt('Configuration successfully exported to !target.', array('!target' => $destination_dir)), LogLevel::SUCCESS);
  drush_backend_set_result($destination_dir);

  // Commit and push, or add exported configuration if requested.
  $remote = drush_get_option('push', FALSE);
  if ($commit || $remote) {
    // There must be changed files at the destination dir; if there are not, then
    // we will skip the commit-and-push step
    $result = drush_shell_cd_and_exec($destination_dir, 'git status --porcelain .');
    if (!$result) {
      return drush_set_error('DRUSH_CONFIG_EXPORT_FAILURE', dt("`git status` failed."));
    }
    $uncommitted_changes = drush_shell_exec_output();
    if (!empty($uncommitted_changes)) {
      $result = drush_shell_cd_and_exec($destination_dir, 'git add -A .');
      if (!$result) {
        return drush_set_error('DRUSH_CONFIG_EXPORT_FAILURE', dt("`git add -A` failed."));
      }
      $comment_file = drush_save_data_to_temp_file($comment);
      $result = drush_shell_cd_and_exec($destination_dir, 'git commit --file=%s', $comment_file);
      if (!$result) {
        return drush_set_error('DRUSH_CONFIG_EXPORT_FAILURE', dt("`git commit` failed.  Output:\n\n!output", array('!output' => implode("\n", drush_shell_exec_output()))));
      }
      if ($remote) {
        // Remote might be FALSE, if --push was not specified, or
        // it might be TRUE if --push was not given a value.
        if (!is_string($remote)) {
          $remote = 'origin';
        }
        $result = drush_shell_cd_and_exec($destination_dir, 'git push --set-upstream %s %s', $remote, $branch);
        if (!$result) {
          return drush_set_error('DRUSH_CONFIG_EXPORT_FAILURE', dt("`git push` failed."));
        }
      }
    }
  }
  elseif (drush_get_option('add')) {
    drush_shell_exec_interactive('git add -p %s', $destination_dir);
  }

  $values = array(
    'destination' => $destination_dir,
  );
  return $values;
}

function drush_config_import_validate() {
  drush_include_engine('drupal', 'environment');
  if (drush_get_option('partial') && !drush_module_exists('config')) {
    return drush_set_error('config_import_partial', 'Enable the config module in order to use the --partial option.');
  }
  if ($source = drush_get_option('source')) {
    if (!file_exists($source)) {
      return drush_set_error('config_import_target', 'The source directory does not exist.');
    }
    if (!is_dir($source)) {
      return drush_set_error('config_import_target', 'The source is not a directory.');
    }
  }
}

/**
 * Command callback. Import from specified config directory (defaults to sync).
 */
function drush_config_import($source = NULL) {
  global $config_directories;

  // Determine source directory.
  if ($target = drush_get_option('source')) {
    $source_dir = $target;
  }
  else {
    $choices = drush_map_assoc(array_keys($config_directories));
    unset($choices[CONFIG_ACTIVE_DIRECTORY]);
    if (!isset($source) && count($choices) >= 2) {
      $source= drush_choice($choices, 'Choose a source.');
      if (empty($source)) {
        return drush_user_abort();
      }
    }
    elseif (!isset($source)) {
      $source = CONFIG_SYNC_DIRECTORY;
    }
    $source_dir = config_get_config_directory($source);
  }

  if ($source == CONFIG_SYNC_DIRECTORY) {
    $source_storage = \Drupal::service('config.storage.sync');
  }
  else {
    $source_storage = new FileStorage($source_dir);
  }

  // Determine $source_storage in partial and non-partial cases.
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
    return drush_log(dt('There are no changes to import.'), LogLevel::OK);
  }

  if (drush_get_option('preview', 'list') == 'list') {
    $change_list = array();
    foreach ($storage_comparer->getAllCollectionNames() as $collection) {
      $change_list[$collection] = $storage_comparer->getChangelist(NULL, $collection);
    }
    _drush_print_config_changes_table($change_list);
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

  if (drush_confirm(dt('Import the listed configuration changes?'))) {
    return drush_op('_drush_config_import', $storage_comparer);
  }
}

// Copied from submitForm() at /core/modules/config/src/Form/ConfigSync.php
function _drush_config_import(StorageComparer $storage_comparer) {
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
    drush_log('Another request may be synchronizing configuration already.', LogLevel::WARNING);
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
              drush_log(str_replace('Synchronizing', 'Synchronized', (string)$context['message']), LogLevel::OK);
            }
          } while ($context['finished'] < 1);
        }
      }
      if ($config_importer->getErrors()) {
        throw new \Drupal\Core\Config\ConfigException('Errors occurred during import');
      }
      else {
        drush_log('The configuration was imported successfully.', LogLevel::SUCCESS);
      }
    }
    catch (ConfigException $e) {
      // Return a negative result for UI purposes. We do not differentiate
      // between an actual synchronization error and a failed lock, because
      // concurrent synchronizations are an edge-case happening only when
      // multiple developers or site builders attempt to do it without
      // coordinating.
      $message = 'The import failed due for the following reasons:' . "\n";
      $message .= implode("\n", $config_importer->getErrors());

      watchdog_exception('config_import', $e);
      return drush_set_error('config_import_fail', $message);
    }
  }
}

/**
 * Edit command callback.
 */
function drush_config_edit($config_name = '') {
  // Identify and validate input.
  if ($config_name) {
    $config = \Drupal::configFactory()->get($config_name);
    if ($config->isNew()) {
      return drush_set_error(dt('Config !name does not exist', array('!name' => $config_name)));
    }
  }
  else {
    $config_names = \Drupal::configFactory()->listAll();
    $choice = drush_choice($config_names, 'Choose a configuration.');
    if (empty($choice)) {
      return drush_user_abort();
    }
    else {
      $config_name = $config_names[$choice];
      $config = \Drupal::configFactory()->get($config_name);
    }
  }

  $active_storage = $config->getStorage();
  $contents = $active_storage->read($config_name);

  // Write tmp YAML file for editing
  $temp_dir = drush_tempdir();
  $temp_storage = new FileStorage($temp_dir);
  $temp_storage->write($config_name, $contents);

  $exec = drush_get_editor();
  drush_shell_exec_interactive($exec, $temp_storage->getFilePath($config_name));

  // Perform import operation if user did not immediately exit editor.
  if (!drush_get_option('bg', FALSE)) {
    $options = drush_redispatch_get_options() + array('partial' => TRUE, 'source' => $temp_dir);
    $backend_options = array('interactive' => TRUE);
    return (bool) drush_invoke_process('@self', 'config-import', array(), $options, $backend_options);
  }
}

/**
 * Config pull validate callback
 *
 */
function drush_config_pull_validate($source, $destination) {
  if (drush_get_option('safe')) {
    $return = drush_invoke_process($destination, 'core-execute', array('git diff --quiet'), array('escape' => 0));
    if ($return['error_status']) {
      return drush_set_error('DRUSH_GIT_DIRTY', 'There are uncommitted changes in your git working copy.');
    }
  }
}

/**
 * Config pull command callback
 *
 * @param string $label
 *   The config label which receives the transferred files.
 */
function drush_config_pull($source, $destination) {
  // @todo drush_redispatch_get_options() assumes you will execute same command. Not good.
  $global_options = drush_redispatch_get_options() + array(
    'strict' => 0,
  );

  // @todo If either call is made interactive, we don't get an $return['object'] back.
  $backend_options = array('interactive' => FALSE);
  if (drush_get_context('DRUSH_SIMULATE')) {
    $backend_options['backend-simulate'] = TRUE;
  }

  $export_options = array(
    // Use the standard backup directory on Destination.
    'destination' => TRUE,
  );
  drush_log(dt('Starting to export configuration on Target.'), LogLevel::OK);
  $return = drush_invoke_process($source, 'config-export', array(), $global_options + $export_options, $backend_options);
  if ($return['error_status']) {
    return drush_set_error('DRUSH_CONFIG_PULL_EXPORT_FAILED', dt('Config-export failed.'));
  }
  else {
    // Trailing slash assures that transfer files and not the containing dir.
    $export_path = $return['object'] . '/';
  }

  $rsync_options = array(
    'remove-source-files' => TRUE,
    'delete' => TRUE,
    'exclude-paths' => '.htaccess',
    'yes' => TRUE,  // No need to prompt as destination is always the target config directory.
  );
  $label = drush_get_option('label', 'sync');
  $runner = drush_get_runner($source, $destination, drush_get_option('runner', FALSE));
  drush_log(dt('Starting to rsync configuration files from !source to !dest.', array('!source' => $source, '!dest' => $destination)), LogLevel::OK);
  // This comment applies similarly to sql-sync's use of core-rsync.
  // Since core-rsync is a strict-handling command and drush_invoke_process() puts options at end, we can't send along cli options to rsync.
  // Alternatively, add options like --ssh-options to a site alias (usually on the machine that initiates the sql-sync).
  $return = drush_invoke_process($runner, 'core-rsync', array("$source:$export_path", "$destination:%config-$label"), $rsync_options);
  if ($return['error_status']) {
    return drush_set_error('DRUSH_CONFIG_PULL_RSYNC_FAILED', dt('Config-pull rsync failed.'));
  }

  drush_backend_set_result($return['object']);
}

/**
 * Show and return a config object
 *
 * @param $config_name
 *   The config object name.
 */
function drush_config_get_object($config_name) {
  $source = drush_get_option('source', 'active');
  $include_overridden = drush_get_option('include-overridden', FALSE);

  if ($include_overridden) {
    // Displaying overrides only applies to active storage.
    $config = \Drupal::config($config_name);
    $data = $config->get();
  }
  elseif ($source == 'active') {
    $config = \Drupal::service('config.storage');
    $data = $config->read($config_name);
  }
  elseif ($source == 'sync') {
    $config = \Drupal::service('config.storage.sync');
    $data = $config->read($config_name);
  }
  else {
    return drush_set_error(dt('Unknown value !value for config source.', array('!value' => $source)));
  }

  if ($data === FALSE) {
    return drush_set_error(dt('Config !name does not exist in !source configuration.', array('!name' => $config_name, '!source' => $source)));
  }
  if (empty($data)) {
    drush_log(dt('Config !name exists but has no data.', array('!name' => $config_name)), LogLevel::NOTICE);
    return;
  }
  return $data;
}

/**
 * Show and return a value from config system.
 *
 * @param $config_name
 *   The config name.
 * @param $key
 *   The config key.
 */
function drush_config_get_value($config_name, $key) {
  $data = drush_config_get_object($config_name);
  $parts = explode('.', $key);
  if (count($parts) == 1) {
    $value =  isset($data[$key]) ? $data[$key] : NULL;
  }
  else {
    $value = NestedArray::getValue($data, $parts, $key_exists);
    $value = $key_exists ? $value : NULL;
  }

  $returns[$config_name . ':' . $key] = $value;

  if ($value === NULL) {
    return drush_set_error('DRUSH_CONFIG_ERROR', dt('No matching key found in !name config.', array('!name' => $config_name)));
  }
  else {
    return $returns;
  }
}

/**
 * Print a table of config changes.
 *
 * @param array $config_changes
 *   An array of changes keyed by collection.
 */
function _drush_format_config_changes_table(array $config_changes, $use_color = FALSE) {
  if (!$use_color) {
    $red = "%s";
    $yellow = "%s";
    $green = "%s";
  }
  else {
    $red = "\033[31;40m\033[1m%s\033[0m";
    $yellow = "\033[1;33;40m\033[1m%s\033[0m";
    $green = "\033[1;32;40m\033[1m%s\033[0m";
  }

  $rows = array();
  $rows[] = array('Collection', 'Config', 'Operation');
  foreach ($config_changes as $collection => $changes) {
    foreach ($changes as $change => $configs) {
      switch ($change) {
        case 'delete':
          $colour = $red;
          break;
        case 'update':
          $colour = $yellow;
          break;
        case 'create':
          $colour = $green;
          break;
        default:
          $colour = "%s";
          break;
      }
      foreach($configs as $config) {
        $rows[] = array(
          $collection,
          $config,
          sprintf($colour, $change)
        );
      }
    }
  }
  $tbl = _drush_format_table($rows);
  return $tbl;
}

/**
 * Print a table of config changes.
 *
 * @param array $config_changes
 *   An array of changes keyed by collection.
 */
function _drush_print_config_changes_table(array $config_changes) {
  $tbl =  _drush_format_config_changes_table($config_changes, !drush_get_context('DRUSH_NOCOLOR'));

  $output = $tbl->getTable();
  if (!stristr(PHP_OS, 'WIN')) {
    $output = str_replace("\r\n", PHP_EOL, $output);
  }

  drush_print(rtrim($output));
  return $tbl;
}

/**
 * Command argument complete callback.
 */
function config_config_get_complete() {
  return _drush_config_names_complete();
}

/**
 * Command argument complete callback.
 */
function config_config_set_complete() {
  return _drush_config_names_complete();
}

/**
 * Command argument complete callback.
 */
function config_config_view_complete() {
  return _drush_config_names_complete();
}

/**
 * Command argument complete callback.
 */
function config_config_edit_complete() {
  return _drush_config_names_complete();
}

/**
 * Command argument complete callback.
 */
function config_config_import_complete() {
  return _drush_config_directories_complete();
}

/**
 * Command argument complete callback.
 */
function config_config_export_complete() {
  return _drush_config_directories_complete();
}

/**
 * Command argument complete callback.
 */
function config_config_pull_complete() {
  return array('values' => array_keys(_drush_sitealias_all_list()));
}

/**
 * Helper function for command argument complete callback.
 *
 * @return
 *   Array of available config directories.
 */
function _drush_config_directories_complete() {
  drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION);
  global $config_directories;
  return array('values' => array_keys($config_directories));
}

/**
 * Helper function for command argument complete callback.
 *
 * @return
 *   Array of available config names.
 */
function _drush_config_names_complete() {
  drush_bootstrap_max();
  return array('values' => $storage = \Drupal::service('config.storage')->listAll());
}
