<?php

declare(strict_types=1);

namespace Unish;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('base')]
class DrushStubTest extends TestCase
{
    /**
     * The drush stub must print its hint and exit, not run drush or dr.
     *
     * Fake drush and dr commands come first on PATH, so a command substitution
     * in the stub prints a marker instead of recursing into the real stub.
     */
    public function testStubPrintsHintWithoutRunningCommands(): void
    {
        $bin = sys_get_temp_dir() . '/drush-stub-test-' . bin2hex(random_bytes(4));
        mkdir($bin);
        foreach (['drush', 'dr'] as $name) {
            file_put_contents("$bin/$name", "#!/bin/sh\necho SUBSTITUTED\n");
            chmod("$bin/$name", 0755);
        }

        $stub = dirname(__DIR__, 2) . '/drush';
        exec('PATH=' . escapeshellarg($bin) . ' /bin/sh ' . escapeshellarg($stub) . ' 2>&1', $output, $exitCode);

        foreach (['drush', 'dr'] as $name) {
            unlink("$bin/$name");
        }
        rmdir($bin);

        $this->assertSame(1, $exitCode);
        $this->assertSame(['Instead of calling `drush`, call `dr`'], $output);
    }
}
