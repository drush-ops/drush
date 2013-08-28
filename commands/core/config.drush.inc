<?php

/**
 * @file
 *   Provides Configuration Management commands.
 */

use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\FileStorage;
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
  $items['config-get'] = array(
    'description' => 'Display a config value, or a whole configuration object.',
    'arguments' => array(
      'config-name' => 'The config object name, for example "system.site".',
      'key' => 'The config key, for example "page.front". Optional.',
    ),
    'required-arguments' => 1,
    'options' => array(
      'source' => array(
        'description' => 'The config storage source to read. Use "database" for database storage, or "file" for file stroage',
        'example-value' => 'database',
        'value' => 'required',
      ),
    ),
    'examples' => array(
      'drush config-view system.site' => 'Displays the system.site config.',
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
    'description' => 'Set config value in the active store. This does not invoke config_sync_changes().',
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

  $items['config-import'] = array(
    'description' => 'Import config from the file store.',
    'core' => array('8+'),
    'aliases' => array('cim')
  );

  $items['config-list'] = array(
    'description' => 'List config names by prefix.',
    'core' => array('8+'),
    'aliases' => array('cli'),
    'arguments' => array(
      'prefix' => 'The config prefix. For example, "system". No prefix will return all names in the system.',
    ),
    'options' => array(
      'format' => array(
        'description' => 'Define the output format. Known formats are: json, print_r, and export.',
      ),
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

  $items['config-edit'] = array(
    'description' => 'Open a config file in a text editor. Edits are imported into Drupal after closing editor.',
    'core' => array('8+'),
    'aliases' => array('cedit'),
    'arguments' => array(
      'config-name' => 'The config object name, for example "system.site".',
    ),
    'options' => array(
      'bg' => 'Run editor in the background. Does not work with editors such as `vi` that run in the terminal. Supresses config-import at the end.',
    ),
    'examples' => array(
      'drush config-edit image.style.large' => 'Edit the image style configurations.',
      'drush config-edit' => 'Choose a config file to edit.',
      'drush config-edit --choice=2' => 'Edit the second file in the choice list.',
      'drush --bg config-edit image.style.large' => 'Return to shell prompt as soon as the editor window opens.',
    ),
  );

  return $items;
}

/**
 * Config list command callback
 *
 * @param string $prefix
 *   The config prefix to retrieve, or empty to return all.
 */
function drush_config_list($prefix = '') {
  $names = config_get_storage_names_with_prefix($prefix);

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
  if (is_null($key)) {
    return drush_config_get_object($config_name);
  }
  else {
    return drush_config_get_value($config_name, $key);
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

  if (is_null($data)) {
    return drush_set_error('DRUSH_CONFIG_ERROR', dt('No config value specified.'));
  }

  $config = Drupal::config($config_name);
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
      if (!class_exists('Symfony\Component\Yaml')) {
        // For Drush PSR-0 and Composer information, see http://drupal.org/node/1316322.
        $path = DRUSH_BASE_PATH . '/lib/Yaml-' . DRUSH_YAML_VERSION;
        require_once "$path/Yaml.php";
      }
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

/**
 * Command callback. Import from staging directory.
 */
function drush_config_import() {
  // Retrieve a list of differences between the active and staged configuration.
  $config_comparer = new StorageComparer(Drupal::service('config.storage.staging'), Drupal::service('config.storage'));
  if (!$config_comparer->createChangelist()->hasChanges()) {
    return drush_log(dt('There are no changes to import.'), 'ok');
  }
  $change_list = $config_comparer->getChangelist();
   _drush_print_config_changes_table($change_list);
  if (drush_confirm(dt('Import the listed configuration changes?'))) {
    return drush_op('_drush_config_import', $config_comparer);
  }
}

// Copied from submitForm() at /core/modules/config/lib/Drupal/config/Form/ConfigSync.php
function _drush_config_import(StorageComparer $storage_comparer) {
  $config_importer = new ConfigImporter(
    $storage_comparer,
    Drupal::service('event_dispatcher'),
    Drupal::service('config.factory'),
    Drupal::entityManager(),
    Drupal::lock()
  );
  if ($config_importer->alreadyImporting()) {
    drush_log('Another request may be synchronizing configuration already.', 'warning');
  }
  else{
    try {
      $config_importer->import();
      drupal_flush_all_caches();
      drush_log('The configuration was imported successfully.', 'success');
    }
    catch (ConfigException $e) {
      // Return a negative result for UI purposes. We do not differentiate
      // between an actual synchronization error and a failed lock, because
      // concurrent synchronizations are an edge-case happening only when
      // multiple developers or site builders attempt to do it without
      // coordinating.
      watchdog_exception('config_import', $e);
      return drush_set_error('config_import_fail', 'The import failed due to an error. Any errors have been logged.');
    }
  }
}

/**
 * Edit command callback.
 */
function drush_config_edit($config_name = '') {
  // Identify and validate input.
  if ($config_name) {
    $config = Drupal::config($config_name);
    if ($config->isNew()) {
      return drush_set_error(dt('Config !name does not exist', array('!name' => $config_name)));
    }
  }
  else {
    $config_names = config_get_storage_names_with_prefix();
    $choice = drush_choice($config_names, 'Choose a configuration.');
    if (empty($choice)) {
      return drush_user_abort();
    }
    else {
      $config_name = $config_names[$choice];
    }
  }

  $active_storage = $config->getStorage();
  $contents = $active_storage->read($config_name);

  // Write tmp YAML file for editing.
  $temp_storage = new FileStorage(drush_tempdir());
  $temp_storage->write($config_name, $contents);

  // $filepath = drush_save_data_to_temp_file();
  $exec = drush_get_editor();
  drush_shell_exec_interactive($exec, $temp_storage->getFilePath($config_name));
  // Perform import operation if user did not immediately exit editor.
  if (!drush_get_option('bg', FALSE)) {
    $new_data = $temp_storage->read($config_name);
    $temp_storage->delete($config_name);
    $config->setData($new_data);
    $config->save();
  }
}

/* Helper functions */

/**
 * Show and return a config object
 *
 * @param $config_name
 *   The config object name.
 */
function drush_config_get_object($config_name) {
  $source = drush_get_option('source', 'database');
  if ($source == 'database') {
    $config = drupal_container()->get('config.storage');
  }
  elseif ($source == 'file') {
    $config = drupal_container()->get('config.storage.staging');
  }

  $data = $config->read($config_name);
  if ($data === FALSE) {
    return drush_set_error(dt('Config !name does not exist in the !source system.', array('!name' => $config_name, '!source' => $source)));
  }
  if (empty($data)) {
    drush_log(dt('Config !name exists but has no data.', array('!name' => $config_name)), 'notice');
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
  $config = Drupal::config($config_name);
  if ($config->isNew()) {
    return drush_set_error(dt('Config !name does not exist', array('!name' => $config_name)));
  }
  $value = $config->get($key);
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
 *   An array of changes.
 */
function _drush_print_config_changes_table(array $config_changes) {
  if (drush_get_context('DRUSH_NOCOLOR')) {
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
  $rows[] =  array('Config', 'Operation');
  foreach ($config_changes as $change => $configs) {
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
        $config,
        sprintf($colour, $change)
      );
    }
  }
  drush_print_table($rows, TRUE);
}

/**
 * Command argument complete callback.
 */
function drush_config_get_complete() {
  return _drush_config_complete();
}

/**
 * Command argument complete callback.
 */
function drush_config_set_complete() {
  return _drush_config_complete();
}

/**
 * Command argument complete callback.
 */
function drush_config_view_complete() {
  return _drush_config_complete();
}

/**
 * Command argument complete callback.
 */
function drush_config_edit_complete() {
  return _drush_config_complete();
}

/**
 * Helper function for command argument complete callback.
 *
 * @return
 *   Array of available config names.
 */
function _drush_config_complete() {
  return array('values' => drupal_container()->get('config.storage')->listAll());
}
