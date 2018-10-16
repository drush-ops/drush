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
        $this->drush('pm:enable', ['devel']);
        $this->drush('updatedb:status', [], ['format' => 'json']);
        $out = $this->getOutputFromJSON();
        $this->assertNull($out);

        // Force a pending update.
        $this->drush('php-script', ['updatedb_script'], ['script-path' => __DIR__ . '/resources']);

        // Assert that pending hook_update_n appears
        $this->drush('updatedb:status', [], ['format' => 'json']);
        $out = $this->getOutputFromJSON('devel_update_8002');
        $this->assertEquals('Add enforced dependencies to system.menu.devel', trim($out->description));

        // Run hook_update_n
        $this->drush('updatedb', []);

        // Assert that we ran hook_update_n properly
        $this->drush('updatedb:status', [], ['format' => 'json']);
        $out = $this->getOutputFromJSON();
        $this->assertNull($out);

        // Assure that a pending post-update is reported.
        $this->pathPostUpdate = Path::join($this->webroot(), 'modules/unish/devel/devel.post_update.php');
        copy(__DIR__ . '/resources/devel.post_update.php', $this->pathPostUpdate);
        $this->drush('updatedb:status', [], ['format' => 'json']);
        $out = $this->getOutputFromJSON('devel-post-null_op');
        $this->assertEquals('This is a test of the emergency broadcast system.', trim($out->description));
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
        $sites = $this->setUpDrupal(1, true);
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

        $this->assertOutputEquals(preg_replace('#  *#', ' ', $this->simplifyOutput($expected_status_report)));
        $this->assertErrorOutputEquals(preg_replace('#  *#', ' ', $this->simplifyOutput($expected_update_log_output)));
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
                <<<LOG
 -------- ----------- --------------- -----------------------
  Module   Update ID   Type            Description
 -------- ----------- --------------- -----------------------
  woot     8101        hook_update_n   Good update.
  woot     8102        hook_update_n   Failing update.
  woot     8103        hook_update_n   Failing update 2.
  woot     8104        hook_update_n   Another good update.
  woot     failing     post-update     Failing post-update.
 -------- ----------- --------------- -----------------------

 // Do you wish to run the specified pending updates?: yes.
LOG
                ,
                // The expected output being logged during the update.
                <<<LOG
 [notice] Update started: woot_update_8101
 [notice] This is the update message from woot_update_8101
 [ok] Update completed: woot_update_8101
 [notice] Update started: woot_update_8102
 [error] This is the exception message thrown in woot_update_8102
 [error] Update failed: woot_update_8102
 [error] Update aborted by: woot_update_8102
 [error] Finished performing updates.
LOG
                ,
            ],
            [
                // The last successfully completed update. This means that the
                // updates starting with woot_update_8103() will be performed in
                // the test.
                8102,
                // The expected status report that will be output before the
                // test is initiated.
                <<<LOG
 -------- ----------- --------------- -----------------------
  Module   Update ID   Type            Description
 -------- ----------- --------------- -----------------------
  woot     8103        hook_update_n   Failing update 2.
  woot     8104        hook_update_n   Another good update.
  woot     failing     post-update     Failing post-update.
 -------- ----------- --------------- -----------------------

 // Do you wish to run the specified pending updates?: yes.
LOG
                ,
                // The expected output being logged during the update.
                <<<LOG
 [notice] Update started: woot_update_8103
 [error] Call to undefined function non_existing_function()
 [error] Update failed: woot_update_8103
 [error] Update aborted by: woot_update_8103
 [error] Finished performing updates.
LOG
                ,
            ],
        ];
    }

    /**
     * Tests that a failed post-update is handled correctly.
     */
    public function testFailedPostUpdate()
    {
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

        $expected_output = <<<LOG
 -------- ----------- --------------- -------------------------
  Module   Update ID    Type            Description
 -------- ----------- --------------- -------------------------
  woot     8104         hook_update_n   Another good update.
  woot     a            post-update     Successful post-update.
  woot     failing      post-update     Failing post-update.
 -------- ----------- --------------- -------------------------

 // Do you wish to run the specified pending updates?: yes.
LOG;
        $this->assertOutputEquals(preg_replace('#  *#', ' ', $this->simplifyOutput($expected_output)));

        $expected_error_output = <<<LOG
 [notice] Update started: woot_update_8104
 [notice] This is the update message from woot_update_8104
 [ok] Update completed: woot_update_8104
 [notice] Update started: woot_post_update_a
 [notice] This is the update message from woot_post_update_a
 [ok] Update completed: woot_post_update_a
 [notice] Update started: woot_post_update_failing
 [error]  This is the exception message thrown in woot_post_update_failing
 [error]  Update failed: woot_post_update_failing
 [error]  Update aborted by: woot_post_update_failing
 [error] Finished performing updates.
LOG;

        $this->assertErrorOutputEquals(preg_replace('#  *#', ' ', $this->simplifyOutput($expected_error_output)));
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

        $this->markTestSkipped('Requires a patched Drupal. See https://github.com/drush-ops/drush/pull/3735.');

        $root = $this->webroot();
        $this->setUpDrupal(1, true);
        $options = [
            'include' => __DIR__,
        ];
        $this->setupModulesForTests(['woot'], Path::join(__DIR__, 'resources/modules/d8'));
        $this->drush('pm-enable', ['woot'], $options);

        // Force re-run of the post-update woot_post_update_install_devel().
        $this->forcePostUpdate('woot_post_update_install_devel', $options);

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
    arguments: ['@devel.dumper']
YAML_FRAGMENT;
        file_put_contents($filename, $serviceDefinition, FILE_APPEND);

        $filename = Path::join($root, 'modules/unish/woot/woot.info.yml');
        $moduleDependency = <<<YAML_FRAGMENT
dependencies:
  - devel
YAML_FRAGMENT;
        file_put_contents($filename, $moduleDependency, FILE_APPEND);

        // Run updates.
        $this->drush('updatedb');

        // Assert that the updates were run correctly.
        $this->drush('updatedb:status', [], ['format' => 'json']);
        $out = $this->getOutputFromJSON();
        $this->assertNull($out);
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
        $this->drush('updatedb', [], $options, null, null, self::EXIT_SUCCESS);

        $expected_output = <<<LOG
 -------- ----------- --------------- -------------------------
  Module   Update ID    Type            Description
 -------- ----------- --------------- -------------------------
  woot     8104         hook_update_n   Another good update.
  woot     a            post-update     Successful post-update.
  woot     render       post-update     Renders some content.
 -------- ----------- --------------- -------------------------

 // Do you wish to run the specified pending updates?: yes.
LOG;
        $this->assertOutputEquals(preg_replace('#  *#', ' ', $this->simplifyOutput($expected_output)));

        $expected_error_output = <<<LOG
 [notice] Update started: woot_update_8104
 [notice] This is the update message from woot_update_8104
 [ok] Update completed: woot_update_8104
 [notice] Update started: woot_post_update_a
 [notice] This is the update message from woot_post_update_a
 [ok] Update completed: woot_post_update_a
 [notice] Update started: woot_post_update_render
 [ok] Update completed: woot_post_update_render
 [success] Finished performing updates.
LOG;

        $this->assertErrorOutputEquals(preg_replace('#  *#', ' ', $this->simplifyOutput($expected_error_output)));
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
