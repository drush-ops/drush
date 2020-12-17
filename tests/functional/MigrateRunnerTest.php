<?php

namespace Unish;

use Webmozart\PathUtil\Path;

/**
 * @group slow
 * @group commands
 * @coversDefaultClass \Drush\Drupal\Commands\core\MigrateRunnerCommands
 */
class MigrateRunnerTest extends CommandUnishTestCase
{
    use TestModuleHelperTrait;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDrupal(1, true);
        $this->setupModulesForTests(['woot'], Path::join(__DIR__, 'resources/modules/d8'));
        $this->drush('pm:enable', ['migrate', 'node', 'woot']);
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

        // Names only.
        $this->drush('migrate:status', [], ['names-only' => true, 'format' => 'json']);
        $output = $this->getOutputFromJSON();
        $this->assertArrayHasKey('id', $output[0]);
        $this->assertArrayNotHasKey('status', $output[0]);
        $this->assertArrayNotHasKey('total', $output[0]);
        $this->assertArrayNotHasKey('imported', $output[0]);
        $this->assertArrayNotHasKey('last_imported', $output[0]);

        $actualIds = array_column($output, 'id');
        $this->assertCount(3, $actualIds);
        $this->assertContains('test_migration', $actualIds);
        $this->assertContains('test_migration_tagged', $actualIds);
        $this->assertContains('test_migration_untagged', $actualIds);

        // Check that invalid migration IDs are reported.
        $this->drush('migrate:status', ['non_existing,test_migration,another_invalid'], [], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('Invalid migration IDs: non_existing, another_invalid', $this->getErrorOutput());
    }

