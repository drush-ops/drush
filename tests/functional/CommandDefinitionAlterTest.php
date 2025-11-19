<?php

declare(strict_types=1);

namespace Unish;

use PHPUnit\Framework\Attributes\Group;
use Drush\Commands\pm\PmCommands;

#[Group('commands')]
final class CommandDefinitionAlterTest extends CommandUnishTestCase
{
    use TestModuleHelperTrait;

    /**
     * Tests Console Definition Event Listener.
     */
    public function testCommandDefinitionAlter(): void
    {
        $this->setUpDrupal(1, true);
        $this->drush(PmCommands::INSTALL, ['woot']);
        $this->drush('woot:altered', [], ['help' => true, 'debug' => true]);
        $this->assertStringNotContainsString('woot-initial-alias', $this->getOutput());
        $this->assertStringContainsString('woot-new-alias', $this->getOutput());

        // Check the debug messages.
        $this->assertStringContainsString("[debug] Module 'woot' changed the alias of 'woot:altered' command into 'woot-new-alias' in Drupal\woot\Drush\Listeners\WootDefinitionListener::__invoke().", $this->getErrorOutput());

        // Run the command with the altered alias.
        $this->drush('woot-new-alias');
    }
}
