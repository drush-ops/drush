<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\PhpCommands;
use Drush\Commands\core\UpdateDBCommands;
use Drush\Commands\pm\PmCommands;
use Drush\Commands\sql\SqlCommands;
use Symfony\Component\Filesystem\Path;

/**
 *  @group slow
 *  @group commands
 */
class UpdateDBTest extends CommandUnishTestCase
{
    use TestModuleHelperTrait;

    protected ?string $pathPostUpdate = null;

    public function testUpdateDBStatus()
    {
        $this->setUpDrupal(1, true);
        $this->drush(PmCommands::INSTALL, ['drush_empty_module']);
        $this->drush(UpdateDBCommands::STATUS);
        $err = $this->getErrorOutput();
        $this->assertStringContainsString('[success] No database updates required.', $err);

        // Force a pending update.
        $this->drush(PhpCommands::SCRIPT, ['updatedb_script'], ['script-path' => __DIR__ . '/resources']);

        // Assert that pending hook_update_n appears
        $this->drush(UpdateDBCommands::STATUS, [], ['format' => 'json']);
        $out = $this->getOutputFromJSON('drush_empty_module_update_8001');
        $this->assertStringContainsString('Fake update hook', trim($out['description']));

        // Run hook_update_n
        $this->drush(UpdateDBCommands::UPDATEDB, []);

        // Assert that we ran hook_update_n properly
        $this->drush(UpdateDBCommands::STATUS);
        $err = $this->getErrorOutput();
        $this->assertStringContainsString('[success] No database updates required.', $err);

        // Assure that a pending post-update is reported.
        $this->pathPostUpdate = Path::join($this->webroot(), 'modules/unish/drush_empty_module/drush_empty_module.post_update.php');
        copy(__DIR__ . '/resources/drush_empty_module.post_update.php', $this->pathPostUpdate);
        $this->drush(UpdateDBCommands::STATUS, [], ['format' => 'json']);
        $out = $this->getOutputFromJSON('drush_empty_module-post-null_op');
        $this->assertStringContainsString('This is a test of the emergency broadcast system.', trim($out['description']));
    }

    /**
     * Tests that the updatedb command reports failed updates properly.
     *
     * @dataProvider failedUpdateProvider
     */
    public function testFailedUpdate($last_successful_update, $expected_status_report, $expected_update_log_output)
    {
        $this->setUpDrupal(1, true);
        $options = [
            'yes' => null,
        ];
        $this->drush(PmCommands::INSTALL, ['woot'], $options);

        // Force a pending update.
        $this->drush(PhpCommands::SCRIPT, ['updatedb_script'], ['script-path' => __DIR__ . '/resources']);

        // Force re-run of woot_update_8101().
        $this->drush(PhpCommands::EVAL, array('Drupal::service("update.update_hook_registry")->setInstalledVersion("woot", ' . $last_successful_update . ')'), $options);

        // Force re-run of the post-update woot_post_update_failing().
        $this->forcePostUpdate('woot_post_update_failing', $options);

        // Run updates.
        $this->drush(UpdateDBCommands::UPDATEDB, [], $options, null, null, self::EXIT_ERROR);

        foreach ($expected_status_report as $needle) {
            $this->assertStringContainsString($needle, $this->getOutput());
        }
        foreach ($expected_update_log_output as $needle) {
            $this->assertStringContainsString($needle, $this->getErrorOutput());
        }
    }

    /**
     * Data provider for ::testFailedUpdate().
     */
    public static function failedUpdateProvider()
    {
        return [
            [
                // The last successfully completed update. This means that the
                // updates starting with woot_update_8101() will be performed in
                // the test.
                8100,
                // The expected status report that will be output before the
                // test is initiated.
                [
                    'woot     8104        hook_update_n',
                    'woot     failing     post-update',
                ],
                [
                    '[notice] Update started: woot_update_8101',
                    'This is the exception message thrown in woot_update_8102',
                    'Update failed: woot_update_8102',
                    'Update aborted by: woot_update_8102',
                    'Finished performing updates.',
                ],
            ],
            [
                // The last successfully completed update. This means that the
                // updates starting with woot_update_8103() will be performed in
                // the test.
                8102,
                // The expected status report that will be output before the
                // test is initiated.
                [
                    'woot     8103        hook_update_n',
                    'woot     8104        hook_update_n',
                    'woot     failing     post-update',
                ],
                [
                    'Update started: woot_update_8103',
                    'Call to undefined function non_existing_function()',
                    'Update failed: woot_update_8103',
                    'Update aborted by: woot_update_8103',
                    'Finished performing updates.',
                ],
            ],
        ];
    }

