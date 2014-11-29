<?php

/**
 * @file
 * Contains \Drush\Migrate\MigrateManifestTest8
 */

namespace Drush\Migrate;

use Drupal\migrate\Entity\Migration;

class MigrateManifestTest8 extends MigrateManifest8 {

  /**
   * We override run so we can simply dump the migration for testing.
   *
   * We currently dump the migration, source and destination configuration. We
   * could expand on this or find a nicer way to pash the information back for
   * testing purposes.
   *
   * @param \Drupal\migrate\Entity\Migration $migration
   *   The migration entity.
   */
  public function run(Migration $migration) {
    drush_log("Importing: " . $migration->id(), 'success');
    drush_log("Source Config: " . print_r($migration->source, TRUE), 'success');
    drush_log("Destination Config: " . print_r($migration->destination, TRUE), 'success');
  }

}
