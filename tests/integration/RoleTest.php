<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Core\Site\Settings;
use Drush\Commands\core\CoreCommands;
use Drush\Commands\core\RoleCommands;
use Drush\Commands\pm\PmCommands;
use Symfony\Component\Filesystem\Path;

class RoleTest extends UnishIntegrationTestCase
{
    use TestModuleHelperTrait;

    const USER_FORM_TEST = 'user_form_test';

    public function setup(): void
    {
        parent::setUp();
        // Install Drupal if needed.
        $this->drush(CoreCommands::VERSION);
        // Help Drupal discover a test-only module within core's user module.
        $instance = Settings::getInstance();
        $all = $instance->getAll();
        $all['extension_discovery_scan_tests'] = true;
        $instance = new Settings($all);
        $this->drush(PmCommands::INSTALL, [self::USER_FORM_TEST]);
    }


    /**
     * Create, edit, block, and cancel users.
     */
    public function testRole()
    {
        $this->drush(RoleCommands::LIST);
        $output = $this->getOutput();
        $this->assertStringNotContainsString('cancel other accounts', $output);

        $this->drush(RoleCommands::LIST, [], ['filter' => 'cancel other accounts']);
        $output = $this->getOutput();
        $this->assertStringNotContainsString('authenticated', $output);
        $this->assertStringNotContainsString('anonymous', $output);

        // Create the role foo.
        $rid = 'foo';
        $this->drush(RoleCommands::CREATE, [$rid]);
        $this->drush(RoleCommands::LIST);
        $this->assertStringContainsString($rid, $this->getOutput());

        // Assert that anon user starts without 'cancel other accounts' perm.
        $perm = 'cancel other accounts';
        $this->drush(RoleCommands::LIST, [], ['format' => 'json']);
        $role = $this->getOutputFromJSON($rid);
        $this->assertFalse(in_array($perm, $role['perms']));

        // Now grant that perm to foo.
        $this->drush(RoleCommands::PERM_ADD, [$rid, 'cancel other accounts']);
        $this->drush(RoleCommands::LIST, [], ['format' => 'json']);
        $role = $this->getOutputFromJSON($rid);
        $this->assertTrue(in_array($perm, $role['perms']));

        // Now remove the perm from foo.
        $this->drush(RoleCommands::PERM_REMOVE, [$rid, 'cancel other accounts']);
        $this->drush(RoleCommands::LIST, [], ['format' => 'json']);
        $role = $this->getOutputFromJSON($rid);
        $this->assertFalse(in_array($perm, $role['perms']));

        // Delete the foo role
        $this->drush(RoleCommands::DELETE, [$rid]);
        $this->drush(RoleCommands::LIST);
        $this->assertStringNotContainsString($rid, $this->getOutput());
    }

    public function tearDown(): void
    {
        $this->drush(PmCommands::UNINSTALL, [self::USER_FORM_TEST]);
        // Revert our discovery change.
        $instance = Settings::getInstance();
        $all = $instance->getAll();
        $all['extension_discovery_scan_tests'] = false;
        $instance = new Settings($all);
        parent::tearDown();
    }
}
