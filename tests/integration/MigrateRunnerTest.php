<?php

namespace Unish;

use Webmozart\PathUtil\Path;

/**
 * @group commands
 * @coversDefaultClass \Drush\Drupal\Commands\core\MigrateRunnerCommands
 */
class MigrateRunnerTest extends UnishIntegrationTestCase
{
    use TestModuleHelperTrait;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupModulesForTests(['woot'], Path::join(__DIR__, '/../fixtures/modules/d8'));
        $this->drush('pm:enable', ['migrate', 'node', 'woot']);
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        // Cleanup any created content.
        $this->drush('php:eval', ['$storage = Drupal::entityTypeManager()->getStorage("node"); $storage->delete($storage->loadMultiple());']);

        // Uninstall test modules.
        $this->drush('pm:uninstall', ['migrate', 'node', 'woot']);
        // Uninstalling Migrate module doesn't automatically drop the tables.
        // @see https://www.drupal.org/project/drupal/issues/2713327
        $this->dropMigrateTables();
        \Drupal::keyValue('migrate:high_water')->deleteAll();
        \Drupal::keyValue('migrate_last_imported')->deleteAll();
        \Drupal::keyValue('migrate_status')->deleteAll();

        parent::tearDown();
    }

    /**
     * @covers ::status
     * @covers ::getMigrationList
     */
    public function testMigrateStatus(): void
    {
        // No arguments, no options.
        $this->drush('migrate:status', [], [
          'format' => 'json',
          'debug' => null,
        ]);
        $output = $this->getOutputFromJSON();
        $actualIds = array_column($output, 'id');
        $this->assertCount(3, $actualIds);
        $this->assertContains('test_migration', $actualIds);
        $this->assertContains('test_migration_tagged', $actualIds);
        $this->assertContains('test_migration_untagged', $actualIds);

        // Debug message registered for 'test_migration_source_issues'.
        // @see \Drush\Drupal\Commands\core\MigrateRunnerCommands::getMigrationList()
        $this->assertStringContainsString("[debug] Migration 'test_migration_source_issues' is skipped as its source plugin has missed requirements: type1: a. type1: b. type1: c. type2: x. type2: y. type2: z.", $this->getErrorOutputRaw());

        // With arguments.
        $this->drush(
            'migrate:status',
            ['test_migration_tagged,test_migration_untagged'],
            ['format' => 'json']
        );
        $output = $this->getOutputFromJSON();
        $actualIds = array_column($output, 'id');
        $this->assertCount(2, $actualIds);
        $this->assertContains('test_migration_tagged', $actualIds);
        $this->assertContains('test_migration_untagged', $actualIds);

        // Using --tag with value.
        $this->drush('migrate:status', [], ['tag' => 'tag1,tag2', 'format' => 'json']);
        $output = $this->getOutputFromJSON();

        $this->assertCount(7, $output);
        // Tag: tag1. The first line contains the tag.
        $this->assertEquals('Tag: tag1', $output[0]['id']);
        // When using --tag, the migration IDs are indented, so we trim().
        $this->assertEquals('test_migration', trim($output[1]['id']));
        $this->assertEquals('test_migration_tagged', trim($output[2]['id']));
        // There's an empty row after each tag group.
        $this->assertNull($output[3]['id']);
        // Tag: tag2
        $this->assertEquals('Tag: tag2', $output[4]['id']);
        $this->assertEquals('test_migration_tagged', trim($output[5]['id']));
        $this->assertNull($output[6]['id']);

        // Check that --names-only takes precedence over --fields.
        $this->drush('migrate:status', [], [
          'names-only' => null,
          'fields' => 'id,status,imported',
          'format' => 'json',
        ]);
        $output = $this->getOutputFromJSON();
        $this->assertArrayHasKey('id', $output[0]);
        $this->assertArrayNotHasKey('status', $output[0]);
        $this->assertArrayNotHasKey('total', $output[0]);
        $this->assertArrayNotHasKey('imported', $output[0]);
        $this->assertArrayNotHasKey('needing_update', $output[0]);
        $this->assertArrayNotHasKey('unprocessed', $output[0]);
        $this->assertArrayNotHasKey('last_imported', $output[0]);
        // Check that the deprecation warning is printed.
        $this->assertStringContainsString('The --names-only option is deprecated in Drush 10.5.1 and is removed from Drush 11.0.0. Use --field=id instead.', $this->getErrorOutput());

        // Check improper usage of --names-only with --field.
        $this->drush('migrate:status', [], [
          'field' => 'status',
          'names-only' => null,
        ], self::EXIT_ERROR);
        $this->assertStringContainsString('Cannot use --names-only with --field=status.', $this->getErrorOutput());

        $actualIds = array_column($output, 'id');
        $this->assertCount(3, $actualIds);
        $this->assertContains('test_migration', $actualIds);
        $this->assertContains('test_migration_tagged', $actualIds);
        $this->assertContains('test_migration_untagged', $actualIds);

        // Check that invalid migration IDs are reported.
        $this->drush('migrate:status', ['non_existing,test_migration,another_invalid'], [], self::EXIT_ERROR);
        $this->assertStringContainsString('Invalid migration IDs: non_existing, another_invalid', $this->getErrorOutput());

        // Check --fields option.
        $this->drush('migrate:status', [], [
          'fields' => 'id,status,needing_update',
          'format' => 'json',
        ]);
        $this->assertArrayHasKey('id', $this->getOutputFromJSON(0));
        $this->assertArrayHasKey('status', $this->getOutputFromJSON(0));
        $this->assertArrayHasKey('needing_update', $this->getOutputFromJSON(0));
        $this->assertArrayNotHasKey('total', $this->getOutputFromJSON(0));
        $this->assertArrayNotHasKey('imported', $this->getOutputFromJSON(0));
        $this->assertArrayNotHasKey('unprocessed', $this->getOutputFromJSON(0));
        $this->assertArrayNotHasKey('last_imported', $this->getOutputFromJSON(0));
        $this->assertArrayHasKey('id', $this->getOutputFromJSON(1));
        $this->assertArrayHasKey('status', $this->getOutputFromJSON(1));
        $this->assertArrayHasKey('needing_update', $this->getOutputFromJSON(1));
        $this->assertArrayNotHasKey('total', $this->getOutputFromJSON(1));
        $this->assertArrayNotHasKey('imported', $this->getOutputFromJSON(1));
        $this->assertArrayNotHasKey('unprocessed', $this->getOutputFromJSON(1));
        $this->assertArrayNotHasKey('last_imported', $this->getOutputFromJSON(1));
        $this->assertArrayHasKey('id', $this->getOutputFromJSON(2));
        $this->assertArrayHasKey('status', $this->getOutputFromJSON(2));
        $this->assertArrayHasKey('needing_update', $this->getOutputFromJSON(2));
        $this->assertArrayNotHasKey('total', $this->getOutputFromJSON(2));
        $this->assertArrayNotHasKey('imported', $this->getOutputFromJSON(2));
        $this->assertArrayNotHasKey('unprocessed', $this->getOutputFromJSON(2));
        $this->assertArrayNotHasKey('last_imported', $this->getOutputFromJSON(2));
    }

    /**
     * @covers ::import
     * @covers ::rollback
     */
    public function testMigrateImportAndRollback(): void
    {
        // Trigger logging in ProcessRowTestSubscriber::onPrepareRow().
        // @see \Drupal\woot\EventSubscriber\ProcessRowTestSubscriber::onPrepareRow()
        // @see \Drupal\woot\EventSubscriber\PreRowDeleteTestSubscriber::onPreRowDelete()
        $this->drush('state:set', ['woot.test_migrate_trigger_failures', true]);

        // Warm-up the 'migrate_prepare_row' hook implementations cache to test
        // that system_migrate_prepare_row() is picked-up during import. See
        // MigrateEvents::DRUSH_MIGRATE_PREPARE_ROW test, later.
        // @see system_migrate_prepare_row()
        // @see \Drupal\woot\EventSubscriber\ProcessRowTestSubscriber::onPrepareRow()
        $this->drush('php:eval', ["Drupal::moduleHandler()->invokeAll('migrate_prepare_row');"]);

        // Expect that this command will fail because the 2nd row fails.
        // @see \Drupal\woot\Plugin\migrate\process\TestFailProcess
        $this->drush('migrate:import', ['test_migration'], [], self::EXIT_ERROR);

        // Check for the expected command output.
        $output = $this->getErrorOutput();
        $this->assertStringContainsString('Processed 2 items (1 created, 0 updated, 1 failed, 0 ignored)', $output);
        $this->assertStringContainsString('test_migration migration: 1 failed.', $output);

        // Check if the MigrateEvents::DRUSH_MIGRATE_PREPARE_ROW event is dispatched.
        $this->assertStringContainsString('MigrateEvents::DRUSH_MIGRATE_PREPARE_ROW fired for row with ID 1', $output);
        $this->assertStringContainsString('MigrateEvents::DRUSH_MIGRATE_PREPARE_ROW fired for row with ID 2', $output);

        // Check that nid 1 has been imported successfully while nid 2 failed.
        // @see \Drupal\woot\Plugin\migrate\process\TestFailProcess
        $this->drush('sql:query', ['SELECT title FROM node_field_data']);
        $this->assertSame(['Item 1'], $this->getOutputAsList());

        // Check that an appropriate error is logged when rollback fails.
        // @see \Drupal\woot\EventSubscriber\PreRowDeleteTestSubscriber::onPreRowDelete()
        $this->drush('migrate:rollback', [], ['all' => null], self::EXIT_ERROR);
        $this->assertStringContainsString('Earthquake while rolling back', $this->getErrorOutputRaw());
        $this->drush('migrate:reset', ['test_migration']);

        // Reset the flag, so we won't fail the rollback again.
        $this->drush('state:delete', ['woot.test_migrate_trigger_failures']);

        $this->drush('migrate:rollback', ['test_migration']);
        // Note that item with source ID 2, which failed to import, was already
        // deleted from the map in the previous rollback.
        $this->assertStringContainsString('Rolled back 1 item', $this->getErrorOutput());

        // Check that the migration rollback removes both nodes from backend.
        $this->drush('sql:query', ['SELECT title FROM node_field_data']);
        $this->assertEmpty(array_filter($this->getOutputAsList()));

        $this->drush('migrate:status', ['test_migration'], [
          'format' => 'json',
        ]);
        // Check that status and last import time were reset.
        // @see https://www.drupal.org/project/migrate_tools/issues/3011996
        $this->assertSame('Idle', $this->getOutputFromJSON(0)['status']);
        $this->assertEmpty($this->getOutputFromJSON(0)['last_imported']);

        // Test that dependent migrations run only once.
        $this->drush('migrate:import', ['test_migration_tagged,test_migration_untagged'], ['execute-dependencies' => null]);
        foreach (['test_migration_tagged', 'test_migration_untagged'] as $migration_id) {
            $occurrences = substr_count($this->getErrorOutput(), "done with '$migration_id'");
            $this->assertEquals(1, $occurrences);
        }
    }

    /**
     * @covers ::import
     * @covers ::rollback
     */
    public function testMigrateImportAndRollbackWithIdList(): void
    {
        // Enlarge the source recordset to 50 rows.
        $this->drush('state:set', ['woot.test_migration_source_data_amount', 50]);

        $this->drush('migrate:import', ['test_migration'], [
            // Intentionally added 56, which is out of bounds.
            'idlist' => '4,12,29,34,56',
        ]);

        $this->drush('migrate:status', ['test_migration'], [
            'format' => 'json',
        ]);

        // Check that only rows with ID 4, 12, 29, 34 were imported.
        $this->assertSame(50, $this->getOutputFromJSON(0)['total']);
        $this->assertSame('4 (8%)', $this->getOutputFromJSON(0)['imported']);
        $this->assertSame(46, $this->getOutputFromJSON(0)['unprocessed']);
        $this->drush('sql:query', ['SELECT title FROM node_field_data']);
        $this->assertSame(['Item 4', 'Item 12', 'Item 29', 'Item 34'], $this->getOutputAsList());

        $this->drush('migrate:rollback', ['test_migration'], [
            // Intentionally added 56, which is out of bounds.
            'idlist' => '4,34,56',
        ]);
        $this->drush('migrate:status', ['test_migration'], ['format' => 'json']);

        // Check that only rows with ID 4 and 34 were rolled back.
        $this->assertSame(50, $this->getOutputFromJSON(0)['total']);
        $this->assertSame('2 (4%)', $this->getOutputFromJSON(0)['imported']);
        $this->assertSame(48, $this->getOutputFromJSON(0)['unprocessed']);
        $this->drush('sql:query', ['SELECT title FROM node_field_data']);
        $this->assertEquals(['Item 12', 'Item 29'], $this->getOutputAsList());
    }

    /**
     * @covers ::stop
     * @covers ::resetStatus
     */
    public function testMigrateStopAndResetStatus(): void
    {
        $this->drush('migrate:stop', ['test_migration']);
        // @todo Find a way to stop a migration that runs.
        $this->assertStringContainsString('Migration test_migration is idle', $this->getErrorOutput());

        $this->drush('migrate:reset', ['test_migration']);
        // @todo Find a way to reset a migration that is not idle.
        $this->assertStringContainsString('Migration test_migration is already Idle', $this->getErrorOutput());
    }

    /**
     * Drops the migration tables.
     */
    protected function dropMigrateTables(): void
    {
        $this->drush('sql:query', ["SHOW TABLES LIKE 'migrate_map_%'"]);
        $tables = $this->getOutputAsList();
        $this->drush('sql:query', ["SHOW TABLES LIKE 'migrate_message_%'"]);
        $tables = array_filter(array_merge($tables, $this->getOutputAsList()));
        foreach ($tables as $table) {
            $this->drush('sql:query', ["DROP TABLE $table"]);
        }
    }
}
