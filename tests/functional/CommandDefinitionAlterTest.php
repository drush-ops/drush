<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\pm\PmCommands;

/**
 * @group commands
 *
 */
class CommandDefinitionAlterTest extends CommandUnishTestCase
{
    use TestModuleHelperTrait;

    /**
     * Tests Console Definition Event Listener.
     */
    public function testCommandDefinitionAlter()
    {
        $this->setUpDrupal(1, true);
        $this->drush(PmCommands::INSTALL, ['woot']);
        $this->drush('woot:altered', [], ['help' => true, 'debug' => true]);
        $this->assertStringNotContainsString('woot-initial-alias', $this->getOutput());
        $this->assertStringContainsString('woot-new-alias', $this->getOutput());

        // Check the debug messages.
        $this->assertStringContainsString("[debug] Module 'woot' changed the alias of 'woot:altered' command into 'woot-new-alias' in Drupal\woot\Drush\Listeners\WootDefinitionListener::__invoke().", $this->getErrorOutput());
        // Listeners dispatch mostly outside of Drush so no longer able to asset this message.
        // $this->assertStringContainsString('[debug] Commands are potentially altered in Drupal\woot\Drush\Listeners.', $this->getErrorOutput());

        // Try to run the command with the initial alias.
        $this->drush('woot-initial-alias', [], [], null, null, self::EXIT_ERROR);
        // Run the command with the altered alias.
        $this->drush('woot-new-alias');
    }
}
