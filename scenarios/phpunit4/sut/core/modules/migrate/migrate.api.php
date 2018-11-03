<?php

/**
 * @file
 * Hooks provided by the Migrate module.
 */

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrateSourceInterface;
use Drupal\migrate\Row;

/**
 * @defgroup migration Migrate API
 * @{
 * Overview of the Migrate API, which migrates data into Drupal.
 *
 * @section overview Overview of a migration
 * Migration is an
 * @link http://wikipedia.org/wiki/Extract,_transform,_load Extract, Transform, Load @endlink
 * (ETL) process. In the Drupal Migrate API, the extract phase is called
 * 'source', the transform phase is called 'process', and the load phase is
 * called 'destination'. It is important to understand that the term 'load' in
 * ETL refers to loading data into the storage while in a typical Drupal context
 * the term 'load' refers to loading data from storage.
 *
 * In the source phase, a set of data, called the row, is retrieved from the
 * data source. The data can be migrated from a database, loaded from a file
 * (for example CSV, JSON or XML) or fetched from a web service (for example RSS
 * or REST). The row is sent to the process phase where it is transformed as
 * needed or marked to be skipped. Processing can also determine if a 'stub'
 * needs to be created. For example, if a term has a parent term which hasn't
 * been migrated yet, a stub term is created so that the parent relation can be
 * established, and the stub is updated at a later point. After processing, the
 * transformed row is passed to the destination phase where it is loaded (saved)
 * into the target Drupal site.
 *
 * Migrate API uses the Drupal plugin system for many different purposes. Most
 * importantly, the overall ETL process is defined as a migration plugin and the
 * three phases (source, process and destination) have their own plugin types.
 *
 * @section sec_migrations Migrate API migration plugins
 * Migration plugin definitions are stored in a module's 'migrations' directory.
 * The plugin class is \Drupal\migrate\Plugin\Migration, with interface
 * \Drupal\migrate\Plugin\MigrationInterface. Migration plugins are managed by
 * the \Drupal\migrate\Plugin\MigrationPluginManager class. Migration plugins
 * are only available if the providers of their source plugins are installed.
 *
 * @link https://www.drupal.org/docs/8/api/migrate-api/migrate-destination-plugins-examples Example migrations in Migrate API handbook. @endlink
 *
 * @section sec_source Migrate API source plugins
 * Migrate API source plugins implement
 * \Drupal\migrate\Plugin\MigrateSourceInterface and usually extend
 * \Drupal\migrate\Plugin\migrate\source\SourcePluginBase. They are annotated
 * with \Drupal\migrate\Annotation\MigrateSource annotation and must be in
 * namespace subdirectory 'Plugin\migrate\source' under the namespace of the
 * module that defines them. Migrate API source plugins are managed by the
 * \Drupal\migrate\Plugin\MigrateSourcePluginManager class.
 *
 * @link https://api.drupal.org/api/drupal/namespace/Drupal!migrate!Plugin!migrate!source List of source plugins provided by the core Migrate module. @endlink
 * @link https://www.drupal.org/docs/8/api/migrate-api/migrate-source-plugins Core and contributed source plugin usage examples in Migrate API handbook. @endlink
 *
 * @section sec_process Migrate API process plugins
 * Migrate API process plugins implement
 * \Drupal\migrate\Plugin\MigrateProcessInterface and usually extend
 * \Drupal\migrate\ProcessPluginBase. They are annotated with
 * \Drupal\migrate\Annotation\MigrateProcessPlugin annotation and must be in
 * namespace subdirectory 'Plugin\migrate\process' under the namespace of the
 * module that defines them. Migrate API process plugins are managed by the
 * \Drupal\migrate\Plugin\MigratePluginManager class.
 *
 * @link https://api.drupal.org/api/drupal/namespace/Drupal!migrate!Plugin!migrate!process List of process plugins for common operations provided by the core Migrate module. @endlink
 *
 * @section sec_destination Migrate API destination plugins
 * Migrate API destination plugins implement
 * \Drupal\migrate\Plugin\MigrateDestinationInterface and usually extend
 * \Drupal\migrate\Plugin\migrate\destination\DestinationBase. They are
 * annotated with \Drupal\migrate\Annotation\MigrateDestination annotation and
 * must be in namespace subdirectory 'Plugin\migrate\destination' under the
 * namespace of the module that defines them. Migrate API destination plugins
 * are managed by the \Drupal\migrate\Plugin\MigrateDestinationPluginManager
 * class.
 *
 * @link https://api.drupal.org/api/drupal/namespace/Drupal!migrate!Plugin!migrate!destination List of destination plugins for Drupal configuration and content entities provided by the core Migrate module. @endlink
 *
 * @section sec_more_info Documentation handbooks
 * @link https://www.drupal.org/docs/8/api/migrate-api Migrate API handbook. @endlink
 * @link https://www.drupal.org/docs/8/upgrade Upgrading to Drupal 8 handbook. @endlink
 * @}
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Allows adding data to a row before processing it.
 *
 * For example, filter module used to store filter format settings in the
 * variables table which now needs to be inside the filter format config
 * file. So, it needs to be added here.
 *
 * hook_migrate_MIGRATION_ID_prepare_row() is also available.
 *
 * @param \Drupal\migrate\Row $row
 *   The row being imported.
 * @param \Drupal\migrate\Plugin\MigrateSourceInterface $source
 *   The source migration.
 * @param \Drupal\migrate\Plugin\MigrationInterface $migration
 *   The current migration.
 *
 * @ingroup migration
 */
