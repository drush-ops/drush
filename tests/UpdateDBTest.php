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
        $this->drush('sql:query', ["SELECT value FROM key_value WHERE collection = 'post_update' AND name = 'existing_updates'"], $options);
        $functions = unserialize($this->getOutput());
        unset($functions[array_search('woot_post_update_failing', $functions)]);
        $functions = serialize($functions);
        $this->drush('sql:query', ["UPDATE key_value SET value = '$functions' WHERE collection = 'post_update' AND name = 'existing_updates'"], $options);

      // Run updates.
        $this->drush('updatedb', [], $options, null, null, self::EXIT_ERROR);
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
}
