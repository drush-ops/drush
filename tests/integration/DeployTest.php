<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\config\ConfigExportCommand;
use Drush\Commands\core\DeployCommands;

/**
 * @group commands
 */
class DeployTest extends UnishIntegrationTestCase
{
    /**
     * A simple test since all the sub-commands are tested elsewhere.
     */
    public function testDeploy()
    {
        // Prep a config directory that will be imported later.
        $this->drush(ConfigExportCommand::NAME, [], ['yes' => NULL]);

        $this->drush(DeployCommands::DEPLOY);
        $expecteds = ["Database updates start.", 'Config import start.', 'Deploy hook start.', 'Cache rebuild start.'];
        foreach ($expecteds as $expected) {
            $this->assertStringContainsString($expected, $this->getErrorOutput());
        }
    }
}
