<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\StatusCommands;
use Drush\Commands\core\DeployHookCommands;
use Drush\Commands\core\StateCommands;
use Drush\Commands\pm\PmCommands;
use Symfony\Component\Filesystem\Path;

/**
 *  @group slow
 *  @group commands
 */
class DeployHookTest extends CommandUnishTestCase
{
    use TestModuleHelperTrait;

    public function testDeployHooks()
    {

        $this->setUpDrupal(1, true);
        $options = [
            'yes' => null,
        ];
        $this->drush(PmCommands::INSTALL, ['woot'], $options);

        // Run deploy hooks.
        $this->drush(DeployHookCommands::HOOK, [], $options, null, null, self::EXIT_ERROR);

        $this->assertStringContainsString('woot     a         Successful deploy hook.', $this->getOutput());
        $this->assertStringContainsString('woot     batch     Successful batched deploy hook.', $this->getOutput());
        $this->assertStringContainsString('woot     failing   Failing deploy hook.', $this->getOutput());

        $this->assertStringContainsString('[notice] Deploy hook started: woot_deploy_a', $this->getErrorOutput());
        $this->assertStringContainsString('[notice] This is the update message from woot_deploy_a', $this->getErrorOutput());
        $this->assertStringContainsString('[notice] Deploy hook started: woot_deploy_batch', $this->getErrorOutput());
        $this->assertStringContainsString('[notice] Iteration 1.', $this->getErrorOutput());
        $this->assertStringContainsString('[notice] Iteration 2.', $this->getErrorOutput());
        $this->assertStringContainsString('[notice] Finished at 3.', $this->getErrorOutput());
        $this->assertStringContainsString('[notice] Deploy hook started: woot_deploy_failing', $this->getErrorOutput());
        $this->assertStringContainsString('[error]  This is the exception message thrown in woot_deploy_failing', $this->getErrorOutput());
        $this->assertStringContainsString('[error]  Finished performing deploy hooks.', $this->getErrorOutput());

        // Set the drupal state so that the failing hook passes
        $this->drush(StateCommands::SET, ['woot_deploy_pass', 'true'], [], null, null, self::EXIT_SUCCESS);

        // Run deploy hooks again.
        $this->drush(DeployHookCommands::HOOK, [], $options, null, null, self::EXIT_SUCCESS);

        $this->assertStringContainsString('woot     failing   Failing deploy hook.', $this->getOutput());
        $this->assertStringContainsString('[notice] Deploy hook started: woot_deploy_failing', $this->getErrorOutput());
        $this->assertStringContainsString('[notice] Now woot_deploy_failing is passing', $this->getErrorOutput());
        $this->assertStringContainsString('[success] Finished performing deploy hooks.', $this->getErrorOutput());

        // This time there is nothing more to run.
        $this->drush(DeployHookCommands::HOOK, [], [], null, null, self::EXIT_SUCCESS);
        $this->assertStringContainsString('[success] No pending deploy hooks.', $this->getErrorOutput());
        $this->assertStringNotContainsString('Finished performing deploy hooks.', $this->getErrorOutput());
    }

    public function testSkipDeployHooks()
    {
        $this->setUpDrupal(1, true);
        $this->drush(PmCommands::INSTALL, ['woot'], ['yes' => null]);

        $options = [
            'format' => 'json'
        ];
        $hooks = [
            [
                "module" => "woot",
                "hook" => "a",
                "description" => "Successful deploy hook.",
            ],
            [
                "module" => "woot",
                "hook" => "batch",
                "description" => "Successful batched deploy hook.",
            ],
            [
                "module" => "woot",
                "hook" => "failing",
                "description" => "Failing deploy hook.",
            ],
        ];
        // Check pending deploy hooks.
        $this->drush(DeployHookCommands::HOOK_STATUS, [], $options, null, null, self::EXIT_SUCCESS);
        $this->assertEquals($hooks, $this->getOutputFromJSON());

        // Mark them all as having run.
        $this->drush(DeployHookCommands::MARK_COMPLETE, [], [], null, null, self::EXIT_SUCCESS);
        $this->assertStringContainsString('[success] Marked 3 pending deploy hooks as complete.', $this->getErrorOutput());

        // Check again to see no pending hooks.
        $this->drush(DeployHookCommands::HOOK_STATUS, [], $options, null, null, self::EXIT_SUCCESS);
        $this->assertStringContainsString('[]', $this->getOutput());
    }

    public function testDeployHooksInModuleWithDeployInName()
    {
        $this->setUpDrupal(1, true);
        $options = [
            'yes' => null,
        ];
        $this->drush(PmCommands::INSTALL, ['woot_deploy'], $options);

        // Run deploy hooks.
        $this->drush(DeployHookCommands::HOOK, [], $options, null, null, self::EXIT_SUCCESS);

        $this->assertStringContainsString('[notice] Deploy hook started: woot_deploy_deploy_function', $this->getErrorOutput());
        $this->assertStringContainsString('[notice] This is the update message from woot_deploy_deploy_function', $this->getErrorOutput());
        $this->assertStringContainsString('[notice] Performed: woot_deploy_deploy_function', $this->getErrorOutput());
        $this->assertStringContainsString('[success] Finished performing deploy hooks.', $this->getErrorOutput());
    }
}
