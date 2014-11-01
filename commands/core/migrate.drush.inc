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
 *
 * This command allows you to specify a list of migrations and their config in
 * a YAML file. An example of a simple migration may look like this:
 *
 * @code
 *  - d6_action_settings
 *  - d6_aggregator_feed
 * @endcode
 *
 * You can also provide configuration to a migration for both source and the
 * destination. An example as such:
 *
 * @code
 *  - d6_file:
 *    source:
 *      conf_path: sites/assets
 *    destination:
 *      source_base_path: destination/base/path
 *      destination_path_property: uri
 *  - d6_action_settings
 * @endcode
 *
 * @param string $manifest
 *   The path to the manifest file.
 */
function drush_migrate_manifest($manifest) {
  if (!file_exists($manifest)) {
    return drush_set_error('The manifest file does not exist.');
  }

  $migration_list = Yaml::parse($manifest);
  if (!is_array($migration_list)) {
    return drush_set_error('The manifest file cannot be parsed.');
  }

  $db_url = drush_get_option('legacy-db-url');
  $db_spec = drush_convert_db_from_db_url($db_url);
  Database::addConnectionInfo('migrate', 'default', $db_spec);

  $nonexistent_migrations = array();
  $message = new DrushLogMigrateMessage();

  foreach ($migration_list as $migration_info) {
    if (is_array($migration_info)) {
      // The migration is stored as the key in the info array.
      $migration_id = key($migration_info);
      $migration = entity_load('migration', $migration_id);

      // If we have source config, apply it to the migration.
      if (isset($migration_info['source'])) {
        foreach ($migration_info['source'] as $source_key => $source_value) {
          $migration->source[$source_key] = $source_value;
        }
      }
      // If we have destination config, apply it to the migration.
      if (isset($migration_info['destination'])) {
        foreach ($migration_info['destination'] as $destination_key => $destination_value) {
          $migration->destination[$destination_key] = $destination_value;
        }
      }
    }
    else {
      // If it wasn't an array then the info is just the migration_id.
      $migration_id = $migration_info;
      $migration = entity_load('migration', $migration_id);
    }

    if (!is_null($migration)) {
      drush_log('Running ' . $migration->id(), 'ok');
      $executable = new MigrateExecutable($migration, $message);
      $executable->import();
    }
    else {
      // Keep track of any migrations that weren't found.
      $nonexistent_migrations[] = $migration_id;
    }
  }
  // Warn the user if any migrations were not found.
  if (count($nonexistent_migrations) > 0) {
    drush_log(dt('The following migrations were not found: !migrations', array(
      '!migrations' => implode(', ', $nonexistent_migrations),
    )), 'warning');
  }
  drush_invoke_process('@self', 'cache-rebuild', array(), array(), FALSE);
}
