<?php

declare(strict_types=1);

namespace Unish;

/**
 * @group commands
 */
class StaticCreateFactoryCommandsTest extends CommandUnishTestCase
{
    /**
     * Tests that commands provided by custom libraries with static `create` methods.
     */
    public function testStaticCreateFactoryCommands(): void
    {
        $this->drush('site:path');
        $this->assertStringContainsString('The site path is: sites/default', $this->getOutput());
    }
}
