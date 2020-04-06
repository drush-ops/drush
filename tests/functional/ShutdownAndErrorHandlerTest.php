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
     * if request exits abruptly (e.g. page redirect)
     */
    public function testShutdownFunctionAbruptExit()
    {
        // Run some garbage php with a syntax error.
        $this->drush('ev', ['exit(0);'], [], null, null, DrushCommands::EXIT_FAILURE);

        $this->assertContains("Drush command terminated abnormally.", $this->getErrorOutput(), 'Error handler did not log a message.');
    }

    /**
     * Check to see if the shutdown function is working
     * and the exit code is passed through when script exist
     * with a specific exit code.
     */
    public function testShutdownFunctionExitCodePassedThrough()
    {
        // Run some garbage php with a syntax error.
        $this->drush('ev', ['exit(123);'], [], null, null, 123);

        $this->assertContains("Drush command terminated abnormally.", $this->getErrorOutput(), 'Error handler did not log a message.');
    }

    /**
     * Check to see if the shutdown function is working
     * if request exits due to a PHP problem such as a syntax error
     */
    public function testShutdownFunctionPHPError()
    {
        // Run some garbage php with a syntax error.
        $this->drush('ev', ['\Drush\Drush::setContainer("string is the wrong type to pass here");'], [], null, null, PHP_MAJOR_VERSION == 5 ? 255 : DrushCommands::EXIT_FAILURE);

        $this->assertContains("Drush command terminated abnormally.", $this->getErrorOutput(), 'Error handler did not log a message.');
    }

    /**
     * Check to see if the error handler is using correct error level (info).
     */
    public function testErrorHandler()
    {
        // Access a missing array element
        $this->drush('ev', ['$a = []; print $a["b"];']);

        if (empty($this->logLevel())) {
            $this->assertEquals('', $this->getErrorOutput(), 'Error handler did not suppress deprecated message.');
        } else {
            $this->assertContains('Undefined index: b PhpCommands.php', $this->getErrorOutput());
        }
    }
}
