<?php

namespace Unish;

use Webmozart\PathUtil\Path;

/**
 *  @group slow
 *  @group commands
 */
class UpdateDBTest extends CommandUnishTestCase
{

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
        $this->pathPostUpdate = $this->getSut() . '/web/modules/unish/devel/devel.post_update.php';
        copy(__DIR__ . '/resources/devel.post_update.php', $this->pathPostUpdate);
        $this->drush('updatedb:status', [], ['format' => 'json']);
        $out = $this->getOutputFromJSON('devel-post-null_op');
        $this->assertEquals('This is a test of the emergency broadcast system.', trim($out->description));
    }

    /**
     * Tests that updatedb command returns properly a failure.
     */
    public function testFailedUpdate()
    {
        $sites = $this->setUpDrupal(1, true);
        $options = [
        'yes' => null,
        'root' => $root = $this->webroot(),
        'uri' => key($sites),
        ];
        $this->setupModulesForTests($root);
        $this->drush('pm-enable', ['woot'], $options);

      // Force a pending update.
        $this->drush('php-script', ['updatedb_script'], ['script-path' => __DIR__ . '/resources']);

      // Force re-run of woot_update_8101().
        $this->drush('php:eval', array('drupal_set_installed_schema_version("woot", 8100)'), $options);

      // Force re-run of the post-update woot_post_update_failing().
        $this->forcePostUpdate('woot_post_update_failing', $options);

      // Run updates.
        $this->drush('updatedb', [], $options, null, null, self::EXIT_ERROR);
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
        $sites = $this->setUpDrupal(1, true);
        $options = [
            'yes' => null,
            'root' => $root,
            'uri' => key($sites),
            'include' => __DIR__,
        ];
        $this->setupModulesForTests($root);
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

    protected function setupModulesForTests($root)
    {
        $wootModule = Path::join(__DIR__, '/resources/modules/d8/woot');
      // We install into Unish so that we aren't cleaned up. That causes container to go invalid after tearDownAfterClass().
        $targetDir = Path::join($root, 'modules/unish/woot');
        $this->mkdir($targetDir);
        $this->recursiveCopy($wootModule, $targetDir);
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
