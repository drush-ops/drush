<?php

use Drupal\Core\Database\Database;
use Drupal\migrate\MigrateExecutable;
use Symfony\Component\Yaml\Yaml;
use Drush\Migrate\DrushLogMigrateMessage;

/**
 * Implements hook_drush_command().
 */
function migrate_drush_command() {
  $items['migrate-manifest'] = array(
    'arguments' => array(
      'db_url' => 'The source database URL',
      'manifest' => 'The name of the manifest file',
    ),
  );
  return $items;
}

/**
 * Import from a manifest file.
 *
 * @param string $db_url
 *   The database url. Eg. mysql://[username]:[password]@localhost/[database]
 * @param $manifest
 *   The path to the manifest path.
 */
function drush_migrate_manifest($db_url, $manifest) {
  if (file_exists($manifest)) {
    $db_spec = drush_convert_db_from_db_url($db_url);
    Database::addConnectionInfo('migrate', 'default', $db_spec);
    $list = Yaml::parse($manifest);
    if (is_array($list)) {
      $message = new DrushLogMigrateMessage();
      foreach (entity_load_multiple('migration', $list) as $migration_id => $migration) {
        drush_log("Running $migration_id");
        $executable = new MigrateExecutable($migration, $message);
        $executable->import();
      }
      include_once DRUPAL_ROOT . '/core/includes/utility.inc';
      drupal_rebuild();
    }
    else {
      drush_log('Error: The manifest file is in the wrong format..');
    }
  }
  else {
    drush_log('Error: The manifest file does not exist.', 'error');
  }
}
