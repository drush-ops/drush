<?php

namespace Unish;

use Webmozart\PathUtil\Path;

/**
 * Tests the Drush override of DrupalKernel.
 *
 * @group base
 *
 * @see https://github.com/drush-ops/drush/issues/3123
 */
class ContainerTest extends CommandUnishTestCase
{

    /**
     * Tests that the existing container is available while Drush rebuilds it.
     */
    public function testContainer()
    {
        $this->setUpDrupal(1, true);
        $root = $this->webroot();

      // Copy the 'woot' module over to the Drupal site we just set up.
        $this->setupModulesForTests($root);

      // Enable our module.
        $this->drush('pm-enable', ['woot']);

      // Set up for a config import with just one small piece.
        $this->drush('config-export');
        $this->drush('config-set', ['system.site', 'name', 'config_test']);

      // Trigger the container rebuild we need.
        $this->drush('cr');

      // If the event was registered successfully, then upon a config import, we
      // should get the error message.
        $this->drush('config-import', [], [], null, null, CommandUnishTestCase::EXIT_ERROR);
        $this->assertContains("woot config error", $this->getErrorOutput(), 'Event was successfully registered.');
    }

    /**
     * Sets up the woot module for the test.
     *
     * @param string $root
     *   The web root.
     */
    public function setupModulesForTests($root)
    {
        $wootModule = Path::join(__DIR__, '/resources/modules/d8/woot');
        // We install into Unish so that we aren't cleaned up. That causes
        // container to go invalid after tearDownAfterClass().
        $targetDir = Path::join($root, 'modules/unish/woot');
        $this->mkdir($targetDir);
        $this->recursiveCopy($wootModule, $targetDir);
    }
}
