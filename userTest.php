<?php

/*
 * @file
 *   Tests for user.drush.inc
 */
class userCase extends Drush_TestCase {

  /*
   * Create, edit, block, and cancel users.
   */
  public function testUser() {
    // user-create
    $env = 'dev';
    $this->setUpDrupal($env, TRUE);
    $root = $this->sites[$env]['root'];
    $name = "example";
    $options = array(
      'root' => $root,
      'uri' => $env,
      'yes' => NULL,
    );
    $this->drush('user-create', array($name), $options + array('password' => 'password', 'mail' => "example@example.com"));
    $this->drush('user-information', array($name), $options + array('pipe' => NULL));
    $output = $this->getOutputAsList();
    // getcsv
    $this->assertEquals($options['mail'], $row[2]);
    $this->assertEquals($options['name'], $row[0]);
    $this->assertEquals(1, $row[3], 'Newly created user is Active.');
    $this->assertEquals('authenticated user', $row[4], 'Newly created user has one role.');

    // user-block
    $this->drush('user-block', array($name), $options);
    $this->drush('user-information', array($name), $options + array('pipe' => NULL));
    $output = $this->getOutputAsList();
    // getcsv
    $this->assertEquals(0, $row[3], 'User is blocked.');

    // user-unblock
    $this->drush('user-unblock', array($name), $options);
    $this->drush('user-information', array($name), $options + array('pipe' => NULL));
    $output = $this->getOutputAsList();
    // getcsv
    $this->assertEquals(1, $row[3], 'User is unblocked.');

    // user-add-role
    $this->drush('user-add-role', array('administrator', $name), $options);
    $this->drush('user-information', array($name), $options + array('pipe' => NULL));
    $output = $this->getOutputAsList();
    // getcsv
    $this->assertEquals('authenticated user, administrator', $row[4], 'User has administrator role.');

    // user-remove-role
    $this->drush('user-remove-role', array('administrator', $name), $options);
    $this->drush('user-information', array($name), $options + array('pipe' => NULL));
    $output = $this->getOutputAsList();
    // getcsv
    $this->assertEquals('authenticated user', $row[4], 'User removed administrator role.');

    // user-password
    $newpass = 'newpass';
    $this->drush('user-password', array($name), $options + array('password' => $newpass));
    $eval = "require_once DRUPAL_ROOT . '/' . variable_get('password_inc', 'includes/password.inc');";
    $eval .= "\$account = user_load_by_name('example');";
    $eval .= "print (string) user_check_password($newpass, \$account)";
    $this->drush('php-eval', array($eval), $options);
    $output = $this->getOutput();
    $this->assertTrue('1', $output, 'User can login with new password.');

    //user-login
    $this->drush('user-login', array($name), $options);
    $output = $this->getOutput();
    $this->assertTrue(parse_url($output), 'Login returned a valid URL');

    // user-cancel
    // create content
    $this->create_node_types();
    $node = (object) array(
      'title' => 'foo',
      'uid' => 2,
      'type' => 'page',
    );
    node_save($node);
    $this->drush('user-cancel', array($name), $options + array('delete-content' => NULL));
    $this->assertFalse(user_load(2), 'User was deleted');
    $this->assertFalse(node_load(2), 'Content was deleted');
  }
}