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
        $this->setUpDrupal(1, true);
        $this->drush('site:path');
        $this->assertStringContainsString('The site path is: sites/dev', $this->getOutput());
    }
}
