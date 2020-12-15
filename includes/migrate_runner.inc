<?php

use Drupal\migrate\Plugin\MigrateSourceInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drush\Drupal\Migrate\MigrateEvents;
use Drush\Drupal\Migrate\MigratePrepareRowEvent;

/**
 * Implements hook_migrate_prepare_row().
 *
 * We implement this in behalf of the 'system' module.
 */
function system_migrate_prepare_row(Row $row, MigrateSourceInterface $source, MigrationInterface $migration)
{
    \Drupal::service('event_dispatcher')->dispatch(
        MigrateEvents::DRUSH_MIGRATE_PREPARE_ROW,
        new MigratePrepareRowEvent($row, $source, $migration)
    );
}
