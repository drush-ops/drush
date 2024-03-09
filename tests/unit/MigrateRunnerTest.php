<?php

declare(strict_types=1);

namespace Drush\Drupal\Migrate;

use Composer\Semver\Comparator;
use Drupal\Core\Database\Database;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Path;
use Unish\TestSqlIdMap;

class MigrateRunnerTest extends TestCase
{
    /**
     * @covers \Drush\Drupal\Migrate\MigrateUtils::parseIdList
     * @dataProvider dataProviderParseIdList
     *
     * @param string $idList
     * @param array $expected
     */
    public function testParseIdList(string $idList, array $expected): void
    {
        $this->assertSame($expected, MigrateUtils::parseIdList($idList));
    }

    /**
     * Data provider for testBuildIdList.
     */
    public static function dataProviderParseIdList(): array
    {
        return [
          'empty' => [
            '',
            [],
          ],
          'single simple ID' => [
            '223',
            [['223']],
          ],
          'single ID with delimiters' => [
            '"223,3425"',
            [['223,3425']],
          ],
          'multiple IDs' => [
            '1, 2 ,33,777,4',
            [['1'], ['2'], ['33'], ['777'], ['4']],
          ],
          'multiple with multiple columns' => [
            '1:foo,235:bar, 543:"x:o"',
            [['1', 'foo'], ['235', 'bar'], ['543', 'x:o']],
          ],
        ];
    }

    /**
     * @covers \Drush\Drupal\Migrate\MigrateIdMapFilter
     * @dataProvider dataProviderMigrateIdMapFilter
     *
     * @param array $sourceIdList
     * @param array $destinationIdList
     * @param array $expectedRows
     */
    public function testMigrateIdMapFilter(array $sourceIdList, array $destinationIdList, array $expectedRows): void
    {
        $migration = $this->createMock(MigrationInterface::class);
        $migration->expects($this->any())->method('id')->willReturn('foo');
        $migrationManager = $this->createMock(MigrationPluginManagerInterface::class);
        $eventDispatcher = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();
        $db = $this->getDatabaseConnection();
        require_once 'TestSqlIdMap.php';
        $idMap = new TestSqlIdMap($db, [], 'sql', [], $migration, $eventDispatcher, $migrationManager);

        $filteredIdMap = new MigrateIdMapFilter($idMap, $sourceIdList, $destinationIdList);
        $this->assertFilteredIdMap($filteredIdMap, $expectedRows);
    }

    /**
     * Data provider for testMigrateIdMapFilter.
     */
    public static function dataProviderMigrateIdMapFilter(): array
    {
        return [
          'no filter' => [
            [],
            [],
            self::getMapTableData(),
          ],
          'filter only on source' => [
            [
              [1, 'foo'],
              [68900, 'at'],
            ],
            [],
            [
              [1, 'foo', 'bar', 99],
              [68900, 'at', 'park', 1046],
            ],
          ],
          'filter only on destination' => [
            [],
            [
              ['bar', 99],
              ['dictate', 1045],
              ['wrong', 34033],
            ],
            [
              [1, 'foo', 'bar', 99],
              [366, 'monopoly', 'dictate', 1045],
              [324, 'melon', 'wrong', 34033],
            ],
          ],
          'filter on both' => [
            [
              [1, 'foo'],
              [324, 'melon'],
            ],
            [
              ['bar', 99],
              ['dictate', 1045],
              ['wrong', 34033],
            ],
            [
              [1, 'foo', 'bar', 99],
              [324, 'melon', 'wrong', 34033],
            ],
          ],
        ];
    }

    protected function assertFilteredIdMap(MigrateIdMapFilter $filteredIdMap, array $expectedRows): void
    {
        $actualRows = [];
        $filteredIdMap->rewind();
        /** @var \Drupal\migrate\Plugin\MigrateIdMapInterface $idMap */
        $idMap = $filteredIdMap->getInnerIterator();
        while ($filteredIdMap->valid()) {
            $actualRows[] = array_merge($idMap->currentSource(), $idMap->currentDestination());
            $filteredIdMap->next();
        }
        // Make the assertion predictable.
        sort($actualRows);
        sort($expectedRows);

        $this->assertEquals($expectedRows, $actualRows);
    }

    protected function getDatabaseConnection()
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is not available.');
        }

        // Need to manually add the class loader info for the driver.
        /** @var \Composer\Autoload\ClassLoader $loader */
        $loader = require PHPUNIT_COMPOSER_INSTALL;
        $loader->addPsr4('Drupal\\sqlite\\', $this->webroot() . '/core/modules/sqlite/src');

        // Get the database connection.
        $cwd = getcwd();
        chdir($this->webroot());
        $info = Database::convertDbUrlToConnectionInfo('sqlite://localhost/:memory:?module=sqlite', $this->webroot());
        Database::addConnectionInfo('default', 'default', $info);
        $connection = Database::getConnection();
        chdir($cwd);

        // Create the table and load it with data.
        $mapTableSchema = $this->getMapTableSchema();
        $table = 'migrate_map_test_migration';
        if ($connection->schema()->tableExists($table)) {
            $connection->schema()->dropTable($table);
        }
        $connection->schema()->createTable($table, $mapTableSchema);
        $fields = array_keys($mapTableSchema['fields']);
        $insert = $connection->insert($table)->fields($fields);
        $mapTableData = $this->getMapTableData();

        $mapTableData = array_map(function (array $row): array {
            // Add missing column values.
            return array_merge([''], $row, [0, 0, time(), '']);
        }, $mapTableData);
        array_walk($mapTableData, [$insert, 'values']);
        $insert->execute();

        return $connection;
    }

    public static function webroot(): string
    {
        return Path::join(Path::canonicalize(dirname(__DIR__, 2)), 'sut');
    }

    /**
     * @return array
     */
    protected function getMapTableSchema(): array
    {
        return [
          'fields' => [
            'source_ids_hash' => [
              'type' => 'varchar',
              'length' => '64',
              'not null' => true,
            ],
            'sourceid1' => [
              'type' => 'int',
              'not null' => true,
            ],
            'sourceid2' => [
              'type' => 'varchar',
              'length' => '32',
              'not null' => true,
            ],
            'destid1' => [
              'type' => 'varchar',
              'length' => '64',
              'not null' => false,
            ],
            'destid2' => [
              'type' => 'int',
              'not null' => false,
            ],
            'source_row_status' => [
              'type' => 'int',
              'size' => 'tiny',
              'unsigned' => true,
              'not null' => true,
              'default' => 0,
            ],
            'rollback_action' => [
              'type' => 'int',
              'size' => 'tiny',
              'unsigned' => true,
              'not null' => true,
              'default' => 0,
            ],
            'last_imported' => [
              'type' => 'int',
              'unsigned' => true,
              'not null' => true,
              'default' => 0,
            ],
            'hash' => [
              'type' => 'varchar',
              'length' => '64',
              'not null' => false,
            ],
          ],
        ];
    }

    protected static function getMapTableData(): array
    {
        return [
          [1, 'foo', 'bar', 99],
          [2, 'baz', 'qux', 98],
          [366, 'monopoly', 'dictate', 1045],
          [68900, 'at', 'park', 1046],
          [324, 'melon', 'wrong', 34033],
        ];
    }
}
