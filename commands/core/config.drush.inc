<?php

/**
 * @file
 *   Provides CMI commands.
 */

use Drupal\Core\Config\DatabaseStorage;
use Drupal\Core\Config\FileStorage;
use Symfony\Component\Yaml\Yaml;

/**
 * Implementation of hook_drush_command().
 */
function config_drush_command() {
  $items['config-view'] = array(
    'description' => 'Display config object.',
    'arguments' => array(
      'config-name' => 'The config object name, for example "system.site".',
    ),
    'required-arguments' => 1,
    'options' => array(
      'format' => array(
        'description' => 'Format the object. Use "yaml" for YAML (default), "php" for a serialized array, or "dump" for a php array declaration.',
        'example-value' => 'yaml',
        'value' => 'required',
      ),
      'source' => array(
        'description' => 'The config storage source to read. Use "database" for database storage, or "file" for file stroage',
        'example-value' => 'database',
        'value' => 'required',
      ),
    ),
    'examples' => array(
      'drush config-view system.site' => 'Displays the system.site config.',
    ),
    'aliases' => array('cview'),
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

  $items['config-get'] = array(
    'description' => 'Display config value.',
    'arguments' => array(
      'config-name' => 'The config object name, for example "system.site".',
      'key' => 'The config key, for example "page.front".',
    ),
    'required-arguments' => 2,
    'examples' => array(
      'drush config-get system.site page.front' => 'gets system.site:page.front value.',
    ),
    'aliases' => array('cget'),
    'core' => array('8+'),
  );

  $items['config-export'] = array(
    'description' => 'Export config from the active store to the file store.',
    'core' => array('8+'),
    'aliases' => array('cex'),
  );

  $items['config-import'] = array(
    'description' => 'Import config from the file store.',
    'core' => array('8+'),
    'aliases' => array('cim')
  );

  return $items;
}

/**
 * Config view command callback.
 *
 * @param $config_name
 *   The config object name.
 */
function drush_config_view($config_name) {
  $source = drush_get_option('source', 'database');
  if ($source == 'database') {
    $config = new DatabaseStorage();
  }
  elseif ($source == 'file') {
    $config = new FileStorage();
  }

  $data = $config->read($config_name);
  if ($data === FALSE) {
    return drush_set_error(dt('Config !name does not exist in the !source system.', array('!name' => $config_name, '!source' => $source)));
  }
  if (empty($data)) {
    drush_print(dt('Config !name exists but has no data.', array('!name' => $config_name)));
    return;
  }
  $format = drush_get_option('format', 'yaml');
  switch ($format) {
    case 'yaml':
      // As config appears in the file system.
      $output = Yaml::dump($config->read($config_name));
      break;
    case 'php':
      // As config appears in the database.
      $output = serialize($data);
      break;
    case 'dump':
      include_once DRUPAL_ROOT . '/core/includes/utility.inc';
      // Do this so it's super easy to copy and paste into settings.php for
      // evil global overrides.
      $data = array(
        $config_name => $data,
      );
      $output = drupal_var_export($data);
      break;
    default:
      return drush_set_error(dt('Unsupported config dump format: !format', array('!format' => $format)));
      break;
  }
  drush_print($output);
  return $output;
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
 * Config get command callback.
 *
 * @param $config_name
 *   The config name.
 * @param $key
 *   The config key.
 */
function drush_config_get($config_name, $key) {
  $config = config($config_name);
  if ($config->isNew()) {
    return drush_set_error(dt('Config !name does not exist', array('!name' => $config_name)));
  }
  $value = $config->get($key);
  $pipe = array();
  $data = array(
    $key => $value,
  );
  $pipe[] = drush_format($data, $config_name, 'config');
  drush_print(drush_format($value, $config_name . ':' . $key));
  $returns[$config_name . ':' . $key] = $value;

  if ($value === NULL) {
    return drush_set_error('DRUSH_CONFIG_ERROR', dt('No matching key found in !name config.', array('!name' => $config_name)));
  }
  else {
    return $returns;
  }
}

/**
 * Import config ommand callback.
 */
function drush_config_export() {
  // Retrieve a list of differences between the active store and FileStorage.
  $source_storage = drupal_container()->get('config.storage');
  $target_storage = new FileStorage();
  $config_changes = config_sync_get_changes($source_storage, $target_storage);
  if ($config_changes === FALSE) {
    drush_print(dt('There are no changes to export.'));
  }
  _drush_print_config_changes_table($config_changes);
  if (drush_confirm(dt('Export the listed configuration changes?'))) {
    return drush_op('config_export');
  }
}

/**
 * Export config command callback.
 */
function drush_config_import() {
  // Retrieve a list of differences between the active store and FileStorage.
  $source_storage = new FileStorage();
  $target_storage = drupal_container()->get('config.storage');
  $config_changes = config_sync_get_changes($source_storage, $target_storage);
  if ($config_changes === FALSE) {
    return drush_print(dt('There are no changes to import.'));
  }
  _drush_print_config_changes_table($config_changes);
  if (drush_confirm(dt('Import the listed configuration changes?'))) {
    return drush_op('config_import');
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

/*
 * Command argument complete callback.
 */
function drush_config_get_complete() {
  return _drush_config_complete();
}

/*
 * Command argument complete callback.
 */
function drush_config_set_complete() {
  return _drush_config_complete();
}

/*
 * Command argument complete callback.
 */
function drush_config_view_complete() {
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
