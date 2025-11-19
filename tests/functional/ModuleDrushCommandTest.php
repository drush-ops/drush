<?php

declare(strict_types=1);

namespace Unish;

use PHPUnit\Framework\Attributes\Group;
use Drush\Commands\pm\PmCommands;

#[Group('commands')]
final class ModuleDrushCommandTest extends CommandUnishTestCase
{
    use TestModuleHelperTrait;

    /**
     * Tests command info alter.
     */
    public function testDrushCommandsInModule(): void
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

        $this->drush('woot:root');
        $this->assertStringContainsString('/sut', $this->getOutput());
    }
}
