<?php

/**
 * @file
 *   Provides CMI commands.
 */

use Drupal\Core\Config\Config;
use Drupal\Core\Config\DatabaseStorage;
use Drupal\Core\Config\FileStorage;

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
      'value' => 'The value to assign to the config key.'
    ),
    'required-arguments' => 1,
    'options' => array(
      'format' => array(
        'description' => 'Format to parse the object. Use "string" for string (default), and "yaml" for YAML.',
        'example-value' => 'yaml',
        'value' => 'required',
      ),
      'stdin' => 'Get value from STDIN.',
    ),
    'examples' => array(
      'drush config-set system.site page.front node' => 'Sets system.site:page.front to node.',
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
    // Just incase there is no config.
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
  // Allow stdin to be use to set config keys.
  if (!isset($key) && !drush_get_option('stdin')) {
    return drush_set_error('DRUSH_CONFIG_ERROR', dt('No config key specified.'));
  }
  if (!isset($data) && !drush_get_option('stdin')) {
    return drush_set_error('DRUSH_CONFIG_ERROR', dt('No config value specified.'));
  }

  $config = config($config_name);
  // Check to see if config key already exists.
  if ($config->get($key) === NULL) {
    $new_key = TRUE;
  }
  else {
    $new_key = FALSE;
  }

  if (drush_get_option('stdin')) {
    $data = stream_get_contents(STDIN);
  }

  // Now, we parse the value.
  switch (drush_get_option('format', 'string')) {
    case 'yaml':
      $data = Yaml::parse($data);
      break;
  }

  if (is_array($data) && drush_confirm(dt('Do you want to update or set mulltiple keys on !name config.', array('!name' => $config_name)))) {
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
 * Import config command callback.
 */
function drush_config_import() {
  // Retrieve a list of differences between the active and staged configuration.
  $source_storage = drupal_container()->get('config.storage.staging');
  $target_storage = drupal_container()->get('config.storage');
  $config_changes = config_sync_get_changes($source_storage, $target_storage);
  if ($config_changes === FALSE) {
    return drush_log(dt('There are no changes to import.'), 'ok');
  }
  _drush_print_config_changes_table($config_changes);
  if (drush_confirm(dt('Import the listed configuration changes?'))) {
    return drush_op('config_import');
  }
}

/**
 * Edit command callback.
 */
function drush_config_edit($config_name = '') {
  // Identify and validate input.
  if ($config_name) {
    $config = config($config_name);
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

  $active_storage = drupal_container()->get('config.storage');
  $staging_storage = drupal_container()->get('config.storage.staging');
  $filepath = $staging_storage->getFilePath($config_name);
  $staging_directory = config_get_config_directory(CONFIG_STAGING_DIRECTORY);

  // Wipe staging directory before we write our work file.
  drush_delete_dir_contents($staging_directory, TRUE);

  // Copy from active storage to staging directory.
  $staging_storage->write($config_name, $active_storage->read($config_name));

  $exec = drush_get_editor();
  drush_shell_exec_interactive($exec, $filepath, $filepath);
  // Perform import operation if user did not immediately exit editor.
  if (!drush_get_option('bg', FALSE)) {
    $result = config_import();
    if ($result === NULL) {
      drush_log('No changes to import.', 'ok');
    }
    elseif ($result === FALSE) {
      drush_set_error('Error importing new configuration.');
    }
  }

  // Cleanup staging.
  drush_delete_dir_contents($staging_directory, TRUE);
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
  drush_set_default_outputformat('labeled-export');
  $config = config($config_name);
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
      case 'change':
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
