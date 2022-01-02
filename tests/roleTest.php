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
    $sites = $this->setUpDrupal(1, TRUE, UNISH_DRUPAL_MAJOR_VERSION, UNISH_DRUPAL_MAJOR_VERSION == 6 ? 'default' : 'standard');
    $root = $this->webroot();
    $name = "example";
    $options = array(
      'root' => $root,
      'uri' => key($sites),
      'yes' => NULL,
    );
    $anonymous = 'anonymous';
    $authenticated = 'authenticated';
    if (UNISH_DRUPAL_MAJOR_VERSION < 8) {
      $anonymous .= ' user';
      $authenticated .= ' user';
    }
    $this->drush('role-list', array($anonymous), $options + array('pipe' => NULL) );
    $output = $this->getOutput();
    $this->assertStringContainsString('access content', $output);
    $this->drush('role-list', array($authenticated), $options + array('pipe' => NULL) );
    $output = $this->getOutput();
    $this->assertStringContainsString('access content', $output);
    $this->drush('role-add-perm', array($anonymous, 'administer nodes'), $options );
    $this->drush('role-list', array($anonymous), $options + array('pipe' => NULL) );
    $output = $this->getOutput();
    $this->assertStringContainsString('administer nodes', $output);
    $this->drush('role-remove-perm', array($anonymous, 'administer nodes'), $options );
    $this->drush('role-list', array($anonymous), $options + array('pipe' => NULL) );
    $output = $this->getOutput();
    $this->assertStringNotContainsString('administer nodes', $output);
  }
}
