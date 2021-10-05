<?php

namespace Drush\Drupal\Migrate;

use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Missing source rows event.
 */
class MigrateMissingSourceRowsEvent extends Event
{
    /**
     * The migration plugin instance.
     *
     * @var \Drupal\migrate\Plugin\MigrationInterface
     */
    protected $migration;

    /**
     * Values representing the destination IDs.
     *
     * @var array[]
     */
    protected $destinationIds;

    /**
     * Constructs a new event instance.
     *
     * @param \Drupal\migrate\Plugin\MigrationInterface $migration
     *   The migration plugin instance.
     * @param array[] $destinationIds
     *   Values representing the destination ID.
     */
    public function __construct(MigrationInterface $migration, array $destinationIds)
    {
        $this->migration = $migration;
        $this->destinationIds = $destinationIds;
    }

    /**
     * Gets the migration plugin instance.
     *
     * @return \Drupal\migrate\Plugin\MigrationInterface
     *   The migration being rolled back.
     */
    public function getMigration(): MigrationInterface
    {
        return $this->migration;
    }

    /**
     * Gets the destination ID values.
     *
     * @return array[]
     *   The destination IDs as an array of arrays.
     */
    public function getDestinationIds(): array
    {
        return $this->destinationIds;
    }
}
