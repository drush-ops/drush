<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\MaintCommands;

/**
 * Tests Maintenance commands
 *
 * @group commands
 */
class MaintTest extends UnishIntegrationTestCase
{
    public function testMaint()
    {
        $this->drush(MaintCommands::SET, ['1']);
        $this->drush(MaintCommands::GET);
        $this->assertOutputEquals('1');
        $this->drush(MaintCommands::STATUS, [], [], self::EXIT_ERROR_WITH_CLARITY);
        $this->drush(MaintCommands::SET, ['0']);
        $this->drush(MaintCommands::GET);
        $this->assertOutputEquals('0');
        $this->drush(MaintCommands::STATUS, [], [], self::EXIT_SUCCESS);
    }
}
