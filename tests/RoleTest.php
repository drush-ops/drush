<?php

namespace Unish;

use Webmozart\PathUtil\Path;

/**
 *  @group slow
 *  @group commands
 */
class RoleCase extends CommandUnishTestCase
{
    use TestModuleHelperTrait;

    /**
     * Create, edit, block, and cancel users.
     */
    public function testRole()
    {
        $this->setUpDrupal(1, true);

        // In D8+, the testing profile has no perms.
        // Copy the module to where Drupal expects it.
        $this->setupModulesForTests(['user_form_test'], Path::join($this->webroot(), 'core/modules/user/tests/modules'));
        $this->drush('pm-enable', ['user_form_test']);

        $this->drush('role-list');
        $output = $this->getOutput();
        $this->assertNotContains('cancel other accounts', $output);

        $this->drush('role-list', [], ['filter' => 'cancel other accounts']);
        $output = $this->getOutput();
        $this->assertNotContains('authenticated', $output);
        $this->assertNotContains('anonymous', $output);

        // Create the role foo.
        $rid = 'foo';
        $this->drush('role-create', [$rid]);
        $this->drush('role-list');
        $this->assertContains($rid, $this->getOutput());

        // Assert that anon user starts without 'cancel other accounts' perm.
        $perm = 'cancel other accounts';
        $this->drush('role-list', [], ['format' => 'json']);
        $role = $this->getOutputFromJSON($rid);
        $this->assertFalse(in_array($perm, $role->perms));

        // Now grant that perm to foo.
        $this->drush('role-add-perm', [$rid, 'cancel other accounts']);
        $this->drush('role-list', [], ['format' => 'json']);
        $role = $this->getOutputFromJSON($rid);
        $this->assertTrue(in_array($perm, $role->perms));

        // Now remove the perm from foo.
        $this->drush('role-remove-perm', [$rid, 'cancel other accounts']);
        $this->drush('role-list', [], ['format' => 'json']);
        $role = $this->getOutputFromJSON($rid);
        $this->assertFalse(in_array($perm, $role->perms));

        // Delete the foo role
        $this->drush('role-delete', [$rid]);
        $this->drush('role-list');
        $this->assertNotContains($rid, $this->getOutput());
    }
}
