<?php

namespace Drupal\woot\EventSubscriber;

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
     *
     * @param \Drush\Drupal\Migrate\MigratePrepareRowEvent $event The prepare row migrate event.
     */
    public function onPrepareRow(MigratePrepareRowEvent $event)
    {
        \Drush\Drush::logger()
            ->notice("MigrateEvents::DRUSH_MIGRATE_PREPARE_ROW fired for row with ID {$event->getRow()->getSourceProperty('id')}");
    }
}
