<?php

namespace Unish;

use Drupal\Core\Database\Driver\sqlite\Connection;
use Drupal\migrate\Plugin\migrate\id_map\Sql;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Testable class replacing the core plugin.
 */
class TestSqlIdMap extends Sql
{

    public function __construct(Connection $database, array $configuration, $pluginId, $pluginDefinition, MigrationInterface $migration, EventDispatcherInterface $eventDispatcher)
    {
        $this->database = $database;
        parent::__construct($configuration, $pluginId, $pluginDefinition, $migration, $eventDispatcher);
    }

    public function mapTableName(): string
    {
        return 'migrate_map_test_migration';
    }

    protected function sourceIdFields(): array
    {
        return ['sourceid1', 'sourceid2'];
    }

    protected function destinationIdFields(): array
    {
        return ['destid1', 'destid2'];
    }

    protected function ensureTables(): void
    {
    }
}
