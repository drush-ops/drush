<?php

declare(strict_types=1);

namespace Unish;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate\Plugin\migrate\id_map\Sql;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Testable class replacing the core plugin.
 */
class TestSqlIdMap extends Sql
{
    public function __construct(Connection $database, array $configuration, $pluginId, $pluginDefinition, MigrationInterface $migration, EventDispatcherInterface $eventDispatcher, MigrationPluginManagerInterface $migrationPluginManager)
    {
        $this->database = $database;
        parent::__construct($configuration, $pluginId, $pluginDefinition, $migration, $eventDispatcher, $migrationPluginManager);
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
