<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Core\DrupalKernelInterface;
use Drupal\KernelTests\KernelTestBase;
use Drush\Doctor\Commands\deploy\DeployCommand;
use Drush\Doctor\Commands\deploy\DeployHookCommand;
use Drush\Doctor\Commands\deploy\DeployHookStatusCommand;
use Drush\Doctor\Commands\deploy\DeployMarkCompleteCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the dr-only deploy commands.
 *
 * woot ships three deploy hooks: woot_deploy_a(), the multi-pass
 * woot_deploy_batch() and woot_deploy_failing(), which throws unless the
 * woot_deploy_pass state is set.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(DeployCommand::class)]
#[CoversClass(DeployHookCommand::class)]
#[CoversClass(DeployHookStatusCommand::class)]
#[CoversClass(DeployMarkCompleteCommand::class)]
class DeployTest extends KernelTestBase
{
    protected static $modules = ['system', 'user', 'woot'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->installConfig(['system']);
    }

    public function testStatus(): void
    {
        $display = $this->runCommand(new DeployHookStatusCommand($this->kernel()))->getDisplay();
        $this->assertMatchesRegularExpression('/Module\s+\|\s+Hook\s+\|\s+Description/', $display);
        $this->assertMatchesRegularExpression('/woot\s+\|\s+a\s+\|\s+Successful deploy hook\./', $display);

        $json = json_decode($this->runCommand(new DeployHookStatusCommand($this->kernel()), ['--format' => 'json'])->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['module' => 'woot', 'hook' => 'a', 'description' => 'Successful deploy hook.'], $json[0]);
        $this->assertSame(['a', 'batch', 'failing'], array_column($json, 'hook'));
    }

    public function testMarkComplete(): void
    {
        $display = $this->runCommand(new DeployMarkCompleteCommand($this->kernel()))->getDisplay();
        $this->assertStringContainsString('Marked 3 pending deploy hooks as complete.', $display);

        $tester = $this->runCommand(new DeployHookStatusCommand($this->kernel()));
        $this->assertStringContainsString('No pending deploy hooks.', $tester->getDisplay());
        $this->assertStringNotContainsString('woot', $tester->getDisplay());

        $tester = $this->runCommand(new DeployHookStatusCommand($this->kernel()), ['--format' => 'json']);
        $this->assertSame('[]', trim(preg_replace('/^.*No pending deploy hooks.*$/m', '', $tester->getDisplay())));

        $this->assertStringContainsString('No pending deploy hooks.', $this->runCommand(new DeployHookCommand($this->kernel()))->getDisplay());
    }

    public function testHook(): void
    {
        $this->container->get('state')->set('woot_deploy_pass', true);

        $display = $this->runCommand(new DeployHookCommand($this->kernel()))->getDisplay();
        $needles = [
            'Deploy hook started: woot_deploy_a',
            'This is the update message from woot_deploy_a',
            'Deploy hook completed: woot_deploy_a',
            'Iteration 2.',
            'Finished at 3.',
            'Deploy hook completed: woot_deploy_batch',
            'Now woot_deploy_failing is passing',
            'Finished performing deploy hooks.',
        ];
        foreach ($needles as $needle) {
            $this->assertStringContainsString($needle, $display);
        }
        $this->assertSame(1, substr_count($display, 'Deploy hook started: woot_deploy_batch'), $display);
        $this->assertSame([], $this->pending());
    }

    public function testFailingHook(): void
    {
        $tester = $this->runCommand(new DeployHookCommand($this->kernel()), expected: Command::FAILURE);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Deploy hook completed: woot_deploy_batch', $display);
        $this->assertStringContainsString('This is the exception message thrown in woot_deploy_failing', $display);
        $this->assertStringContainsString('Deploy hook failed: woot_deploy_failing', $display);
        $this->assertStringContainsString('Deploy hooks aborted by: woot_deploy_failing', $display);
        $this->assertSame(['woot_deploy_failing'], array_values($this->pending()));
    }

    public function testDeclined(): void
    {
        $tester = new CommandTester(new DeployHookCommand($this->kernel()));
        $tester->setInputs(['no']);
        $this->assertSame(Command::FAILURE, $tester->execute([]), $tester->getDisplay());
        $this->assertCount(3, $this->pending());
    }

