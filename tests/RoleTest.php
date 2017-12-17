<?php

namespace Unish;
use Webmozart\PathUtil\Path;

/**
 *  @group slow
 *  @group commands
 */
class RoleCase extends CommandUnishTestCase {

  /**
   * Create, edit, block, and cancel users.
   */
    public function testRole()
    {
        $sites = $this->setUpDrupal(1, true);
        $root = $this->webroot();

        // In D8+, the testing profile has no perms.
        // Copy the module to where Drupal expects it.
        $this->setupModulesForTests($root);
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

    public function setupModulesForTests($root)
    {
        $sourceDir = Path::join($root, 'core/modules/user/tests/modules/user_form_test');
        $targetDir = Path::join($root, 'modules/contrib');
        $this->mkdir($targetDir);
        $this->recursiveCopy($sourceDir, $targetDir);
    }
}
