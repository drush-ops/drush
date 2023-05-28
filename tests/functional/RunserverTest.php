<?php

declare(strict_types=1);

namespace functional;

use Drush\Commands\core\RunserverCommands;
use Unish\CommandUnishTestCase;

/**
 * Tests Runserver commands
 *
 * @group commands
 */
class RunserverTest extends CommandUnishTestCase
{
    public function testRunserver()
    {
        $this->markTestSkipped('@todo this test has not yet passed.');

        $child_process = $this->drushBackground(RunserverCommands::RUNSERVER, ['8888']);
        $pid = $child_process->getPid();
        // Kill the web server when phpunit ends.
        register_shutdown_function(function () use ($pid) {
            $this->log(sprintf('%s - Killing process with ID %d', date('r'), $pid), 'info');
            exec('kill ' . $pid);
        });
        sleep(3);
        $file = file_get_contents('http://localhost:8888');
        $this->assertStringContainsString('Choose language', $file);
    }
}
