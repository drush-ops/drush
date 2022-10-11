<?php

namespace Unish;

use Symfony\Component\Filesystem\Path;

/**
 * @group commands
 *
 */
class CommandInfoAlterTest extends CommandUnishTestCase
{
    use TestModuleHelperTrait;

    /**
     * Tests command info alter.
     */
    public function testCommandInfoAlter()
    {
        $this->setUpDrupal(1, true);
        $this->setupModulesForTests(['woot'], Path::join(__DIR__, '/../fixtures/modules'));
        $this->drush('pm-install', ['woot']);
        $this->drush('woot:altered', [], ['help' => true, 'debug' => true]);
        $this->assertStringNotContainsString('woot-initial-alias', $this->getOutput());
        $this->assertStringContainsString('woot-new-alias', $this->getOutput());

        // Check the debug messages.
        $this->assertStringContainsString('[debug] Commands are potentially altered in Drupal\woot\WootCommandInfoAlterer.', $this->getErrorOutput());
        $this->assertStringContainsString("[debug] Module 'woot' changed the alias of 'woot:altered' command into 'woot-new-alias' in Drupal\woot\WootCommandInfoAlterer::alterCommandInfo().", $this->getErrorOutput());

        // Try to run the command with the initial alias.
        $this->drush('woot-initial-alias', [], [], null, null, self::EXIT_ERROR);
        // Run the command with the altered alias.
        $this->drush('woot-new-alias');
    }
}