    /**
     * updatedb and cache:warm are stand-ins that record their call.
     */
    public function testDeploy(): void
    {
        $this->container->get('state')->set('woot_deploy_pass', true);
        $this->exportConfig();
        $this->container->get('config.storage.sync')->write('system.site', ['slogan' => 'Imported'] + $this->config('system.site')->getRawData());
        $calls = [];
        $application = $this->application($calls);

        $tester = $this->runCommand($application->find(DeployCommand::NAME));
        $display = $tester->getDisplay();
        $this->assertSame(['updatedb', 'cache:warm'], $calls);
        $this->assertStringContainsString('update system.site', $display);
        $this->assertSame('Imported', $this->config('system.site')->get('slogan'));
        $this->assertSame([], $this->pending());
        // Deploy hooks run after the config import and the cache rebuild.
        $this->assertLessThan(strpos($display, 'Deploy hook started: woot_deploy_a'), strpos($display, 'cache rebuild'));
        $this->assertStringContainsString('Deployment complete.', $display);
    }

    public function testDeployStopsOnFailure(): void
    {
        $this->exportConfig();
        $calls = [];
        $application = $this->application($calls);

        // woot_deploy_failing() throws, so deploy:hook fails.
        $tester = $this->runCommand($application->find(DeployCommand::NAME), expected: Command::FAILURE);
        $this->assertSame(['updatedb'], $calls);
        $this->assertStringContainsString('There are no changes to import.', $tester->getDisplay());
        $this->assertStringContainsString('deploy:hook failed. Deployment stopped.', $tester->getDisplay());

        $calls = [];
        $application = $this->application($calls, ['updatedb' => 3]);
        $this->runCommand($application->find(DeployCommand::NAME), expected: 3);
        $this->assertSame(['updatedb'], $calls);
        $this->assertCount(1, $this->pending());
    }

    /**
     * An empty sync directory is rejected, and nothing runs after it.
     */
    public function testDeployImportFails(): void
    {
        $calls = [];
        $application = $this->application($calls);

        $tester = $this->runCommand($application->find(DeployCommand::NAME), expected: Command::FAILURE);
        $this->assertSame(['updatedb'], $calls);
        $this->assertStringContainsString('config import failed. Deployment stopped.', $tester->getDisplay());
        $this->assertCount(3, $this->pending());
    }

    public function testDeployMissingCommand(): void
    {
        $application = new Application();
        $application->addCommand(new DeployCommand($this->kernel()));
        $application->addCommand(new DeployHookCommand($this->kernel()));

        $tester = $this->runCommand($application->find(DeployCommand::NAME), expected: Command::FAILURE);
        $this->assertMatchesRegularExpression('/not available: updatedb,\s+cache:warm/', $tester->getDisplay());
        $this->assertCount(3, $this->pending());
    }

    /**
     * An application holding deploy, deploy:hook and stand-ins for the rest.
     *
     * @param string[] $calls
     *   Receives the names of the stand-ins as they run.
     * @param array<string, int> $exitCodes
     *   Exit codes of stand-ins that should fail, keyed by command name.
     */
    protected function application(array &$calls, array $exitCodes = []): Application
    {
        $application = new Application();
        $application->addCommand(new DeployCommand($this->kernel()));
        $application->addCommand(new DeployHookCommand($this->kernel()));
        foreach (['updatedb', 'cache:warm'] as $name) {
            $application->addCommand((new Command($name))->setCode(function () use (&$calls, $name, $exitCodes): int {
                $calls[] = $name;
                return $exitCodes[$name] ?? Command::SUCCESS;
            }));
        }
        return $application;
    }

    /**
     * Runs a command non-interactively and refreshes the container, which
     * deploy rebuilds.
     */
    protected function runCommand(callable|Command $command, array $input = [], int $expected = Command::SUCCESS): CommandTester
    {
        $tester = new CommandTester($command);
        $this->assertSame($expected, $tester->execute($input, ['interactive' => false]), $tester->getDisplay());
        $this->container = $this->kernel()->getContainer();
        return $tester;
    }

    /**
     * Makes the sync directory match the active configuration.
     */
    protected function exportConfig(): void
    {
        $this->copyConfig($this->container->get('config.storage'), $this->container->get('config.storage.sync'));
    }

    protected function kernel(): DrupalKernelInterface
    {
        return $this->container->get('kernel');
    }

    /**
     * @return string[]
     */
    protected function pending(): array
    {
        $executed = $this->container->get('keyvalue')->get('deploy_hook')->get('existing_updates', []);
        return array_diff(['woot_deploy_a', 'woot_deploy_batch', 'woot_deploy_failing'], $executed);
    }
}
