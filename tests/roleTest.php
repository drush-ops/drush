<?php

/*
 * @file
 *   Tests for role.drush.inc
 */

/*
 *  @group slow
 *  @group commands
 */
class roleCase extends Drush_CommandTestCase {

  /*
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
    $this->drush('role-list', array('anonymous user'), $options );
    $output = $this->getOutput();
    $this->assertEquals('access content', $output);
    $this->drush('role-list', array('authenticated user'), $options );
    $output = $this->getOutput();
    $this->assertEquals('access content', $output);
    $this->drush('role-add-perm', array('anonymous user', 'administer nodes'), $options );
    $this->drush('role-list', array('anonymous user'), $options );
    $output = $this->getOutput();
    $this->assertContains('administer nodes', $output);
    $this->drush('role-remove-perm', array('anonymous user', 'administer nodes'), $options );
    $this->drush('role-list', array('anonymous user'), $options );
    $output = $this->getOutput();
    $this->assertEquals('access content', $output);
  }
}
