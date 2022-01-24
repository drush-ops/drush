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
        $this->assertStringContainsString('Command drush-extensions-hello was not found.', $this->getErrorOutput());
        $this->drush('drush-extensions-hello', [], [
            'help' => null,
            'config' => '/Users/kporras07/development/pantheon/drush/tests/fixtures/drush-extensions/drush.yml',
        ]);
        $this->assertStringContainsString('Command to load from this file using drush config.', $this->getOutput());
        /*$this->drush('drush-extensions-hello', [], [
            'config' => '/Users/kporras07/development/pantheon/drush/tests/fixtures/drush-extensions/drush.yml',
        ]);
        $this->assertStringContainsString('Hello world!', $this->getOutput());*/
    }
}
