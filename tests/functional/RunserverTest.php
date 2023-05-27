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
        $child_process = $this->drushBackground(RunserverCommands::RUNSERVER, ['8888']);
        $pid = $child_process->getPid();
        // Kill the web server when phpunit ends.
        register_shutdown_function(function () use ($pid) {
            $this->log(sprintf('%s - Killing process with ID %d', date('r'), $pid), 'info');
            exec('kill ' . $pid);
        });
        $file = file_get_contents('http://127.0.0.1:8888');
        $this->assertStringContainsString('Enter your Drush Site-Install username', $file);
    }
}
