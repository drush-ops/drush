<?php

declare(strict_types=1);

namespace Drush\Drupal\Migrate;

use Drupal\migrate\Plugin\MigrateSourceInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Psr\EventDispatcher\EventDispatcherInterface;

class MigrateRunnerHooks
{
    public function __construct(protected readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    /**
     * Implements hook_migrate_prepare_row().
     *
     * We implement this on behalf of the 'system' module.
     *
     * @todo Deprecate this hook implementation when #2952291 lands.
     * @see https://www.drupal.org/project/drupal/issues/2952291
     */
    public function prepareRow(Row $row, MigrateSourceInterface $source, MigrationInterface $migration): void
    {
        $this->eventDispatcher->dispatch(
            new MigratePrepareRowEvent($row, $source, $migration),
            MigrateEvents::DRUSH_MIGRATE_PREPARE_ROW,
        );
    }
}
