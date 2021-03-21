<?php

namespace Unish;

/**
 * @group commands
 */
class CustomLibraryCommandsTest extends UnishIntegrationTestCase
{
    /**
     * Tests that commands provided by custom libraries are registered.
     */
    public function testCustomLibraryCommands(): void
    {
        $this->drush('list');
        $this->assertStringContainsString('custom_cmd', $this->getOutput());
        $this->assertStringContainsString('Auto-discoverable custom command', $this->getOutput());
        $this->drush('custom_cmd');
        $this->assertStringContainsString('Hello world!', $this->getOutput());
    }
}
