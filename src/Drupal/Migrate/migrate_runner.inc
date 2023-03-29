<?php

declare(strict_types=1);

use Drupal\migrate\Plugin\MigrateSourceInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drush\Drupal\Migrate\MigrateEvents;
use Drush\Drupal\Migrate\MigratePrepareRowEvent;

/**
 * Implements hook_migrate_prepare_row().
 *
 * We implement this on behalf of the 'system' module.
 *
 * @todo Deprecate this hook implementation when #2952291 lands.
 * @see https://www.drupal.org/project/drupal/issues/2952291
 */
function system_migrate_prepare_row(Row $row, MigrateSourceInterface $source, MigrationInterface $migration): void
{
    \Drupal::service('event_dispatcher')->dispatch(
        new MigratePrepareRowEvent($row, $source, $migration),
        MigrateEvents::DRUSH_MIGRATE_PREPARE_ROW,
    );
}
