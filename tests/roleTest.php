<?php

/**
 * @file
 *   Tests for role.drush.inc
 */

/**
 *  @group slow
 *  @group commands
 */
class roleCase extends Drush_CommandTestCase {

  /**
   * Create, edit, block, and cancel users.
   */
  public function testRole() {
    $sites = $this->setUpDrupal(1, TRUE);
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
    $this->assertEquals('access content', $output);
    $this->drush('role-list', array($authenticated), $options + array('pipe' => NULL) );
    $output = $this->getOutput();
    $this->assertEquals('access content', $output);
    $this->drush('role-add-perm', array($anonymous, 'administer nodes'), $options );
    $this->drush('role-list', array($anonymous), $options + array('pipe' => NULL) );
    $output = $this->getOutput();
    $this->assertContains('administer nodes', $output);
    $this->drush('role-remove-perm', array($anonymous, 'administer nodes'), $options );
    $this->drush('role-list', array($anonymous), $options + array('pipe' => NULL) );
    $output = $this->getOutput();
    $this->assertEquals('access content', $output);
  }
}
