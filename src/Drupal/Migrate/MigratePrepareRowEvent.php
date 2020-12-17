<?php

namespace Drush\Drupal\Migrate;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrateSourceInterface;
use Drupal\migrate\Row;
use Symfony\Component\EventDispatcher\Event;

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
     *
     * @var \Drupal\migrate\Row
     */
    protected $row;

    /**
     * Migration source plugin.
     *
     * @var \Drupal\migrate\Plugin\MigrateSourceInterface
     */
    protected $source;

    /**
     * Migration plugin.
     *
     * @var \Drupal\migrate\Plugin\MigrationInterface
     */
    protected $migration;

    /**
     * Constructs a prepare-row event object.
     *
     * @param \Drupal\migrate\Row $row
     *   Row of source data to be analyzed/manipulated.
     * @param \Drupal\migrate\Plugin\MigrateSourceInterface $source
     *   Source plugin that is the source of the event.
     * @param \Drupal\migrate\Plugin\MigrationInterface $migration
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
     * @return \Drupal\migrate\Row
     *   The row object about to be imported.
     */
    public function getRow(): Row
    {
        return $this->row;
    }

    /**
     * Gets the source plugin.
     *
     * @return \Drupal\migrate\Plugin\MigrateSourceInterface $source
     *   The source plugin firing the event.
     */
    public function getSource(): MigrateSourceInterface
    {
        return $this->source;
    }

    /**
     * Gets the migration plugin.
     *
     * @return \Drupal\migrate\Plugin\MigrationInterface
     *   The migration entity being imported.
     */
    public function getMigration(): MigrationInterface
    {
        return $this->migration;
    }
}
