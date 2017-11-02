<?php

namespace Unish;

use Webmozart\PathUtil\Path;

/**
 * Tests the Drush override of DrupalKernel.
 *
 * @group base
 */
class containerTest extends CommandUnishTestCase {

    /**
     * Tests that the existing container is available while Drush rebuilds it.
     */
    public function testContainer() {
        $sites = $this->setUpDrupal(1, TRUE);
        $root = $this->webroot();
        $options = array(
            'root' => $root,
            'uri' => key($sites),
            'yes' => NULL,
        );

      // Copy the 'woot' module over to the Drupal site we just set up.
      $this->setupModulesForTests($root);

      // Enable our module.
      $this->drush('pm-enable', ['woot'], $options);

      // Set up for a config import with just one small piece.
      $this->drush('config-export', array(), $options);
      $this->drush('config-set', array('system.site', 'name', 'config_test'), $options);

      // Trigger the container rebuild we need.
      $this->drush('cr', [], $options);
      $this->drush('cron', [], $options);

      // If the event was registered successfully, then upon a config import, we
      // should get the error message.
      $this->drush('config-import', [], $options, NULL, NULL, CommandUnishTestCase::EXIT_ERROR);
      $this->assertContains("woot config error", $this->getErrorOutput(), 'Event was successfully registered.');
    }

    /**
     * Sets up the woot module for the test.
     *
     * @param string $root
     *   The web root.
     */
    public function setupModulesForTests($root) {
        $wootModule = Path::join(__DIR__, '/resources/modules/d8/woot');
        // We install into Unish so that we aren't cleaned up. That causes
        // container to go invalid after tearDownAfterClass().
        $targetDir = Path::join($root, 'modules/unish/woot');
        $this->mkdir($targetDir);
        $this->recursive_copy($wootModule, $targetDir);
    }

}
