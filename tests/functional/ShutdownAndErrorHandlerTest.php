<?php

namespace Unish;

/**
 * Tests the Drush error handler.
 *
 * @group base
 */
class ShutdownAndErrorHandlerTest extends CommandUnishTestCase
{
    /**
     * Check to see if the shutdown function is working.
     */
    public function testShutdownFunction()
    {
        // Run some garbage php with a syntax error.
        $this->drush('ev', ['exit(0);']);

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
