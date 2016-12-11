<?php

/**
 * @file
 *   Tests for role.drush.inc
 */

namespace Unish;

/**
 *  @group slow
 *  @group commands
 */
class roleCase extends CommandUnishTestCase {

  /**
   * Create, edit, block, and cancel users.
   */
  public function testRole() {
    // In D8+, the testing profile has no perms.
    $sites = $this->setUpDrupal(1, TRUE, UNISH_DRUPAL_MAJOR_VERSION, 'standard');
    $root = $this->webroot();
    $options = array(
      'root' => $root,
      'uri' => key($sites),
      'yes' => NULL,
    );

    $this->drush('role-list', array(), $options);
    $output = $this->getOutput();
    $this->assertContains('access content', $output);

    $this->drush('role-list', array(), $options + array('filter' => 'post comments'));
    $output = $this->getOutput();
    $this->assertContains('authenticated', $output);
    $this->assertNotContains('anonymous', $output);

    // Create and check the role foo.
    $rid = 'foo';
    $this->drush('role-create', array($rid), $options);
    $this->drush('role-list', array(), $options);
    $this->assertContains($rid, $this->getOutput());

    // Assert that anon user starts without 'administer nodes' perm.
    $perm = 'administer nodes';
    $this->drush('role-list', array(), $options + array('format' => 'json'));
    $role = $this->getOutputFromJSON($rid);
    $this->assertFalse(in_array($perm, $role->perms));

    // Now grant that perm.
    $this->drush('role-add-perm', array($rid, 'administer nodes'), $options);
    $this->drush('role-list', array(), $options + array('format' => 'json'));
    $role = $this->getOutputFromJSON($rid);
    $this->assertTrue(in_array($perm, $role->perms));

    // Now remove the perm.
    $this->drush('role-remove-perm', array($rid, 'administer nodes'), $options );
    $this->drush('role-list', array(), $options + array('format' => 'json'));
    $role = $this->getOutputFromJSON($rid);
    $this->assertFalse(in_array($perm, $role->perms));

    // Delete the foo role
    $this->drush('role-delete', array($rid), $options);
    $this->drush('role-list', array(), $options);
    $this->assertNotContains($rid, $this->getOutput());

  }
}
