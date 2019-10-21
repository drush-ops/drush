<?php

namespace Unish;

use Webmozart\PathUtil\Path;

/**
 *  @group slow
 *  @group commands
 */
class UpdateDBTest extends CommandUnishTestCase
{
    use TestModuleHelperTrait;

    protected $pathPostUpdate;

    public function testUpdateDBStatus()
    {
        $this->setUpDrupal(1, true);
        $this->drush('pm:enable', ['drush_empty_module']);
        $this->drush('updatedb:status');
        $err = $this->getErrorOutput();
        $this->assertContains('[success] No database updates required.', $err);

        // Force a pending update.
        $this->drush('php-script', ['updatedb_script'], ['script-path' => __DIR__ . '/resources']);

        // Assert that pending hook_update_n appears
        $this->drush('updatedb:status', [], ['format' => 'json']);
        $out = $this->getOutputFromJSON('drush_empty_module_update_8001');
        $this->assertContains('Fake update hook', trim($out['description']));

        // Run hook_update_n
        $this->drush('updatedb', []);

        // Assert that we ran hook_update_n properly
        $this->drush('updatedb:status');
        $err = $this->getErrorOutput();
        $this->assertContains('[success] No database updates required.', $err);

        // Assure that a pending post-update is reported.
        $this->pathPostUpdate = Path::join($this->webroot(), 'modules/unish/drush_empty_module/drush_empty_module.post_update.php');
        copy(__DIR__ . '/resources/drush_empty_module.post_update.php', $this->pathPostUpdate);
        $this->drush('updatedb:status', [], ['format' => 'json']);
        $out = $this->getOutputFromJSON('drush_empty_module-post-null_op');
        $this->assertContains('This is a test of the emergency broadcast system.', trim($out['description']));
    }

    /**
     * Tests that the updatedb command reports failed updates properly.
     *
     * @dataProvider failedUpdateProvider
     */
    public function testFailedUpdate($last_successful_update, $expected_status_report, $expected_update_log_output)
    {
        // This test relies on being able to catch fatal errors. Catching
        // throwables has been introduced in PHP 7.0 and is not available in
        // earlier versions.
        if (version_compare(phpversion(), '7.0.0') < 0) {
            $this->markTestSkipped('Catching fatal errors is supported in PHP 7.0 and higher.');
        }
        $this->setUpDrupal(1, true);
        $options = [
            'yes' => null,
        ];
        $this->setupModulesForTests(['woot'], Path::join(__DIR__, 'resources/modules/d8'));
        $this->drush('pm-enable', ['woot'], $options);

        // Force a pending update.
        $this->drush('php-script', ['updatedb_script'], ['script-path' => __DIR__ . '/resources']);

        // Force re-run of woot_update_8101().
        $this->drush('php:eval', array('drupal_set_installed_schema_version("woot", ' . $last_successful_update . ')'), $options);

        // Force re-run of the post-update woot_post_update_failing().
        $this->forcePostUpdate('woot_post_update_failing', $options);

        // Run updates.
        $this->drush('updatedb', [], $options, null, null, self::EXIT_ERROR);

        foreach ($expected_status_report as $needle) {
            $this->assertContains($needle, $this->getOutput());
        }
        foreach ($expected_update_log_output as $needle) {
            $this->assertContains($needle, $this->getErrorOutput());
        }
    }

    /**
     * Data provider for ::testFailedUpdate().
     */
    public function failedUpdateProvider()
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
                    'woot     8104        hook_update_n   Another good update.',
                    'woot     failing     post-update     Failing post-update.',
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
                    'woot     8103        hook_update_n   Failing update 2.',
                    'woot     8104        hook_update_n   Another good update.',
                    'woot     failing     post-update     Failing post-update.',
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
        $this->setupModulesForTests(['woot'], Path::join(__DIR__, 'resources/modules/d8'));
        $this->drush('pm-enable', ['woot'], $options);

        // Force re-run of woot_update_8104().
        $this->drush('php:eval', array('drupal_set_installed_schema_version("woot", 8103)'), $options);

        // Force re-run of post-update hooks.
        $this->forcePostUpdate('woot_post_update_a', $options);
        $this->forcePostUpdate('woot_post_update_failing', $options);

        // Run updates.
        $this->drush('updatedb', [], $options, null, null, self::EXIT_ERROR);
        $this->assertContains('woot     a           post-update     Successful post-update.', $this->getOutput());
        $this->assertContains('woot     failing     post-update     Failing post-update.', $this->getOutput());
        $this->assertContains('This is the exception message thrown in woot_post_update_failing', $this->getErrorOutput());
        $this->assertContains('Update failed: woot_post_update_failing', $this->getErrorOutput());
        $this->assertContains('Update aborted by: woot_post_update_failing', $this->getErrorOutput());
        $this->assertContains('Finished performing updates.', $this->getErrorOutput());
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
        $this->setupModulesForTests(['woot'], Path::join(__DIR__, 'resources/modules/d8'));
        $this->drush('pm-enable', ['woot'], $options);

