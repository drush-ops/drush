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
        $this->setupModulesForTests(['woot'], Path::join(__DIR__, '/../fixtures/modules/d8'));
        $this->drush('pm:enable', ['migrate', 'node', 'woot']);
    }

    /**
     * @covers ::import
     * @covers \Drush\Drupal\Migrate\MigrateExecutable::handleMissingSourceRows
     */
    public function testMissingSourceRows(): void
    {
        $this->drush('state:set', ['woot.test_migration_source_data_amount', 5]);
        $this->drush('migrate:import', ['test_migration']);
        $this->assertStringContainsString('[notice] Processed 5 items (5 created, 0 updated, 0 failed, 0 ignored)', $this->getErrorOutput());
        $this->drush('sql:query', ['SELECT title FROM node_field_data']);
        $this->assertSame(['Item 1', 'Item 2', 'Item 3', 'Item 4', 'Item 5'], $this->getOutputAsList());

        $this->drush('state:set', ['woot.test_migration_source_removed_rows', '2,4']);
        // Rebuild cache to get the new source plugin definition.
        $this->drush('cache:rebuild');

        $this->drush('migrate:import', ['test_migration'], ['delete' => null]);

        $this->assertStringContainsString('[notice] 2 items are missing from source and will be rolled back', $this->getErrorOutput());
        $this->assertStringContainsString("[notice] Rolled back 2 items - done with 'test_migration'", $this->getErrorOutput());
        $this->assertStringContainsString('[notice] Processed 0 items (0 created, 0 updated, 0 failed, 0 ignored)', $this->getErrorOutput());
        $this->drush('sql:query', ['SELECT title FROM node_field_data']);
        $this->assertSame(['Item 1', 'Item 3', 'Item 5'], $this->getOutputAsList());
    }

    /**
     * @covers ::messages
     * @covers ::fieldsSource
     */
    public function testMigrateMessagesAndFieldSource(): void
    {
        $this->drush('state:set', ['woot.test_migration_source_data_amount', 20]);
        $this->drush('state:set', ['woot.test_migrate_trigger_failures', true]);

        $this->drush('migrate:import', ['test_migration'], [
          'no-progress' => null,
        ], null, null, self::EXIT_ERROR);

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
        // @see woot_migrate_source_info_alter()
        $this->drush('state:set', ['woot.test_migration_source_data_amount', 300]);
        $this->drush('migrate:import', ['test_migration'], [
            'feedback' => 20,
            'limit' => 199,
            'no-progress' => null,
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
        $this->drush('state:set', ['woot.test_migration_source_data_amount', 50]);

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
        // @see woot_migrate_source_info_alter()
        $this->drush('state:set', ['woot.test_command_progress_bar.skip_count', true]);
        $this->drush('cache:rebuild');

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
}
