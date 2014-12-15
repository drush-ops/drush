<?php

/**
 * @file
 * Contains \Drush\Migrate\MigrateManifest
 */

namespace Drush\Migrate;

use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Drupal\migrate\MigrateExecutable;
use Drupal\Core\Database\Database;

class MigrateManifest8 implements MigrateInterface {

  /**
   * The path to the manifest file.
   *
   * @var string
   */
  protected $manifestFile;

  /**
   * The list of migrations to run and their configuration.
   *
   * @var array
   */
  protected $migrationList;

  /**
   * An array of all migrations and their info.
   *
   * @var array
   */
  protected $migrations = array();

  /**
   * The message log.
   *
   * @var \Drush\Migrate\DrushLogMigrateMessage
   */
  protected $log;

  /**
   * Constructs a new MigrateManifest object.
   */
  public function __construct($manifest_file) {
    $this->manifestFile = $manifest_file;
    $this->migrationList = Yaml::parse($this->manifestFile);
    $this->log = new DrushLogMigrateMessage();

    if (!file_exists($this->manifestFile)) {
      throw new FileNotFoundException('The manifest file does not exist.');
    }

    if (!is_array($this->migrationList)) {
      throw new ParseException('The manifest file cannot be parsed.');
    }
  }

  public function import() {

    $this->setupLegacyDb();
    $nonexistent_migrations = array();

    foreach ($this->migrationList as $migration_info) {
      if (is_array($migration_info)) {
        // The migration is stored as the key in the info array.
        $migration_id = key($migration_info);
        $migration = $this->loadMigration($migration_id);

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
        $migration = $this->loadMigration($migration_id);
      }

      if (isset($migration)) {
        $executable = $this->importSingle($migration);
        // Store all the migrations for later.
        $this->migrations[$migration->id()] = array(
          'executable' => $executable,
          'migration' => $migration,
        );
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

    return $this->migrations;
  }

  /**
   * Load a migration.
   *
   * @param $migration_id
   *   The migration id to load.
   *
   * @return \Drupal\migrate\Entity\Migration
   *   The loaded migration entity.
   */
  protected function loadMigration($migration_id) {
    return entity_load('migration', $migration_id);
  }

  /**
   * Import a single migration.
   *
   * @param \Drupal\migrate\Entity\Migration $migration
   *   The migration to run.
   *
   * @return \Drupal\migrate\MigrateExecutable
   *   The migration executable.
   */
  protected function importSingle($migration) {
    drush_log('Running ' . $migration->id(), 'ok');
    $executable = new MigrateExecutable($migration, $this->log);
    // drush_op() provides --simulate support.
    drush_op(array($executable, 'import'));

    return $executable;
  }

  /**
   * Setup the legacy database connection to migrate from.
   */
  protected function setupLegacyDb() {
    $db_url = drush_get_option('legacy-db-url');
    $db_spec = drush_convert_db_from_db_url($db_url);
    Database::addConnectionInfo('migrate', 'default', $db_spec);
  }

}
