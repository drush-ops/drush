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
        $this->drush('pm:uninstall', ['migrate', 'node', 'woot']);
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
}