    /**
     * @covers ::import
     * @covers ::rollback
     */
    public function testMigrateImportAndRollback(): void
    {
        // Trigger logging in ProcessRowTestSubscriber::onPrepareRow().
        // @see \Drupal\woot\EventSubscriber\ProcessRowTestSubscriber::onPrepareRow()
        $this->drush('state:set', ['woot.test_migrate_import_and_rollback', true]);

        // Expect that this command will fail because the 2nd row fails.
        // @see \Drupal\woot\Plugin\migrate\process\TestFailProcess
        $this->drush('migrate:import', ['test_migration'], [], null, null, self::EXIT_ERROR);

        // Check for the expected command output.
        $output = $this->getErrorOutput();
        $this->assertStringContainsString('Processed 2 items (1 created, 0 updated, 1 failed, 0 ignored)', $output);
        $this->assertStringContainsString('test_migration migration: 1 failed.', $output);

        // Check if the MigrateEvents::DRUSH_MIGRATE_PREPARE_ROW event is dispatched.
        $this->assertStringContainsString('MigrateEvents::DRUSH_MIGRATE_PREPARE_ROW fired for row with ID 1', $output);
        $this->assertStringContainsString('MigrateEvents::DRUSH_MIGRATE_PREPARE_ROW fired for row with ID 2', $output);

        // Check that nid 1 has been imported successfully while nid 2 failed.
        // @see \Drupal\woot\Plugin\migrate\process\TestFailProcess
        $this->drush('sql:query', ['SELECT * FROM node_field_data']);
        $this->assertStringContainsString('foo', $this->getOutput());
        $this->assertStringNotContainsString('bar', $this->getOutput());

        $this->drush('migrate:rollback', ['test_migration']);
        // Check for the expected command output.
        $this->assertStringContainsString('Rolled back 2 items', $this->getErrorOutput());

        // Check that the migration rollback removes both nodes from backend.
        $this->drush('sql:query', ['SELECT * FROM node_field_data']);
        $this->assertStringNotContainsString('foo', $this->getOutput());
        $this->assertStringNotContainsString('bar', $this->getOutput());

        $this->drush('migrate:status', ['test_migration'], [
          'format' => 'json',
        ]);
        // Check that status and last import time were reset.
        // @see https://www.drupal.org/project/migrate_tools/issues/3011996
        $this->assertSame('Idle', $this->getOutputFromJSON(0)['status']);
        $this->assertEmpty($this->getOutputFromJSON(0)['last_imported']);

        // Test that dependent migrations run only once.
        $this->drush('migrate:import', ['test_migration_tagged,test_migration_untagged'], ['execute-dependencies' => true]);
        foreach (['test_migration_tagged', 'test_migration_untagged'] as $migration_id) {
            $occurrences = substr_count($this->getErrorOutput(), "done with '$migration_id'");
            $this->assertEquals(1, $occurrences);
        }
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
     * @covers ::messages
     * @covers ::fieldsSource
     */
    public function testMigrateMessagesAndFieldSource(): void
    {
        $this->drush('migrate:messages', ['test_migration']);
        // @todo Cover cases with non-empty message list.
        $this->assertStringContainsString('Level   Message   Source IDs hash', $this->getOutputRaw());

        $this->drush('migrate:fields-source', ['test_migration'], ['format' => 'json']);
        $output = $this->getOutputFromJSON();
        $this->assertEquals('id', $output[0]['machine_name']);
        $this->assertEquals('id', $output[0]['description']);
        $this->assertEquals('name', $output[1]['machine_name']);
        $this->assertEquals('name', $output[1]['description']);
    }

    /**
     * Regression test when importing with --limit and --feedback.
     *
     * @see https://www.drupal.org/project/migrate_tools/issues/2919108
     */
    public function testImportingWithLimitAndFeedback(): void
    {
        // Trigger 'test_migration' source plugin alteration.
        // @see woot_migrate_source_info_alter(0
        $this->drush('state:set', ['woot.test_importing_with_limit_and_feedback', true]);
        $this->drush('migrate:import', ['test_migration'], [
            'feedback' => 20,
            'limit' => 199,
        ]);

        $importOutput = array_values(array_filter(array_map('trim', $this->getErrorOutputAsList()), function (string $line): bool {
            return strpos($line, '[notice]') === 0;
        }));

        $this->assertCount(10, $importOutput);
        foreach ($importOutput as $delta => $outputLine) {
            if ($delta < 9) {
                $this->assertMatchesRegularExpression("/^\[notice\] Processed 20 items \(20 created, 0 updated, 0 failed, 0 ignored\) in \d+(\.\d+)? seconds \(\d+(\.\d+)?\/min\) \- continuing with 'test_migration'/", $outputLine);
            } else {
                // The last log entry is different.
                $this->assertMatchesRegularExpression("/^\[notice\] Processed 19 items \(19 created, 0 updated, 0 failed, 0 ignored\) in \d+(\.\d+)? seconds \(\d+(\.\d+)?\/min\) \- done with 'test_migration'/", $outputLine);
            }
        }

        $this->drush('migrate:status', ['test_migration'], ['format' => 'json']);
        $output = $this->getOutputFromJSON(0);

        // Check also stats.
        $this->assertSame(300, $output['total']);
        $this->assertSame('199 (66.3%)', $output['imported']);
        $this->assertSame(101, $output['unprocessed']);
    }

    /**
     * Regression test when importing with --update and --idlist.
     *
     * @covers ::executeMigration
     * @see https://www.drupal.org/project/migrate_tools/issues/3015386
     */
    public function testImportingWithUpdateAndIdlist(): void
    {
        // Check a migration limited by ID.
        $this->drush('migrate:import', ['test_migration'], ['idlist' => '1']);
        $this->drush('migrate:status', ['test_migration'], [
            'format' => 'json',
        ]);
        $this->assertSame('1 (50%)', $this->getOutputFromJSON(0)['imported']);
        // Migrate all.
        $this->drush('migrate:import', ['test_migration']);
        $this->drush('migrate:status', ['test_migration'], [
            'format' => 'json',
        ]);
        $this->assertSame('2 (100%)', $this->getOutputFromJSON(0)['imported']);
        // Try to reimport with --idlist and --update.
        $this->drush('migrate:import', ['test_migration'], [
            'idlist' => '1',
            'update' => null,
        ]);
        $this->drush('migrate:status', ['test_migration'], [
          'format' => 'json',
        ]);
        // Check that now row needs update.
        $this->assertSame(0, $this->getOutputFromJSON(0)['needing_update']);
    }
}
