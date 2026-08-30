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

    /**
     * Test the deploy:hook-list command.
     */
    public function testDeployHookList()
    {
        $this->setUpDrupal(1, true);
        $options = [
            'yes' => null,
        ];
        $this->drush(PmCommands::INSTALL, ['woot'], $options);

        // Run deploy hooks to create some deployed hooks.
        $this->drush(DeployHookCommands::HOOK, [], $options, null, null, self::EXIT_ERROR);

        // Set the drupal state so that the failing hook passes
        $this->drush(StateCommands::SET, ['woot_deploy_pass', 'true'], [], null, null, self::EXIT_SUCCESS);

        // Run deploy hooks again to complete all hooks.
        $this->drush(DeployHookCommands::HOOK, [], $options, null, null, self::EXIT_SUCCESS);

        // Test the hook-list command.
        $options = [
            'format' => 'json'
        ];
        $this->drush(DeployHookCommands::HOOK_LIST, [], $options, null, null, self::EXIT_SUCCESS);

        $output = $this->getOutputFromJSON();
        $this->assertNotEmpty($output);

        // Check that the expected hooks are in the list
        $found_hooks = [
            'woot_deploy_a' => false,
            'woot_deploy_batch' => false,
            'woot_deploy_failing' => false,
        ];

        foreach ($output as $hook) {
            if ($hook['module'] === 'woot' && $hook['hook'] === 'a') {
                $found_hooks['woot_deploy_a'] = true;
            }
            if ($hook['module'] === 'woot' && $hook['hook'] === 'batch') {
                $found_hooks['woot_deploy_batch'] = true;
            }
            if ($hook['module'] === 'woot' && $hook['hook'] === 'failing') {
                $found_hooks['woot_deploy_failing'] = true;
            }
        }

        $this->assertTrue($found_hooks['woot_deploy_a'], 'Hook woot_deploy_a should be in the list');
        $this->assertTrue($found_hooks['woot_deploy_batch'], 'Hook woot_deploy_batch should be in the list');
        $this->assertTrue($found_hooks['woot_deploy_failing'], 'Hook woot_deploy_failing should be in the list');
    }

    /**
     * Test the deploy:hook-unset command.
     */
    public function testDeployHookUnset()
    {
        $this->setUpDrupal(1, true);
        $options = [
            'yes' => null,
        ];
        $this->drush(PmCommands::INSTALL, ['woot'], $options);

        // Run deploy hooks to create some deployed hooks.
        $this->drush(DeployHookCommands::HOOK, [], $options, null, null, self::EXIT_ERROR);

        // Set the drupal state so that the failing hook passes
        $this->drush(StateCommands::SET, ['woot_deploy_pass', 'true'], [], null, null, self::EXIT_SUCCESS);

        // Run deploy hooks again to complete all hooks.
        $this->drush(DeployHookCommands::HOOK, [], $options, null, null, self::EXIT_SUCCESS);

        // Test the hook-unset command.
        $this->drush(DeployHookCommands::HOOK_UNSET, ['woot_deploy_a'], [], null, null, self::EXIT_SUCCESS);
        $this->assertStringContainsString(
            '[success] Hook woot_deploy_a removed from deployed hooks list.',
            $this->getErrorOutput()
        );

        // Verify the hook is no longer in the list
        $options = [
            'format' => 'json'
        ];
        $this->drush(DeployHookCommands::HOOK_LIST, [], $options, null, null, self::EXIT_SUCCESS);

        $output = $this->getOutputFromJSON();
        $found_hook_a = false;

        foreach ($output as $hook) {
            if ($hook['module'] === 'woot' && $hook['hook'] === 'a') {
                $found_hook_a = true;
                break;
            }
        }

        $this->assertFalse($found_hook_a, 'Hook woot_deploy_a should not be in the list after unset');
    }

    /**
     * Test the deploy:redeploy command.
     */
    public function testDeployHookRedeploy()
    {
        $this->setUpDrupal(1, true);
        $options = [
            'yes' => null,
        ];
        $this->drush(PmCommands::INSTALL, ['woot'], $options);

        // Run deploy hooks to create some deployed hooks.
        $this->drush(DeployHookCommands::HOOK, [], $options, null, null, self::EXIT_ERROR);

        // Set the drupal state so that the failing hook passes
        $this->drush(StateCommands::SET, ['woot_deploy_pass', 'true'], [], null, null, self::EXIT_SUCCESS);

        // Run deploy hooks again to complete all hooks.
        $this->drush(DeployHookCommands::HOOK, [], $options, null, null, self::EXIT_SUCCESS);

        // Test the redeploy command.
        $this->drush(DeployHookCommands::HOOK_REDEPLOY, ['woot_deploy_a'], $options, null, null, self::EXIT_SUCCESS);

        $this->assertStringContainsString('[notice] Deploy hook started: woot_deploy_a', $this->getErrorOutput());
        $this->assertStringContainsString(
            '[notice] This is the update message from woot_deploy_a',
            $this->getErrorOutput()
        );
        $this->assertStringContainsString('[success] Finished performing re-deploy hooks.', $this->getErrorOutput());
    }
}
