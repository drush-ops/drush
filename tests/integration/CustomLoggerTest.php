<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\CoreCommands;

class CustomLoggerTest extends UnishIntegrationTestCase
{
    public function testCustomLogger()
    {
        // Uses standard Drush logger.
        $this->drush(CoreCommands::VERSION, [], ['debug' => true]);
        $this->assertStringContainsString('sec', $this->getErrorOutputRaw());

        // sut:simple has been hooked so that a custom logger is use. It doesn't show timing information during --debug.
        $this->drush('sut:simple', [], ['debug' => true, 'simulate' => true]);
        $this->assertStringNotContainsString('sec', $this->getOutput());
    }
}
