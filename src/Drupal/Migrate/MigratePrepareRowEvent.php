<?php

declare(strict_types=1);

namespace Drush\Drupal\Migrate;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrateSourceInterface;
use Drupal\migrate\Row;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Wraps a prepare-row event for event listeners.
 *
 * @internal
 *
 * @todo @todo Deprecate this class when #2952291 lands.
 * @see https://www.drupal.org/project/drupal/issues/2952291
 */
final class MigratePrepareRowEvent extends Event
{
    /**
     * Row object.
     */
    protected Row $row;

    /**
     * Migration source plugin.
     */
    protected MigrateSourceInterface $source;

    /**
     * Migration plugin.
     */
    protected MigrationInterface $migration;

    /**
     * Constructs a prepare-row event object.
     *
     * @param Row $row
     *   Row of source data to be analyzed/manipulated.
     * @param MigrateSourceInterface $source
     *   Source plugin that is the source of the event.
     * @param MigrationInterface $migration
     *   Migration entity.
     */
    public function __construct(Row $row, MigrateSourceInterface $source, MigrationInterface $migration)
    {
        $this->row = $row;
        $this->source = $source;
        $this->migration = $migration;
    }

    /**
     * Gets the row object.
     *
     * @return Row
     *   The row object about to be imported.
     */
    public function getRow(): Row
    {
        return $this->row;
    }

    /**
    * Gets the source plugin.
    *
    * @return MigrateSourceInterface $source
    *   The source plugin firing the event.
    */
    public function getSource(): MigrateSourceInterface
    {
        return $this->source;
    }

    /**
     * Gets the migration plugin.
     *
     * @return MigrationInterface
     *   The migration entity being imported.
     */
    public function getMigration(): MigrationInterface
    {
        return $this->migration;
    }
}
