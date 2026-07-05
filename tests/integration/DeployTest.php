<?php

declare(strict_types=1);

namespace Unish;

use PHPUnit\Framework\Attributes\Group;
use Drush\Commands\config\ConfigExportCommand;
use Drush\Commands\core\DeployCommands;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[Group('commands')]
#[RunTestsInSeparateProcesses]
class DeployTest extends UnishIntegrationTestCase
{
    /**
     * A simple test since all the sub-commands are tested elsewhere.
     */
    public function testDeploy(): void
    {
        // Prep a config directory that will be imported later.
        $this->drush(ConfigExportCommand::NAME, [], ['yes' => null]);

        $this->drush(DeployCommands::DEPLOY);
        $expecteds = ["Database updates start.", 'Config import start.', 'Deploy hook start.', 'Cache rebuild start.'];
        foreach ($expecteds as $expected) {
            $this->assertStringContainsString($expected, $this->getErrorOutput());
        }
    }
}
