<?php

declare(strict_types=1);

namespace Unish;

use Drupal\migrate\Plugin\MigrationInterface;

/**
 * @group commands
 * @coversDefaultClass \Drush\Commands\core\MigrateRunnerCommands
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
        $this->drush('pm:install', ['migrate', 'node', 'woot']);
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
        // @see \Drush\Commands\core\MigrateRunnerCommands::getMigrationList()
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
        // Reset status just in case.
        $this->drush('migrate:reset-status', ['test_migration']);

        // Trigger logging in ProcessRowTestSubscriber::onPrepareRow().
        // @see \Drupal\woot\EventSubscriber\ProcessRowTestSubscriber::onPrepareRow()
        // @see \Drupal\woot\EventSubscriber\PreRowDeleteTestSubscriber::onPreRowDelete()
        $this->drush('state:set', ['woot.migrate_runner.trigger_failures', true]);

        if (!$this->isDrupalGreaterThanOrEqualTo('11.1.0')) {
            // Warm-up the 'migrate_prepare_row' hook implementations cache to
            // test that system_migrate_prepare_row() is picked-up during
            // import. See MigrateEvents::DRUSH_MIGRATE_PREPARE_ROW test, later.
            // @see system_migrate_prepare_row()
            // @see \Drupal\woot\EventSubscriber\ProcessRowTestSubscriber::onPrepareRow()
            $this->drush('php:eval', ["Drupal::moduleHandler()->invokeAll('migrate_prepare_row');"]);
        }

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
        $this->drush('migrate:reset-status', ['test_migration']);

        // Reset the flag, so we won't fail the rollback again.
        $this->drush('state:delete', ['woot.migrate_runner.trigger_failures']);

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
        $this->drush('state:set', ['woot.migrate_runner.source_data_amount', '50']);

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
     * @covers ::import
     * @covers \Drush\Drupal\Migrate\MigrateExecutable::handleMissingSourceRows
     */
    public function testMissingSourceRows(): void
    {
        $this->drush('state:set', ['woot.migrate_runner.source_data_amount', '5']);
        $this->drush('migrate:import', ['test_migration']);
        $this->assertStringContainsString('[notice] Processed 5 items (5 created, 0 updated, 0 failed, 0 ignored)', $this->getErrorOutput());
        $this->drush('sql:query', ['SELECT title FROM node_field_data']);
        $this->assertSame(['Item 1', 'Item 2', 'Item 3', 'Item 4', 'Item 5'], $this->getOutputAsList());

        $this->drush('state:set', ['woot.migrate_runner.removed_source_rows', '2,4']);
        // Get fresh plugin definitions.
        drupal_flush_all_caches();

        $this->drush('migrate:import', ['test_migration'], ['delete' => null]);

        $this->assertStringContainsString('[notice] 2 items are missing from source and will be rolled back', $this->getErrorOutput());
        $this->assertStringContainsString("[notice] Rolled back 2 items - done with 'test_migration'", $this->getErrorOutput());
        $this->assertStringContainsString('[notice] Processed 0 items (0 created, 0 updated, 0 failed, 0 ignored)', $this->getErrorOutput());
        $this->drush('sql:query', ['SELECT title FROM node_field_data']);
        $this->assertSame(['Item 1', 'Item 3', 'Item 5'], $this->getOutputAsList());
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

        $this->drush('migrate:reset-status', ['test_migration']);
        // @todo Find a way to reset a migration that is not idle.
        $this->assertStringContainsString('Migration test_migration is already Idle', $this->getErrorOutput());
    }

    /**
     * @covers ::messages
     * @covers ::fieldsSource
     */
    public function testMigrateMessagesAndFieldSource(): void
    {
        $this->drush('state:set', ['woot.migrate_runner.source_data_amount', '20']);
        $this->drush('state:set', ['woot.migrate_runner.trigger_failures', true]);

        $this->drush('migrate:import', ['test_migration'], [
          'no-progress' => null,
        ], self::EXIT_ERROR);

        // All messages together.
        $this->drush('migrate:messages', ['test_migration'], ['format' => 'json']);
        $output = $this->getOutputFromJSON();
        // @see \Drupal\woot\Plugin\migrate\process\TestFailProcess::transform()
        $this->assertCount(3, $output);
        $this->assertEquals(1, $output[0]['level']);
        $this->assertSame('2', $output[0]['source_ids']);
        $this->assertEmpty($output[0]['destination_ids']);
        $this->assertStringContainsString('ID 2 should fail', $output[0]['message']);
        $this->assertEquals(1, $output[1]['level']);
        $this->assertSame('9', $output[1]['source_ids']);
        $this->assertEmpty($output[0]['destination_ids']);
        $this->assertStringContainsString('ID 9 should fail', $output[1]['message']);
        $this->assertEquals(1, $output[2]['level']);
        $this->assertStringContainsString('ID 17 should fail', $output[2]['message']);
        $this->assertSame('17', $output[2]['source_ids']);
        $this->assertEmpty($output[0]['destination_ids']);

        // Only one invalid ID.
        $this->drush('migrate:messages', ['test_migration'], ['format' => 'json', 'idlist' => '100']);
        $output = $this->getOutputFromJSON();
        $this->assertCount(0, $output);

        // Only one valid ID.
        $this->drush('migrate:messages', ['test_migration'], ['format' => 'json', 'idlist' => '2']);
        $output = $this->getOutputFromJSON();
        // @see \Drupal\woot\Plugin\migrate\process\TestFailProcess::transform()
        $this->assertCount(1, $output);
        $this->assertEquals(1, $output[0]['level']);
        $this->assertSame('2', $output[0]['source_ids']);
        $this->assertEmpty($output[0]['destination_ids']);
        $this->assertStringContainsString('ID 2 should fail', $output[0]['message']);

        // Three valid IDs, two with data, one without, and one invalid ID.
        $this->drush('migrate:messages', ['test_migration'], ['format' => 'json', 'idlist' => '1,2,9,100']);
        $output = $this->getOutputFromJSON();
        // @see \Drupal\woot\Plugin\migrate\process\TestFailProcess::transform()
        $this->assertCount(2, $output);
        $this->assertEquals(1, $output[0]['level']);
        $this->assertSame('2', $output[0]['source_ids']);
        $this->assertEmpty($output[0]['destination_ids']);
        $this->assertStringContainsString('ID 2 should fail', $output[0]['message']);
        $this->assertEquals(1, $output[1]['level']);
        $this->assertSame('9', $output[1]['source_ids']);
        $this->assertEmpty($output[0]['destination_ids']);
        $this->assertStringContainsString('ID 9 should fail', $output[1]['message']);

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
        // Set the test_migration source to 300 records.
        // @see woot_migration_plugins_alter()
        $this->drush('state:set', ['woot.migrate_runner.source_data_amount', '300']);
        $this->drush('migrate:import', ['test_migration'], [
            'feedback' => 20,
            'limit' => 199,
            'no-progress' => null,
        ]);

        $importOutput = array_values(array_filter(array_map('trim', $this->getErrorOutputAsList()), function (string $line): bool {
            return str_starts_with($line, '[notice]');
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
          'fields' => 'needing_update',
          'format' => 'json',
        ]);
        // Check that no row needs update.
        $this->assertSame(0, $this->getOutputFromJSON(0)['needing_update']);
    }

    /**
     * @covers \Drush\Drupal\Migrate\MigrateExecutable::initProgressBar
     */
    public function testCommandProgressBar(): void
    {
        $this->drush('state:set', ['woot.migrate_runner.source_data_amount', '50']);

        // Check an import and rollback with progress bar.
        $this->drush('migrate:import', ['test_migration']);
        $this->assertProgressBar();
        $this->drush('migrate:rollback', ['test_migration']);
        $this->assertProgressBar();

        // Check that progress bar won't show when --no-progress is passed.
        $this->drush('migrate:import', ['test_migration'], ['no-progress' => null]);
        $this->assertNoProgressBar();
        $this->drush('migrate:rollback', ['test_migration'], ['no-progress' => null]);
        $this->assertNoProgressBar();

        // Check that progress bar won't show when --feedback is passed.
        $this->drush('migrate:import', ['test_migration'], ['feedback' => 10]);
        $this->assertNoProgressBar();
        $this->drush('migrate:rollback', ['test_migration'], ['feedback' => 10]);
        $this->assertNoProgressBar();

        // Set the 'test_migration' source plugin to skip count.
        // @see woot_migration_plugins_alter()
        $this->drush('state:set', ['woot.migrate_runner.progress_bar_skip_count', true]);
        // Get fresh plugin definitions.
        drupal_flush_all_caches();

        // Check that progress bar won't show when the source skips count.
        $this->drush('migrate:import', ['test_migration']);
        $this->assertNoProgressBar();
        $this->drush('migrate:rollback', ['test_migration']);
        $this->assertNoProgressBar();
    }

    /**
     * Asserts that the command exposes a progress bar.
     */
    protected function assertProgressBar(): void
    {
        $this->progressBarAssertionHelper(true);
    }

    /**
     * Asserts that the command doesn't expose a progress bar.
     */
    protected function assertNoProgressBar(): void
    {
        $this->progressBarAssertionHelper(false);
    }

    /**
     * @param bool $assertHasProgressBar
     */
    protected function progressBarAssertionHelper(bool $assertHasProgressBar): void
    {
        static $expectedProgressBars = [
          '5/50 [▓▓░░░░░░░░░░░░░░░░░░░░░░░░░░]  10%',
          '10/50 [▓▓▓▓▓░░░░░░░░░░░░░░░░░░░░░░░]  20%',
          '15/50 [▓▓▓▓▓▓▓▓░░░░░░░░░░░░░░░░░░░░]  30%',
          '20/50 [▓▓▓▓▓▓▓▓▓▓▓░░░░░░░░░░░░░░░░░]  40%',
          '25/50 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓░░░░░░░░░░░░░░]  50%',
          '30/50 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓░░░░░░░░░░░░]  60%',
          '35/50 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓░░░░░░░░░]  70%',
          '40/50 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓░░░░░░]  80%',
          '45/50 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓░░░]  90%',
          '50/50 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%',
        ];

        // On Windows systems the new line delimiter is a CR+LF (\r\n)
        // sequence instead of LF (\n) as it is on *nix systems.
        $actualOutput = str_replace("\r\n", "\n", $this->getErrorOutputRaw());

        if ($this->isWindows()) {
            // Only standard bar is supported.
            array_walk($expectedProgressBars, function (string &$bar): void {
                $bar = str_replace('▓░', '=>', $bar);
                $bar = str_replace('▓', '=', $bar);
                $bar = str_replace('░', '-', $bar);
            });
        }

        foreach ($expectedProgressBars as $expectedProgressBar) {
            if ($assertHasProgressBar) {
                $this->assertStringContainsString($expectedProgressBar, $actualOutput);
            } else {
                $this->assertStringNotContainsString($expectedProgressBar, $actualOutput);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        // Cleanup any created content.
        $this->drush('entity:delete', ['node']);

        // Uninstalling Migrate module doesn't automatically drop the tables.
        // @see https://www.drupal.org/project/drupal/issues/2713327
        $migrations = \Drupal::service('plugin.manager.migration')->createInstances([]);
        array_walk($migrations, function (MigrationInterface $migration): void {
            $migration->getIdMap()->destroy();
        });

        // Uninstall test modules.
        $this->drush('pm:uninstall', ['migrate', 'node', 'woot']);

        // Cleanup any testing state vars.
        $state = \Drupal::state();
        foreach (['trigger_failures', 'source_data_amount', 'removed_source_rows', 'progress_bar_skip_count'] as $var) {
            $state->delete("woot.migrate_runner.$var");
        }

        parent::tearDown();
    }
}
