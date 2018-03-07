<?php

namespace Unish;

use Webmozart\PathUtil\Path;

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
        $this->setupModulesForTests(['woot'], Path::join(__DIR__, 'resources/modules/d8'));
        $this->drush('pm-enable', ['woot']);
        $this->drush('woot:altered', [], ['help' => true]);
        $this->assertNotContains('woot-initial-alias', $this->getOutput());
        $this->assertContains('woot-new-alias', $this->getOutput());

        // Try to run the command with the initial alias.
        $this->drush('woot-initial-alias', [], [], null, null, self::EXIT_ERROR);
        // Run the command with the altered alias.
        $this->drush('woot-new-alias');
    }
}
