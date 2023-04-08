<?php

declare(strict_types=1);

namespace Drupal\woot\EventSubscriber;

use Drupal\migrate\Event\MigrateRowDeleteEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Used to test failure of migrate rollbacks.
 */
class PreRowDeleteTestSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return ['migrate.pre_row_delete' => 'onPreRowDelete'];
    }

    public function onPreRowDelete(MigrateRowDeleteEvent $event): void
    {
        // @see \Unish\MigrateRunnerTest::testMigrateImportAndRollback()
        if (\Drupal::state()->get('woot.migrate_runner.trigger_failures')) {
            throw new \RuntimeException('Earthquake while rolling back');
        }
    }
}
