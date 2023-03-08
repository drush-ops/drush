<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\PhpCommands;
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
     * if request exits abruptly (e.g. page redirect)
     */
    public function testShutdownFunctionAbruptExit()
    {
        // Run some garbage php with a syntax error.
        $this->drush(PhpCommands::EVAL, ['exit(0);'], [], null, null, DrushCommands::EXIT_FAILURE);

        $this->assertStringContainsString("Drush command terminated abnormally.", $this->getErrorOutput(), 'Error handler did not log a message.');
    }

    /**
     * Check to see if the shutdown function is working
     * and the exit code is passed through when script exits
     * with a specific exit code.
     */
    public function testShutdownFunctionExitCodePassedThrough()
    {
        // script command passes along an exit code nicely for our purposes.
        $this->drush(PhpCommands::SCRIPT, ['exit.php'], ['script-path' => __DIR__ . '/resources'], null, null, 123);
        // Placate phpunit. If above succeeds we are done here.
        $this->addToAssertionCount(1);
    }

    /**
     * Check to see if the shutdown function is working
     * if request exits due to a PHP problem such as a syntax error
     */
    public function testShutdownFunctionPHPError()
    {
        // Run some garbage php with a syntax error.
        $this->drush(PhpCommands::EVAL, ['\Drush\Drush::setContainer("string is the wrong type to pass here");'], [], null, null, PHP_MAJOR_VERSION == 5 ? 255 : DrushCommands::EXIT_FAILURE);

        $this->assertStringContainsString("Drush command terminated abnormally.", $this->getErrorOutput(), 'Error handler did not log a message.');
    }

    /**
     * Check to see if the error handler is using correct error level (info).
     */
    public function testErrorHandler()
    {
        // Access a missing array element
        $this->drush(PhpCommands::EVAL, ['$a = []; print $a["b"];']);

        if (empty($this->logLevel()) && PHP_MAJOR_VERSION <= 7) {
            $this->assertEquals('', $this->getErrorOutput(), 'Error handler did not suppress deprecated message.');
        } else {
            $msg = PHP_MAJOR_VERSION >= 8 ? 'Undefined array key "b" PhpCommands.php' : 'Undefined index: b PhpCommands.php';
            $this->assertStringContainsString($msg, $this->getErrorOutput());
        }
    }
}
