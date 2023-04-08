<?php

declare(strict_types=1);

namespace Drupal\woot\EventSubscriber;

use Drush\Drush;
use Drush\Drupal\Migrate\MigrateEvents;
use Drush\Drupal\Migrate\MigratePrepareRowEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Used to test the MigrateEvents::PREPARE_ROW event.
 */
class ProcessRowTestSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [MigrateEvents::DRUSH_MIGRATE_PREPARE_ROW => 'onPrepareRow'];
    }

    /**
     * Reacts when a migrate row is being prepared.
     */
    public function onPrepareRow(MigratePrepareRowEvent $event)
    {
        // Log only in MigrateRunnerTest::testMigrateImportAndRollback() test.
        // @see \Unish\MigrateRunnerTest::testMigrateImportAndRollback()
        if (\Drupal::state()->get('woot.migrate_runner.trigger_failures')) {
            Drush::logger()
              ->notice("MigrateEvents::DRUSH_MIGRATE_PREPARE_ROW fired for row with ID {$event->getRow()->getSourceProperty('id')}");
        }
    }
}
