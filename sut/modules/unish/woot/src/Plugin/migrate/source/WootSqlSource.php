<?php

declare(strict_types=1);

namespace Drupal\woot\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Reads the woot_migrate_source table through the core SqlBase plugin.
 *
 * Configured with "key: default" in test_migration_sql, so the source and the
 * ID map share a connection and SqlBase joins the map table.
 *
 * @see \Unish\MigrateRunnerTest::testMissingSourceRowsWithSqlSource()
 *
 * @MigrateSource(
 *   id = "woot_sql_source",
 * )
 */
class WootSqlSource extends SqlBase
{
    public function query(): SelectInterface
    {
        return $this->select('woot_migrate_source', 'w')->fields('w', ['id', 'name']);
    }

    public function fields(): array
    {
        return [
          'id' => 'ID',
          'name' => 'Name',
        ];
    }

    public function getIds(): array
    {
        return ['id' => ['type' => 'integer', 'alias' => 'w']];
    }
}
