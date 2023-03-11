<?php

declare(strict_types=1);

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
        $this->drush('drush-extensions-hello', [], [
            'config' => getenv('FIXTURES_DIR') . '/drush-extensions/drush.yml',
        ]);
        $this->assertStringContainsString('Hello world!', $this->getOutput());
    }
}