    /**
     * Tests that a failed post-update is handled correctly.
     */
    public function testFailedPostUpdate()
    {
        if ($this->isWindows()) {
            $this->markTestSkipped('See https://github.com/consolidation/site-process/pull/27');
        }

        $this->setUpDrupal(1, true);
        $options = [
            'yes' => null,
        ];
        $this->drush(PmCommands::INSTALL, ['woot'], $options);

        // Force re-run of woot_update_8104().
        $this->drush(PhpCommands::EVAL, array('Drupal::service("update.update_hook_registry")->setInstalledVersion("woot", 8103)'), $options);

        // Force re-run of post-update hooks.
        $this->forcePostUpdate('woot_post_update_a', $options);
        $this->forcePostUpdate('woot_post_update_failing', $options);

        // Run updates.
        $this->drush(UpdateDBCommands::UPDATEDB, [], $options, null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('woot     a           post-update     Successful post-update.', $this->getOutput());
        $this->assertStringContainsString('woot     failing     post-update     Failing post-update.', $this->getOutput());
        $this->assertStringContainsString('This is the exception message thrown in woot_post_update_failing', $this->getErrorOutput());
        $this->assertStringContainsString('Update failed: woot_post_update_failing', $this->getErrorOutput());
        $this->assertStringContainsString('Update aborted by: woot_post_update_failing', $this->getErrorOutput());
        $this->assertStringContainsString('Finished performing updates.', $this->getErrorOutput());
    }

    /**
     * Tests that the updatedb command works when new services are introduced.
     *
     * This is a regression test for a bug that prevented the updatedb command
     * from running when the update introduces a new module, and introduces a
     * new service in an existing module that has a dependency on the new
     * module.
     *
     * @see https://github.com/drush-ops/drush/issues/3193
     * @see https://www.drupal.org/project/drupal/issues/2863986
     */
    public function testUpdateModuleWithServiceDependency()
    {
        $root = $this->webroot();

        // This test currently depends on a patched version of Drupal core. Skip
        // the test if the patch is not present. We detect this by checking if
        // the test module `new_dependency_test` is available.
        // @see https://www.drupal.org/project/drupal/issues/2863986
        // To apply the patch, execute the following in the `./sut/` folder:
        // $ curl -S https://www.drupal.org/files/issues/2863986-62.patch | patch -p1
        $filename = $root . '/core/modules/system/tests/modules/new_dependency_test';
        if (!file_exists($filename)) {
            $this->markTestSkipped('Requires a patched Drupal. See https://github.com/drush-ops/drush/pull/3738.');
        }

        $this->setUpDrupal(1, true);
        $options = [
            'include' => __DIR__,
        ];
        $this->drush(PmCommands::INSTALL, ['woot'], $options);

        // Force re-run of the post-update woot_post_update_install_drush_empty_module().
        $this->forcePostUpdate('woot_post_update_install_drush_empty_module', $options);

        // Force a flush of the dependency injection container, so that we can
        // test that the container can be correctly rebuilt even if new services
        // are introduced that depend on modules that are not enabled yet.
        $this->drush('unit-invalidate-container', [], $options);

        // Introduce a new service in the Woot module that depends on a service
        // in the Devel module (which is not yet enabled).
        $filename = Path::join($root, self::WOOT_SERVICES_PATH);
        copy($filename, $filename . '.BAK');
        $serviceDefinition = <<<YAML_FRAGMENT
  woot.depending_service:
    class: Drupal\woot\DependingService
    arguments: ['@drush_empty_module.service']
YAML_FRAGMENT;
        file_put_contents($filename, $serviceDefinition, FILE_APPEND);

        $filename = Path::join($root, self::WOOT_INFO_PATH);
        copy($filename, $filename . '.BAK');
        $moduleDependency = <<<YAML_FRAGMENT
dependencies:
  - drush_empty_module
YAML_FRAGMENT;
        file_put_contents($filename, $moduleDependency, FILE_APPEND);

        // Run updates.
        $this->drush(UpdateDBCommands::UPDATEDB);

        // Assert that the updates were run correctly.
        $this->drush(UpdateDBCommands::STATUS);
        $err = $this->getErrorOutput();
        $this->assertStringContainsString('[success] No database updates required.', $err);
    }

    /**
     * Tests that updates and post-updated can be executed successfully.
     */
    public function testSuccessfulUpdate()
    {
        $this->setUpDrupal(1, true);
        $options = [
            'yes' => null,
        ];
        $this->drush(PmCommands::INSTALL, ['woot'], $options);

        // Force re-run of woot_update_8104() which is expected to be completed successfully.
        $this->drush(PhpCommands::EVAL, array('Drupal::service("update.update_hook_registry")->setInstalledVersion("woot", 8103)'), $options);

        // Force re-run of post-update hooks which are expected to be completed successfully.
        $this->forcePostUpdate('woot_post_update_a', $options);
        $this->forcePostUpdate('woot_post_update_render', $options);

        // Run updates.
        $this->drush(UpdateDBCommands::UPDATEDB, [], $options);
        // Check output.
        $this->assertStringContainsString('woot 8104 hook_update_n', $this->getSimplifiedOutput());
        $this->assertStringContainsString('woot a post-update Successful post-update.', $this->getSimplifiedOutput());
        $this->assertStringContainsString('woot render post-update Renders some content.', $this->getSimplifiedOutput());
        // Check error output.
        $this->assertStringContainsString('Update started: woot_update_8104', $this->getErrorOutput());
        $this->assertStringContainsString('Finished performing updates.', $this->getErrorOutput());
        $this->assertStringNotContainsString('Failed', $this->getErrorOutput());
    }

    /**
     * Tests the output on batch update.
     */
    public function testBatchUpdateLogMessages()
    {
        $options = [
            'yes' => null,
        ];
        $this->setUpDrupal(1, true);
        $this->drush(PmCommands::INSTALL, ['woot'], $options);

        // Force re-run of woot_update_8105().
        $this->drush(PhpCommands::EVAL, ['Drupal::service("update.update_hook_registry")->setInstalledVersion("woot", 8104)'], $options);
        // Force re-run of woot_post_update_batch().
        $this->forcePostUpdate('woot_post_update_batch', $options);

        // Run updates.
        $this->drush(UpdateDBCommands::UPDATEDB, [], $options);

        $expected_update_output = <<<UPDATE
>  [notice] Update started: woot_update_8105
>  [notice] Iteration 1.
>  [notice] Iteration 2.
>  [notice] Finished at 3.
>  [notice] Update completed: woot_update_8105
UPDATE;
        $expected_post_update_output = <<<POST_UPDATE
>  [notice] Update started: woot_post_update_batch
>  [notice] Iteration 1.
>  [notice] Iteration 2.
>  [notice] Finished at 3.
>  [notice] Update completed: woot_post_update_batch
POST_UPDATE;

        // On Windows systems the new line delimiter is a CR+LF (\r\n) sequence
        // instead of LF (\n) as it is on *nix systems.
        $actual_output = str_replace("\r\n", "\n", $this->getErrorOutputRaw());

        $this->assertStringContainsString($expected_update_output, $actual_output);
        $this->assertStringContainsString($expected_post_update_output, $actual_output);
    }

    /**
     * Tests installing modules with entity type definitions via update hooks.
     */
    public function testEnableModuleViaUpdate()
    {
        $options = [
            'yes' => null,
        ];
        $this->setUpDrupal(1, true);
        $this->drush(PmCommands::INSTALL, ['woot'], $options);

        // Force re-run of woot_update_8106().
        $this->drush(PhpCommands::EVAL, ['Drupal::service("update.update_hook_registry")->setInstalledVersion("woot", 8105)'], $options);

        // Run updates.
        $this->drush(UpdateDBCommands::UPDATEDB, [], $options);

        // Check that the post-update function returns the new entity type ID.
        $this->assertStringContainsString('[notice] taxonomy_term', $this->getErrorOutputRaw());

        // Check that the new entity type is installed.
        $this->drush(PhpCommands::EVAL, ['woot_get_taxonomy_term_entity_type_id();']);
        $this->assertStringContainsString('taxonomy_term', $this->getOutputRaw());
    }

    /**
     * Tests installing modules with entity type definitions via post-update hooks.
     */
    public function testEnableModuleViaPostUpdate()
    {
        $options = [
            'yes' => null,
        ];
        $this->setUpDrupal(1, true);
        $this->drush(PmCommands::INSTALL, ['woot'], $options);

        // Force re-run of woot_post_update_install_taxonomy().
        $this->forcePostUpdate('woot_post_update_install_taxonomy', $options);

        // Run updates.
        $this->drush(UpdateDBCommands::UPDATEDB, [], $options);

        // Check that the post-update function returns the new entity type ID.
        $this->assertStringContainsString('[notice] taxonomy_term', $this->getErrorOutputRaw());

        // Check that the new entity type is installed.
        $this->drush(PhpCommands::EVAL, ['woot_get_taxonomy_term_entity_type_id();']);
        $this->assertStringContainsString('taxonomy_term', $this->getOutputRaw());
    }

    public function tearDown(): void
    {
        $this->recursiveDelete($this->pathPostUpdate, true);

        // Undo our yml mess.
        $filenames = [
            Path::join($this->webroot(), self::WOOT_INFO_PATH),
            Path::join($this->webroot(), self::WOOT_SERVICES_PATH),
        ];
        foreach ($filenames as $filename) {
            if (file_exists($filename . '.BAK')) {
                rename($filename . '.BAK', $filename);
            }
        }

        parent::tearDown();
    }

    /**
     * Forces a post-update hook to run again on the next database update.
     *
     * @param string $hook
     *   The name of the hook that needs to be run again.
     * @param array $options
     *   An associative array containing options for the `sql:query` command.
     */
    protected function forcePostUpdate($hook, array $options)
    {
        $this->drush(SqlCommands::QUERY, ["SELECT value FROM key_value WHERE collection = 'post_update' AND name = 'existing_updates'"], $options);
        $functions = unserialize($this->getOutput());
        unset($functions[array_search($hook, $functions)]);
        $functions = serialize($functions);
        $this->drush(SqlCommands::QUERY, ["UPDATE key_value SET value = '$functions' WHERE collection = 'post_update' AND name = 'existing_updates'"], $options);
    }
}
