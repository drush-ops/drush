<?php

namespace Unish;

/**
 * @group commands
 */
class CommandsFromConfigurationTest extends UnishIntegrationTestCase
{
    /**
     * Tests that commands provided by custom libraries are registered.
     */
    public function testCommandsFromConfiguration(): void
    {
        $this->drush('drush-extensions-hello', [], ['help' => null], self::EXIT_ERROR);
        $this->assertStringContainsString('Command "drush-extensions-hello" is not defined.', $this->getErrorOutput());
        $this->drush('drush-extensions-hello', [], [
            'help' => null,
            'config' => getenv('FIXTURES_DIR') . '/drush-extensions/drush.yml',
        ]);
        $this->assertStringContainsString('Command to load from this file using drush config.', $this->getOutput());
        $this->assertStringContainsString('[warning] Discovering commands by configuration is deprecated in Drush 11.0.9 and is scheduled for removal in a future major version. The following command classes should be converted to PSR4 discovery (see docs/commands.md): Drush\Commands\drush_extensions\DrushExtensionsCommands', $this->getErrorOutputRaw());
        $this->drush('drush-extensions-hello', [], [
            'config' => getenv('FIXTURES_DIR') . '/drush-extensions/drush.yml',
        ]);
        $this->assertStringContainsString('Hello world!', $this->getOutput());
        $this->assertStringContainsString('[warning] Discovering commands by configuration is deprecated in Drush 11.0.9 and is scheduled for removal in a future major version. The following command classes should be converted to PSR4 discovery (see docs/commands.md): Drush\Commands\drush_extensions\DrushExtensionsCommands', $this->getErrorOutputRaw());
    }
}
