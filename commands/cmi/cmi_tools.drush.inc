<?php

/**
 * @file
 * Config-export-plus and config-import-plus implementation.
 *
 * Contains turbo-charged config commands for a better partial workflow. This
 * has been ported from the work of Lee Rowlands (larowlan) into drush from
 * drush_cmi_tools on github by scott_euser.
 *
 * @author Lee Rowlands (larowlan)
 *   https://www.drupal.org/u/larowlan.
 * @link https://github.com/previousnext/drush_cmi_tools/
 *   Drush CMI Tools on github.
 */

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use Drupal\config\StorageReplaceDataWrapper;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageComparer;
use Drush\Config\StorageWrapper;
use Drush\Log\LogLevel;

/**
 * Implements hook_drush_command().
 */
function cmi_tools_drush_command() {
  $deps = ['drupal dependencies' => ['config']];
  $items['config-export-plus'] = [
    'description' => 'Export configuration to a directory and apply an ignore list.',
    'core' => ['8+'],
    'aliases' => ['cexy'],
    'options' => [
      'destination' => 'An arbitrary directory that should receive the exported files. An alternative to label argument.',
      'skip-modules' => 'A list of modules to ignore during export (e.g. to avoid listing dev-only modules in exported configuration).',
      'ignore-list' => [
        'description' => 'Path to YAML file containing config to ignore from exports',
      ],
    ],
    'examples' => [
      'drush config-export-plus --destination=/some/folder --ignore-list=./config-ignore.yml' => 'Export configuration; Save files in a backup directory named config-export.',
    ],
  ];

  $items['config-import-plus'] = $deps + [
    'description' => 'Import config from a config directory resepecting live content and a delete list.',
    'options' => [
      'preview' => [
        'description' => 'Format for displaying proposed changes. Recognized values: list, diff. Defaults to list.',
        'example-value' => 'list',
      ],
      'source' => [
        'description' => 'An arbitrary directory that holds the configuration files.',
      ],
      'delete-list' => [
        'description' => 'Path to YAML file containing config to delete before importing. Useful when you need to remove items from active config store before importing.',
      ],
      'install' => [
        'description' => 'Directory that holds the files to import once only.',
      ],
      'skip-modules' => 'A list of modules to ignore during import (e.g. to avoid disabling dev-only modules that are not enabled in the imported configuration).',
    ],
    'core' => ['8+'],
    'examples' => [
      'drush config-import-plus --skip-modules=devel --delete-list=./config-delete.yml --install=/some/install/folder --source=/some/export/folder' => 'Import configuration; do not enable or disable the devel module, regardless of whether or not it appears in the imported list of enabled modules.',
    ],
    'aliases' => ['cimy'],
  ];
  return $items;
}

/**
 * Perform export.
 */
function drush_cmi_tools_config_export_plus($destination = NULL) {
  drush_log(dt('Starting Exporting.'), LogLevel::DEBUG);
  // Do the actual config export operation
  // Determine which target directory to use.
  if (($target = drush_get_option('destination')) && $target !== TRUE) {
    $destination_dir = $target;
    // It is important to be able to specify a destination directory that
    // does not exist yet, for exporting on remote systems.
    drush_mkdir($destination_dir);
  }
  else {
    return drush_log(dt('You must provide a --destination option'), LogLevel::ERROR);
  }
  $patterns = [];
  if ($ignore_list = drush_get_option('ignore-list')) {
    if (!is_file($ignore_list)) {
      return drush_log(dt('The file specified in --ignore-list option does not exist.'), LogLevel::ERROR);
    }
    if ($string = file_get_contents($ignore_list)) {
      $ignore_list_error = FALSE;
      $parsed = FALSE;
      try {
        $parsed = Yaml::decode($string);
      }
      catch (InvalidDataTypeException $e) {
        $ignore_list_error = TRUE;
      }
      if (!isset($parsed['ignore']) || !is_array($parsed['ignore'])) {
        $ignore_list_error = TRUE;
      }
      if ($ignore_list_error) {
        return drush_log(dt('The file specified in --ignore-list option is in the wrong format. It must be valid YAML with a top-level ignore key.'), LogLevel::ERROR);
      }
      foreach ($parsed['ignore'] as $ignore) {
        // Allow for accidental .yml extension.
        if (substr($ignore, -4) === '.yml') {
          $ignore = substr($ignore, 0, -4);
        }
        $patterns[] = '/' . str_replace('\*', '(.*)', preg_quote($ignore)) . '\.yml/';
      }
    }
  }

  $result = _drush_config_export($destination, $destination_dir, FALSE);
  $file_service = \Drupal::service('file_system');
  foreach ($patterns as $pattern) {
    foreach (file_scan_directory($destination_dir, $pattern) as $file_url => $file) {
      $file_service->unlink($file_url);
      drush_log("Removed $file_url according to ignore list.", LogLevel::OK);
    }
  }

  return $result;
}

/**
 * Perform import.
 */
function drush_cmi_tools_config_import_plus($destination = NULL) {
  drush_log(dt('Starting import'), LogLevel::DEBUG);
  // Determine source directory.
  if ($target = drush_get_option('source')) {
    $source_dir = $target;
  }
  else {
    return drush_log(dt('You must provide a --source option'), LogLevel::ERROR);
  }
  /** @var \Drupal\Core\Config\StorageInterface $active_storage */
  $active_storage = \Drupal::service('config.storage');
  $source_storage = new StorageReplaceDataWrapper($active_storage);
  $file_storage = new FileStorage($source_dir);
  foreach ($file_storage->listAll() as $name) {
    $data = $file_storage->read($name);
    $source_storage->replaceData($name, $data);
  }
  if ($delete_list = drush_get_option('delete-list')) {
    if (!is_file($delete_list)) {
      return drush_log(dt('The file specified in --delete-list option does not exist.'), LogLevel::ERROR);
    }
    if ($string = file_get_contents($delete_list)) {
      $delete_list_error = FALSE;
      $parsed = FALSE;
      try {
        $parsed = Yaml::decode($string);
      }
      catch (InvalidDataTypeException $e) {
        $delete_list_error = TRUE;
      }
      if (!isset($parsed['delete']) || !is_array($parsed['delete'])) {
        $delete_list_error = TRUE;
      }
      if ($delete_list_error) {
        return drush_log(dt('The file specified in --delete-list option is in the wrong format. It must be valid YAML with a top-level delete key.'), LogLevel::ERROR);
      }
      foreach ($parsed['delete'] as $delete) {
        // Allow for accidental .yml extension.
        if (substr($delete, -4) === '.yml') {
          $delete = substr($delete, 0, -4);
        }
        if ($source_storage->exists($delete)) {
          $source_storage->delete($delete);
          drush_log("Deleted $delete as per delete list.", LogLevel::OK);
        }
        else {
          drush_log("Ignored deleting $delete, does not exist.", LogLevel::OK);
        }
      }
    }
  }
  if ($install = drush_get_option('install')) {
    $file_storage = new FileStorage($install);
    foreach ($file_storage->listAll() as $name) {
      if (!$source_storage->exists($name)) {
        $data = $file_storage->read($name);
        $source_storage->replaceData($name, $data);
        drush_log("Installed $name for first time.", LogLevel::OK);
      }
    }
  }

  // If our configuration storage is being filtered, then attach all filters
  // to the source storage object.  We will use the filtered values uniformly
  // for comparison, full imports, and partial imports.
  $storage_filters = drush_config_get_storage_filters();
  if (!empty($storage_filters)) {
    $source_storage = new StorageWrapper($source_storage, $storage_filters);
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
