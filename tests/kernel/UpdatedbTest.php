<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Core\Update\UpdateHookRegistry;
use Drupal\Core\Update\UpdateRegistry;
use Drupal\KernelTests\KernelTestBase;
use Drush\Doctor\Commands\UpdatedbCommand;
use Drush\Doctor\Commands\UpdatedbStatusCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the dr-only updatedb commands.
 *
 * drush_empty_module ships drush_empty_module_update_8001(); its schema
 * version is set to 8000 so that update is pending. Post updates come from
 * fixture files under resources/, since both registries discover hooks by
 * scanning defined functions.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(UpdatedbCommand::class)]
#[CoversClass(UpdatedbStatusCommand::class)]
class UpdatedbTest extends KernelTestBase
{
    protected static $modules = ['system', 'user', 'drush_empty_module'];

    protected function setUp(): void
    {
        parent::setUp();
        // A fresh kernel test has every post update of system pending, and
        // the update requirements check treats removed post updates that
        // were never invoked as missing. Mark both as done.
        $registry = $this->postUpdateRegistry();
        $registry->registerInvokedUpdates($registry->getPendingUpdateFunctions());
        foreach (array_keys($this->container->get('module_handler')->getModuleList()) as $module) {
            $registry->registerInvokedUpdates(array_keys($registry->getRemovedPostUpdates($module)));
        }
        // enableModules() records no schema versions. Load every .install
        // file first: the registry scans defined functions once per process.
        $modules = array_keys($this->container->get('module_handler')->getModuleList());
        foreach ($modules as $module) {
            $this->container->get('module_handler')->loadInclude($module, 'install');
        }
        foreach ($modules as $module) {
            $versions = $this->hookRegistry()->getAvailableUpdates($module);
            $lastRemoved = $module . '_update_last_removed';
            if (function_exists($lastRemoved)) {
                $versions[] = $lastRemoved();
            }
            $this->hookRegistry()->setInstalledVersion($module, $versions ? max($versions) : \Drupal::CORE_MINIMUM_SCHEMA_VERSION);
        }
        $this->hookRegistry()->setInstalledVersion('drush_empty_module', 8000);
    }

    public function testStatusNoPending(): void
    {
        $this->hookRegistry()->setInstalledVersion('drush_empty_module', 8001);

        $tester = $this->runStatus();
        $this->assertStringContainsString('No database updates required.', $tester->getDisplay());
        $this->assertStringNotContainsString('drush_empty_module', $tester->getDisplay());

        $tester = $this->runStatus(['--format' => 'json']);
        $this->assertSame('[]', trim(preg_replace('/^.*No database updates required.*$/m', '', $tester->getDisplay())));
    }

    public function testStatus(): void
    {
        require_once __DIR__ . '/resources/updatedb_post_update.php';

        $display = $this->runStatus()->getDisplay();
        $this->assertMatchesRegularExpression('/Extension\s+\|\s+Update ID\s+\|\s+Type\s+\|\s+Description/', $display);
        $this->assertMatchesRegularExpression('/drush_empty_module\s+\|\s+8001\s+\|\s+update\s+\|\s+Fake update hook\./', $display);
        $this->assertMatchesRegularExpression('/drush_empty_module\s+\|\s+fixture\s+\|\s+post_update\s+\|\s+Fixture post update\./', $display);

        $json = json_decode($this->runStatus(['--format' => 'json'])->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(2, $json);
        $this->assertSame(['extension' => 'drush_empty_module', 'update_id' => 8001, 'type' => 'update', 'description' => 'Fake update hook.'], $json[0]);
        $this->assertSame('fixture', $json[1]['update_id']);
    }

    public function testUpdatedb(): void
    {
        require_once __DIR__ . '/resources/updatedb_post_update.php';

        $tester = $this->updatedb();
        $display = $tester->getDisplay();
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $display);
        $needles = [
            'Update started: drush_empty_module_update_8001',
            'The update ran',
            'Update completed: drush_empty_module_update_8001',
            'Update started: drush_empty_module_post_update_fixture',
            'The post update ran',
            'Update completed: drush_empty_module_post_update_fixture',
            'Finished performing updates.',
        ];
        foreach ($needles as $needle) {
            $this->assertStringContainsString($needle, $display);
        }
        $this->assertSame(8001, $this->hookRegistry()->getInstalledVersion('drush_empty_module'));
        $this->assertNotContains('drush_empty_module_post_update_fixture', $this->postUpdateRegistry()->getPendingUpdateFunctions());
        $this->assertFalse((bool) $this->container->get('state')->get('system.maintenance_mode'));
        $this->assertStringContainsString('No database updates required.', $this->runStatus()->getDisplay());
    }

    public function testFailedUpdate(): void
    {
        require_once __DIR__ . '/resources/updatedb_post_update.php';
        require_once __DIR__ . '/resources/updatedb_failing.php';

        $tester = $this->updatedb();
        $display = $tester->getDisplay();
        $this->assertSame(Command::FAILURE, $tester->getStatusCode(), $display);
        $needles = [
            'Update completed: drush_empty_module_update_8001',
            'Fixture failure in drush_empty_module_update_8002',
            'Update failed: drush_empty_module_update_8002',
            'Skipped drush_empty_module_post_update_fixture',
            'Update aborted by: drush_empty_module_update_8002',
        ];
        foreach ($needles as $needle) {
            $this->assertStringContainsString($needle, $display);
        }
        $this->assertSame(8001, $this->hookRegistry()->getInstalledVersion('drush_empty_module'));
        $this->assertContains('drush_empty_module_post_update_fixture', $this->postUpdateRegistry()->getPendingUpdateFunctions());
        $this->assertFalse((bool) $this->container->get('state')->get('system.maintenance_mode'));
    }

    public function testDeclined(): void
    {
        $tester = $this->updatedb(['no']);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('Do you wish to run the specified pending updates?', $tester->getDisplay());
        $this->assertSame(8000, $this->hookRegistry()->getInstalledVersion('drush_empty_module'));
    }

    public function testRequirementsError(): void
    {
        $this->hookRegistry()->setInstalledVersion('system', 7999);

        $tester = $this->updatedb();
        $display = $tester->getDisplay();
        $this->assertSame(Command::FAILURE, $tester->getStatusCode(), $display);
        $this->assertStringContainsString('Minimum schema version', $display);
        $this->assertStringContainsString('Requirements check reports errors.', $display);
        $this->assertSame(8000, $this->hookRegistry()->getInstalledVersion('drush_empty_module'));
    }

    protected function runStatus(array $input = []): CommandTester
    {
        $tester = new CommandTester(new UpdatedbStatusCommand($this->container->get('kernel')));
        $this->assertSame(Command::SUCCESS, $tester->execute($input), $tester->getDisplay());
        return $tester;
    }

    /**
     * Runs updatedb, answering the confirmation with $inputs, or yes when
     * none are given (non-interactive). Refreshes the container afterwards,
     * since the command rebuilds it.
     */
    protected function updatedb(?array $inputs = null): CommandTester
    {
        $tester = new CommandTester(new UpdatedbCommand($this->container->get('kernel')));
        if ($inputs !== null) {
            $tester->setInputs($inputs);
        }
        $tester->execute([], ['interactive' => $inputs !== null]);
        $this->container = $this->container->get('kernel')->getContainer();
        return $tester;
    }

    protected function hookRegistry(): UpdateHookRegistry
    {
        return $this->container->get('update.update_hook_registry');
    }

    protected function postUpdateRegistry(): UpdateRegistry
    {
        return $this->container->get('update.post_update_registry');
    }
}
