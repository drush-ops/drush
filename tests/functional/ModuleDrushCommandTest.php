<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\pm\PmCommands;
use Symfony\Component\Filesystem\Path;

/**
 * @group commands
 *
 */
class ModuleDrushCommandTest extends CommandUnishTestCase
{
    use TestModuleHelperTrait;

    /**
     * Tests command info alter.
     */
    public function testDrushCommandsInModule()
    {
        $this->setUpDrupal(1, true);
        // Commands in the woot module should not be available yet,
        // because the woot module has not been installed.
        $this->drush('woot', [], [], null, null, self::EXIT_ERROR);
        $this->drush('woot-factory', [], [], null, null, self::EXIT_ERROR);

        // Install the woot module; this should make the commands available
        $this->drush(PmCommands::INSTALL, ['woot']);

        $this->drush('woot', [], []);
        $this->assertStringContainsString('Woot!', $this->getOutput());

        $this->drush('woot-factory', [], []);
        $this->assertStringContainsString('Woot 55', $this->getOutput());
    }

    /**
     * Tests retrieval of log messages.
     */
    public function testLogMessageRetrieval()
    {
        $this->setUpDrupal(1, true);
        // Install the woot module.
        $this->drush(PmCommands::INSTALL, ['woot']);

        $this->drush('woot-messages', [], []);

        var_dump($this->getErrorOutput());
        $this->assertStringContainsString('Message 1', $this->getErrorOutput());
        $this->assertStringContainsString('Message 2', $this->getErrorOutput());
        $this->assertStringContainsString('Message 3', $this->getErrorOutput());
        $this->assertStringContainsString('Message 4', $this->getErrorOutput());
    }
}
