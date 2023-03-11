<?php

declare(strict_types=1);

namespace Drush\Drupal\Migrate;

/**
 * Defines the row preparation event for the migration system.
 *
 * @see \Drush\Drupal\Migrate\MigratePrepareRowEvent
 */
final class MigrateEvents
{
    /**
     * Name of the event fired when preparing a source data row.
     *
     * This event allows modules to perform an action whenever the source plugin
     * has read the initial source data into a Row object. Typically, this would
     * be used to add data to the row, manipulate the data into a canonical
     * form, or signal by exception that the row should be skipped. The event
     * listener method receives a \Drush\Drupal\Migrate\MigratePrepareRowEvent
     * instance.
     *
     * @Event
     *
     * @var string
     *
     * @todo Deprecate this event when #2952291 lands.
     *
     * @see https://www.drupal.org/project/drupal/issues/2952291
     * @see \Drush\Drupal\Migrate\MigratePrepareRowEvent
     */
    const DRUSH_MIGRATE_PREPARE_ROW = 'drush.migrate_runner.prepare_row';
}
