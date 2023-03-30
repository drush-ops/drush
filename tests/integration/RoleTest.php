<?php

declare(strict_types=1);

namespace Unish;

use Drush\Drupal\Commands\core\RoleCommands;
use Drush\Drupal\Commands\pm\PmCommands;
use Symfony\Component\Filesystem\Path;

class RoleTest extends UnishIntegrationTestCase
{
    use TestModuleHelperTrait;

    /**
     * Create, edit, block, and cancel users.
     */
    public function testRole()
    {
        // In D8+, the testing profile has no perms.
        // Copy the module to where Drupal expects it.
        $this->setupModulesForTests(['user_form_test'], Path::join($this->webroot(), 'core/modules/user/tests/modules'));
        $this->drush(PmCommands::INSTALL, ['user_form_test']);

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

        $this->drush(PmCommands::UNINSTALL, ['user_form_test']);
        $this->recursiveDelete(Path::join($this->webroot(), "modules/unish/user_form_test"));
    }
}
