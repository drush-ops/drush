<?php

namespace Unish;

use Webmozart\PathUtil\Path;

/**
 * @group slow
 * @group commands
 * @coversDefaultClass \Drush\Commands\core\MigrateRunnerCommands
 */
class MigrateRunnerTest extends CommandUnishTestCase
{
    use TestModuleHelperTrait;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();
        $this->setUpDrupal(1, true);
        $this->setupModulesForTests(['woot'], Path::join(__DIR__, 'resources/modules/d8'));
        $this->drush('pm:enable', ['migrate', 'node', 'woot']);
    }

    /**
     * @covers status
     */
    public function testMigrateStatus()
    {
        // No, arguments, no options.
        $this->drush('migrate:status', [], ['format' => 'json']);
        $output = $this->getOutputFromJSON(null, true);
        $actualIds = array_column($output, 'id');
        $this->assertCount(3, $actualIds);
        $this->assertContains('test_migration', $actualIds);
        $this->assertContains('test_migration_tagged', $actualIds);
        $this->assertContains('test_migration_untagged', $actualIds);

        // With arguments.
        $this->drush('migrate:status', ['test_migration_tagged,test_migration_untagged'], ['format' => 'json']);
        $output = $this->getOutputFromJSON(null, true);
        $actualIds = array_column($output, 'id');
        $this->assertCount(2, $actualIds);
        $this->assertContains('test_migration_tagged', $actualIds);
        $this->assertContains('test_migration_untagged', $actualIds);

        // Using --tag with value.
        $this->drush('migrate:status', [], ['tag' => 'tag1,tag2', 'format' => 'json']);
        $output = $this->getOutputFromJSON(null, true);

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
        $output = $this->getOutputFromJSON(null, true);
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
    }

    /**
     * @covers import
     * @covers rollback
     */
    public function testMigrateImportAndRollback()
    {
        $this->drush('migrate:import', ['test_migration']);
        // Check for the expected command output.
        $this->assertContains('Processed 2 items (2 created, 0 updated, 0 failed, 0 ignored)', $this->getErrorOutput());

        // Check that the migration import actually works.
        $eval = "echo \\Drupal\\node\\Entity\\Node::load(1)->label();";
        $this->drush('php:eval', [$eval]);
        $this->assertContains('foo', $this->getOutput());
        $eval = "echo \\Drupal\\node\\Entity\\Node::load(2)->label();";
        $this->drush('php:eval', [$eval]);
        $this->assertContains('bar', $this->getOutput());

        $this->drush('migrate:rollback', ['test_migration']);
        // Check for the expected command output.
        $this->assertContains('Rolled back 2 items', $this->getErrorOutput());

        // Check that the migration rollback actually works.
        $eval = "var_export(\\Drupal\\node\\Entity\\Node::load(1));";
        $this->drush('php:eval', [$eval]);
        $this->assertEquals('NULL', $this->getOutput());
        $eval = "var_export(\\Drupal\\node\\Entity\\Node::load(2));";
        $this->drush('php:eval', [$eval]);
        $this->assertEquals('NULL', $this->getOutput());
    }

    /**
     * @covers stop
     * @covers resetStatus
     */
    public function testMigrateStopAndResetStatus()
    {
        $this->drush('migrate:stop', ['test_migration']);
        // @todo Find a way to stop am migration that runs.
        $this->assertContains('Migration test_migration is idle', $this->getErrorOutput());

        $this->drush('migrate:reset', ['test_migration']);
        // @todo Find a way to reset am migration that is not idle.
        $this->assertContains('Migration test_migration is already Idle', $this->getErrorOutput());
    }

    /**
     * @covers messages
     * @covers fieldsSource
     */
    public function testMigrateMessagesAndFieldSource()
    {
        $this->drush('migrate:messages', ['test_migration']);
        // @todo Cover cases with non-empty message list.
        $this->assertContains('Level   Message   Source IDs hash', $this->getOutputRaw());

        $this->drush('migrate:fields-source', ['test_migration'], ['format' => 'json']);
        $output = $this->getOutputFromJSON(null, true);
        $this->assertEquals('id', $output[0]['machine_name']);
        $this->assertEquals('id', $output[0]['description']);
        $this->assertEquals('name', $output[1]['machine_name']);
        $this->assertEquals('name', $output[1]['description']);
    }
}
