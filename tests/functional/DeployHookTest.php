<?php

namespace Unish;

use Webmozart\PathUtil\Path;

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
        $this->setupModulesForTests(['woot'], Path::join(__DIR__, 'resources/modules/d8'));
        $this->drush('pm-enable', ['woot'], $options);

        // Run deploy hooks.
        $this->drush('deploy:hook', [], $options, null, null, self::EXIT_ERROR);

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
        $this->assertStringContainsString('[error]  Exception: This is the exception message thrown in woot_deploy_failing in woot_deploy_failing()', $this->getErrorOutput());
        $this->assertStringContainsString('[error]  Finished performing deploy hooks.', $this->getErrorOutput());

        // Set the drupal state so that the failing hook passes
        $this->drush('state:set', ['woot_deploy_pass', 'true'], [], null, null, self::EXIT_SUCCESS);

        // Run deploy hooks again.
        $this->drush('deploy:hook', [], $options, null, null, self::EXIT_SUCCESS);

        $this->assertStringContainsString('woot     failing   Failing deploy hook.', $this->getOutput());
        $this->assertStringContainsString('[notice] Deploy hook started: woot_deploy_failing', $this->getErrorOutput());
        $this->assertStringContainsString('[notice] Now woot_deploy_failing is passing', $this->getErrorOutput());
        $this->assertStringContainsString('[success] Finished performing deploy hooks.', $this->getErrorOutput());

        // This time there is nothing more to run.
        $this->drush('deploy:hook', [], [], null, null, self::EXIT_SUCCESS);
        $this->assertStringContainsString('[success] No pending deploy hooks.', $this->getErrorOutput());
        $this->assertStringNotContainsString('Finished performing deploy hooks.', $this->getErrorOutput());
    }

    public function testSkipDeployHooks()
    {
        $this->setUpDrupal(1, true);
        $this->setupModulesForTests(['woot'], Path::join(__DIR__, 'resources/modules/d8'));
        $this->drush('pm-enable', ['woot'], ['yes' => null]);

        $options = [
            'format' => 'json'
        ];
        $hooks = <<<JSON
[
    {
        "module": "woot",
        "hook": "a",
        "description": "Successful deploy hook."
    },
    {
        "module": "woot",
        "hook": "batch",
        "description": "Successful batched deploy hook."
    },
    {
        "module": "woot",
        "hook": "failing",
        "description": "Failing deploy hook."
    }
]
JSON;
        // Check pending deploy hooks.
        $this->drush('deploy:hook-status', [], $options, null, null, self::EXIT_SUCCESS);
        $this->assertEquals(json_decode($hooks), json_decode($this->getOutput()));

        // Mark them all as having run.
        $this->drush('deploy:hook-skip', [], [], null, null, self::EXIT_SUCCESS);
        $this->assertStringContainsString('[success] Marked 3 pending deploy hooks as having run.', $this->getErrorOutput());

        // Check again to see no pending hooks.
        $this->drush('deploy:hook-status', [], $options, null, null, self::EXIT_SUCCESS);
        $this->assertStringContainsString('[]', $this->getOutput());
    }
}
