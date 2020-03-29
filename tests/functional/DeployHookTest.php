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
        $this->drush('deploy:hook', [], [], null, null, self::EXIT_ERROR);

        $this->assertContains('woot     a         Successful deploy hook.', $this->getOutput());
        $this->assertContains('woot     batch     Successful batched deploy hook.', $this->getOutput());
        $this->assertContains('woot     failing   Failing deploy hook.', $this->getOutput());

        $this->assertContains('[notice] Deploy hook started: woot_deploy_a', $this->getErrorOutput());
        $this->assertContains('[notice] This is the update message from woot_deploy_a', $this->getErrorOutput());
        $this->assertContains('[notice] Deploy hook started: woot_deploy_batch', $this->getErrorOutput());
        $this->assertContains('[notice] Iteration 1.', $this->getErrorOutput());
        $this->assertContains('[notice] Iteration 2.', $this->getErrorOutput());
        $this->assertContains('[notice] Finished at 3.', $this->getErrorOutput());
        $this->assertContains('[notice] Deploy hook started: woot_deploy_failing', $this->getErrorOutput());
        $this->assertContains('[error]  Exception: This is the exception message thrown in woot_deploy_failing in woot_deploy_failing()', $this->getErrorOutput());
        $this->assertContains('[error]  Finished performing deploy hooks.', $this->getErrorOutput());

        // Set the drupal state so that the failing hook passes
        $this->drush('state:set', ['woot_deploy_pass', 'true'], [], null, null, self::EXIT_SUCCESS);

        // Run deploy hooks again.
        $this->drush('deploy:hook', [], [], null, null, self::EXIT_SUCCESS);

        $this->assertContains('woot     failing   Failing deploy hook.', $this->getOutput());
        $this->assertContains('[notice] Deploy hook started: woot_deploy_failing', $this->getErrorOutput());
        $this->assertContains('[notice] Now woot_deploy_failing is passing', $this->getErrorOutput());
        $this->assertContains('[success] Finished performing deploy hooks.', $this->getErrorOutput());

        // This time there is nothing more to run.
        $this->drush('deploy:hook', [], [], null, null, self::EXIT_SUCCESS);
        $this->assertContains('[success] No pending deploy hooks.', $this->getErrorOutput());
        $this->assertNotContains('Finished performing deploy hooks.', $this->getErrorOutput());

        // Resetting a deploy hook.
        $this->drush('deploy:hook-reset', ['woot_deploy_a'], [], null, null, self::EXIT_SUCCESS);
        $this->assertContains('[success] Deploy hook woot_deploy_a reset.', $this->getErrorOutput());

        // Resetting the deploy hook which is already reset.
        $this->drush('deploy:hook-reset', ['woot_deploy_a'], [], null, null, self::EXIT_SUCCESS);
        $this->assertContains('[warning] Deploy hook woot_deploy_a has not run yet.', $this->getErrorOutput());

        // Check the status.
        $this->drush('deploy:hook-info', [], [], null, null, self::EXIT_SUCCESS);
        $this->assertContains('woot     a      Successful deploy hook.', $this->getOutput());
        $this->assertNotContains('Successful batched deploy hook', $this->getOutput());
        $this->assertNotContains('Failing deploy hook', $this->getOutput());
    }
}
