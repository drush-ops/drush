<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\DeployCommands;
use Drush\Commands\config\ConfigExportCommands;

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
        $this->drush(ConfigExportCommands::EXPORT);

        $this->drush(DeployCommands::DEPLOY);
        $expecteds = ["Database updates start.", 'Config import start.', 'Deploy hook start.', 'Cache rebuild start.'];
        foreach ($expecteds as $expected) {
            $this->assertStringContainsString($expected, $this->getErrorOutput());
        }
    }
}
