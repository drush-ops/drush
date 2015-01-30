<?php

/**
 * @file
 * Migrate content into Drupal 8+.
 */

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
  try {
    return drush_migrate_manifest_get_class($manifest)->import();
  }
  catch (\Exception $e) {
    drush_set_error('MIGRATE_ERROR', $e->getMessage());
  }

  drush_invoke_process('@self', 'cache-rebuild', array(), array(), FALSE);
}

/**
 * Get a version specific MigrateManifest class.
 *
 * @param string $manifest
 *   The path to the manifest file.
 *
 * @return \Drush\Migrate\MigrateInterface
 *   The migrate manifest class.
 *
 * @see drush_get_class().
 */
function drush_migrate_manifest_get_class($manifest) {
  return drush_get_class('Drush\Migrate\MigrateManifest', array($manifest));
}
