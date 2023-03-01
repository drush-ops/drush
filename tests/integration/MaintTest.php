<?php

namespace Unish;

use Drush\Drupal\Commands\core\MaintCommands;

/**
 * Tests Maintenance commands
 *
 * @group commands
 */
class MaintenanceTest extends UnishIntegrationTestCase
{
    public function testMaint()
    {
        $this->drush(MaintCommands::SET, [1]);
        $this->drush(MaintCommands::GET);
        $this->assertOutputEquals('1');
        $this->drush(MaintCommands::STATUS, [], [], self::EXIT_ERROR_WITH_CLARITY);
        $this->drush(MaintCommands::SET, [0]);
        $this->drush(MaintCommands::GET);
        $this->assertOutputEquals('0');
        $this->drush(MaintCommands::STATUS, [], [], self::EXIT_SUCCESS);
    }
}
