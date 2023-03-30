<?php

declare(strict_types=1);

namespace Unish;

use Drush\Drupal\Commands\generate\GenerateCommands;

/**
 * @group commands
 */
class CustomLibraryCommandsAndGeneratorsTest extends UnishIntegrationTestCase
{
    /**
     * Tests that commands provided by custom libraries are registered.
     */
    public function testCustomLibraryCommandsAndGenerators(): void
    {
        $this->drush('custom_cmd', [], ['help' => null]);
        $this->assertStringContainsString('Auto-discoverable custom command. Used for Drush testing.', $this->getOutput());
        $this->drush('custom_cmd');
        $this->assertStringContainsString('Hello world!', $this->getOutput());
        $this->drush(GenerateCommands::GENERATE, ['list']);
        $this->assertStringContainsString('drush:testing-generator', $this->getOutput());
        $this->assertStringContainsString('An internal generator', $this->getOutput());
    }
}
