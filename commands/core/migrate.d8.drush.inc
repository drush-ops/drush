<?php

/**
 * @file
 * Migrate content into Drupal 8+.
 */

use Drupal\Core\Database\Database;
use Drupal\migrate\MigrateExecutable;
use Symfony\Component\Yaml\Yaml;
use Drush\Migrate\DrushLogMigrateMessage;

/**
 * Implements hook_drush_command().
 */
function migrate_drush_command() {
  $items['migrate-manifest'] = array(
    'description' => 'Execute the migrations as specified in a manifest file.',
    'arguments' => array(
      'manifest' => 'The path to the manifest file',
    ),
    'required-arguments' => 1,
    'options' => array(
      'legacy-db-url' => array(
        'description' => 'A Drupal 6 style database URL.',
        'example-value' => 'mysql://root:pass@127.0.0.1/db',
        'required' => TRUE,
      ),
    ),
    'drupal dependencies' => array('migrate_drupal'),
  );
  return $items;
}

/**
 * Import from a manifest file.
 * @param string $manifest
 *   The path to the manifest file.
 */
function drush_migrate_manifest($manifest) {
  if (file_exists($manifest)) {
    $db_url = drush_get_option('legacy-db-url');
    $db_spec = drush_convert_db_from_db_url($db_url);
    Database::addConnectionInfo('migrate', 'default', $db_spec);
    $list = Yaml::parse($manifest);
    if (is_array($list)) {
      $message = new DrushLogMigrateMessage();
      foreach (entity_load_multiple('migration', $list) as $migration_id => $migration) {
        drush_log("Running $migration_id", 'ok');
        $executable = new MigrateExecutable($migration, $message);
        $executable->import();
      }
      drush_invoke_process('@self', 'cache-rebuild', array(), array(), FALSE);
    }
    else {
      return drush_set_error('The manifest file cannot be parsed.');
    }
  }
  else {
    return drush_set_error('The manifest file does not exist.');
  }
}
