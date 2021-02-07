<?php

namespace Unish;

use Drush\Commands\DrushCommands;

/**
 * Tests the Drush error handler.
 *
 * @group base
 */
class ShutdownAndErrorHandlerTest extends CommandUnishTestCase
{

    /**
     * Check to see if the shutdown function is working
     * and the exit code is passed through when script exits
     * with a specific exit code.
     */
    public function testShutdownFunctionExitCodePassedThrough()
    {
        // script command passes along an exit code nicely for our purposes.
        $this->drush('php:script', ['exit.php'], ['script-path' => __DIR__ . '/resources'], null, null, 123);
        // Placate phpunit. If above succeeds we are done here.
        $this->addToAssertionCount(1);
    }

    /**
     * Check to see if the error handler is using correct error level (info).
     */
    public function testErrorHandler()
    {
        // Access a missing array element
        $this->drush('ev', ['$a = []; print $a["b"];']);

        if (empty($this->logLevel()) && PHP_MAJOR_VERSION <= 7) {
            $this->assertEquals('', $this->getErrorOutput(), 'Error handler did not suppress deprecated message.');
        } else {
            $msg = PHP_MAJOR_VERSION >= 8 ? 'Undefined array key "b" PhpCommands.php' : 'Undefined index: b PhpCommands.php';
            $this->assertStringContainsString($msg, $this->getErrorOutput());
        }
    }
}