        // Force re-run of the post-update woot_post_update_install_drush_empty_module().
        $this->forcePostUpdate('woot_post_update_install_drush_empty_module', $options);

        // Force a flush of the dependency injection container, so that we can
        // test that the container can be correctly rebuilt even if new services
        // are introduced that depend on modules that are not enabled yet.
        $this->drush('unit-invalidate-container', [], $options);

        // Introduce a new service in the Woot module that depends on a service
        // in the Devel module (which is not yet enabled).
        $filename = Path::join($root, 'modules/unish/woot/woot.services.yml');
        $serviceDefinition = <<<YAML_FRAGMENT
  woot.depending_service:
    class: Drupal\woot\DependingService
    arguments: ['@drush_empty_module.service']
YAML_FRAGMENT;
        file_put_contents($filename, $serviceDefinition, FILE_APPEND);

        $filename = Path::join($root, 'modules/unish/woot/woot.info.yml');
        $moduleDependency = <<<YAML_FRAGMENT
dependencies:
  - drush_empty_module
YAML_FRAGMENT;
        file_put_contents($filename, $moduleDependency, FILE_APPEND);

        // Run updates.
        $this->drush('updatedb');

        // Assert that the updates were run correctly.
        $this->drush('updatedb:status');
        $err = $this->getErrorOutput();
        $this->assertContains('[success] No database updates required.', $err);
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
        $this->setupModulesForTests(['woot'], Path::join(__DIR__, 'resources/modules/d8'));
        $this->drush('pm-enable', ['woot'], $options);

        // Force re-run of woot_update_8104() which is expected to be completed successfully.
        $this->drush('php:eval', array('drupal_set_installed_schema_version("woot", 8103)'), $options);

        // Force re-run of post-update hooks which are expected to be completed successfully.
        $this->forcePostUpdate('woot_post_update_a', $options);
        $this->forcePostUpdate('woot_post_update_render', $options);

        // Run updates.
        $this->drush('updatedb', [], $options);
        // Check output.
        $this->assertContains('woot 8104 hook_update_n Another good update.', $this->getSimplifiedOutput());
        $this->assertContains('woot a post-update Successful post-update.', $this->getSimplifiedOutput());
        $this->assertContains('woot render post-update Renders some content.', $this->getSimplifiedOutput());
        // Check error output.
        $this->assertContains('Update started: woot_update_8104', $this->getErrorOutput());
        $this->assertContains('Finished performing updates.', $this->getErrorOutput());
        $this->assertNotContains('Failed', $this->getErrorOutput());
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
        $this->setupModulesForTests(['woot'], Path::join(__DIR__, 'resources/modules/d8'));
        $this->drush('pm:enable', ['woot'], $options);

        // Force re-run of woot_update_8105().
        $this->drush('php:eval', ['drupal_set_installed_schema_version("woot", 8104)'], $options);
        // Force re-run of woot_post_update_batch().
        $this->forcePostUpdate('woot_post_update_batch', $options);

        // Run updates.
        $this->drush('updatedb', [], $options);

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

        $this->assertContains($expected_update_output, $actual_output);
        $this->assertContains($expected_post_update_output, $actual_output);
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
        $this->setupModulesForTests(['woot'], Path::join(__DIR__, 'resources/modules/d8'));
        $this->drush('pm:enable', ['woot'], $options);

        // Force re-run of woot_update_8106().
        $this->drush('php:eval', ['drupal_set_installed_schema_version("woot", 8105)'], $options);

        // Run updates.
        $this->drush('updatedb', [], $options);

        // Check that the post-update function returns the new entity type ID.
        $this->assertContains('[notice] taxonomy_term', $this->getErrorOutputRaw());

        // Check that the new entity type is installed.
        $this->drush('php:eval', ['woot_get_taxonomy_term_entity_type_id();']);
        $this->assertContains('taxonomy_term', $this->getOutputRaw());
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
        $this->setupModulesForTests(['woot'], Path::join(__DIR__, 'resources/modules/d8'));
        $this->drush('pm:enable', ['woot'], $options);

        // Force re-run of woot_post_update_install_taxonomy().
        $this->forcePostUpdate('woot_post_update_install_taxonomy', $options);

        // Run updates.
        $this->drush('updatedb', [], $options);

        // Check that the post-update function returns the new entity type ID.
        $this->assertContains('[notice] taxonomy_term', $this->getErrorOutputRaw());

        // Check that the new entity type is installed.
        $this->drush('php:eval', ['woot_get_taxonomy_term_entity_type_id();']);
        $this->assertContains('taxonomy_term', $this->getOutputRaw());
    }

    public function tearDown()
    {
        $this->recursiveDelete($this->pathPostUpdate, true);
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
        $this->drush('sql:query', ["SELECT value FROM key_value WHERE collection = 'post_update' AND name = 'existing_updates'"], $options);
        $functions = unserialize($this->getOutput());
        unset($functions[array_search($hook, $functions)]);
        $functions = serialize($functions);
        $this->drush('sql:query', ["UPDATE key_value SET value = '$functions' WHERE collection = 'post_update' AND name = 'existing_updates'"], $options);
    }
}