function hook_migrate_prepare_row(Row $row, MigrateSourceInterface $source, MigrationInterface $migration) {
  if ($migration->id() == 'd6_filter_formats') {
    $value = $source->getDatabase()->query('SELECT value FROM {variable} WHERE name = :name', [':name' => 'mymodule_filter_foo_' . $row->getSourceProperty('format')])->fetchField();
    if ($value) {
      $row->setSourceProperty('settings:mymodule:foo', unserialize($value));
    }
  }
}

/**
 * Allows adding data to a row for a migration with the specified ID.
 *
 * This provides the same functionality as hook_migrate_prepare_row() but
 * removes the need to check the value of $migration->id().
 *
 * @param \Drupal\migrate\Row $row
 *   The row being imported.
 * @param \Drupal\migrate\Plugin\MigrateSourceInterface $source
 *   The source migration.
 * @param \Drupal\migrate\Plugin\MigrationInterface $migration
 *   The current migration.
 *
 * @ingroup migration
 */
function hook_migrate_MIGRATION_ID_prepare_row(Row $row, MigrateSourceInterface $source, MigrationInterface $migration) {
  $value = $source->getDatabase()->query('SELECT value FROM {variable} WHERE name = :name', [':name' => 'mymodule_filter_foo_' . $row->getSourceProperty('format')])->fetchField();
  if ($value) {
    $row->setSourceProperty('settings:mymodule:foo', unserialize($value));
  }
}

/**
 * Allows altering the list of discovered migration plugins.
 *
 * Modules are able to alter specific migrations structures or even remove or
 * append additional migrations to the discovery. For example, this
 * implementation filters out Drupal 6 migrations from the discovered migration
 * list. This is done by checking the migration tags.
 *
 * @param array[] $migrations
 *   An associative array of migrations keyed by migration ID. Each value is the
 *   migration array, obtained by decoding the migration YAML file and enriched
 *   with some meta information added during discovery phase, like migration
 *   'class', 'provider' or '_discovered_file_path'.
 *
 * @ingroup migration
 */
function hook_migration_plugins_alter(array &$migrations) {
  $migrations = array_filter($migrations, function (array $migration) {
    $tags = isset($migration['migration_tags']) ? (array) $migration['migration_tags'] : [];
    return !in_array('Drupal 6', $tags);
  });
}

/**
 * @} End of "addtogroup hooks".
 */
