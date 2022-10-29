<?php

namespace Unish;

use Symfony\Component\Filesystem\Path;

/**
 * Tests the Drush override of DrupalKernel.
 *
 * @group base
 *
 * @see https://github.com/drush-ops/drush/issues/3123
 */
class ContainerTest extends CommandUnishTestCase
{
    use TestModuleHelperTrait;

    /**
     * Tests that the existing container is available while Drush rebuilds it.
     */
    public function testContainer()
    {
        $this->setUpDrupal(1, true);

        // Copy the 'woot' module over to the Drupal site we just set up.
        $this->setupModulesForTests(['woot'], Path::join(__DIR__, '/../fixtures/modules'));

        // Enable our module.
        $this->drush('pm-install', ['woot']);

        // Set up for a config import with just one small piece.
        $this->drush('config-export');
        $this->drush('config-set', ['system.site', 'name', 'config_test']);

        // Trigger the container rebuild we need.
        $this->drush('cr');

        // If the event was registered successfully, then upon a config import, we
        // should get the error message.
        $this->drush('config-import', [], [], null, null, CommandUnishTestCase::EXIT_ERROR);
        $this->assertStringContainsString("woot config error", $this->getErrorOutput(), 'Event was successfully registered.');
    }
}
