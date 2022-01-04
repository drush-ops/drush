<?php

namespace Drush\Drupal\Migrate;

use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Missing source rows event.
 */
class MigrateMissingSourceRowsEvent extends Event
{
    /**
     * The migration plugin instance.
     */
    protected MigrationInterface $migration;

    /**
     * Values representing the destination IDs.
     *
     * @var array[]
     */
    protected array $destinationIds;

    /**
     * Constructs a new event instance.
     *
     * @param MigrationInterface $migration
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
     * @return MigrationInterface
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
