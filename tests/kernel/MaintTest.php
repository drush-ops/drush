<?php

declare(strict_types=1);

namespace Unish;

use Drupal\KernelTests\KernelTestBase;
use Drush\Doctor\Commands\maint\MaintDisableCommand;
use Drush\Doctor\Commands\maint\MaintEnableCommand;
use Drush\Doctor\Commands\maint\MaintStatusCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the dr-only copies of core's maint:enable, maint:disable and
 * maint:status.
 *
 * Based on \Drupal\Tests\system\Kernel\Command\MaintCommandTest from core
 * merge request 17320.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(MaintEnableCommand::class)]
#[CoversClass(MaintDisableCommand::class)]
#[CoversClass(MaintStatusCommand::class)]
class MaintTest extends KernelTestBase
{
    protected static $modules = ['system'];

    /**
     * Tests enabling, disabling and checking maintenance mode.
     */
    public function testMaint(): void
    {
        $state = $this->container->get('state');
        $enable = new CommandTester(new MaintEnableCommand($state));
        $disable = new CommandTester(new MaintDisableCommand($state));
        $status = new CommandTester(new MaintStatusCommand($state));

        $this->assertSame(Command::SUCCESS, $enable->execute([]));
        $code = $status->execute([]);
        $this->assertSame(MaintStatusCommand::EXIT_ERROR_WITH_CLARITY, $code);
        $this->assertSame('1', trim($status->getDisplay()));

        $this->assertSame(Command::SUCCESS, $disable->execute([]));
        $code = $status->execute([]);
        $this->assertSame(Command::SUCCESS, $code);
        $this->assertSame('0', trim($status->getDisplay()));
    }
}
