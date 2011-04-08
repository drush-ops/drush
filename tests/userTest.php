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
    $output = $this->getOutput();
    $row  = str_getcsv($output);
    $uid = $row[1];
    $this->assertEquals('example@example.com', $row[2]);
    $this->assertEquals($name, $row[0]);
    $this->assertEquals(1, $row[3], 'Newly created user is Active.');
    $this->assertEquals('authenticated user', $row[4], 'Newly created user has one role.');

    // user-block
    $this->drush('user-block', array($name), $options);
    $this->drush('user-information', array($name), $options + array('pipe' => NULL));
    $output = $this->getOutput();
    $row  = str_getcsv($output);
    $this->assertEquals(0, $row[3], 'User is blocked.');

    // user-unblock
    $this->drush('user-unblock', array($name), $options);
    $this->drush('user-information', array($name), $options + array('pipe' => NULL));
    $output = $this->getOutput();
    $row  = str_getcsv($output);
    $this->assertEquals(1, $row[3], 'User is unblocked.');

    // user-add-role
    // first, create the fole since we use testing install profile.
    $eval = "user_role_save((object)array('name' => 'administrator'))";
    $this->drush('php-eval', array($eval), $options);
    $this->drush('user-add-role', array('administrator', $name), $options);
    $this->drush('user-information', array($name), $options + array('pipe' => NULL));
    $output = $this->getOutput();
    $row  = str_getcsv($output);
    $this->assertEquals('authenticated user, administrator', $row[4], 'User has administrator role.');

    // user-remove-role
    $this->drush('user-remove-role', array('administrator', $name), $options);
    $this->drush('user-information', array($name), $options + array('pipe' => NULL));
    $output = $this->getOutput();
    $row  = str_getcsv($output);
    $this->assertEquals('authenticated user', $row[4], 'User removed administrator role.');

    // user-password
    $newpass = 'newpass';
    $this->drush('user-password', array($name), $options + array('password' => $newpass));
    $eval = "require_once DRUPAL_ROOT . '/' . variable_get('password_inc', 'includes/password.inc');";
    $eval .= "\$account = user_load_by_name('example');";
    $eval .= "print (string) user_check_password('$newpass', \$account)";
    $this->drush('php-eval', array($eval), $options);
    $output = $this->getOutput();
    $this->assertEquals('1', $output, 'User can login with new password.');

    // user-login
    $this->drush('user-login', array($name), $options);
    $output = $this->getOutput();
    $url = parse_url($output);
    $this->assertStringStartsWith('/user/reset/' . $uid, $url['path'], 'Login returned a valid reset URL');

    // user-cancel
    // create content
    $eval = $this->create_node_types_php();
    $this->drush('php-eval', array($eval), $options);
    $eval = "
      \$node = (object) array(
        'title' => 'foo',
        'uid' => 2,
        'type' => 'page',
      );
      node_save(\$node);
    ";
    $this->drush('php-eval', array($eval), $options);
    $this->drush('user-cancel', array($name), $options + array('delete-content' => NULL));
    $eval = 'print (string) user_load(2)';
    $this->drush('php-eval', array($eval), $options);
    $output = $this->getOutput();
    $this->assertEmpty($output, 'User was deleted');
    $eval = 'print (string) node_load(2)';
    $this->drush('php-eval', array($eval), $options);
    $output = $this->getOutput();
    $this->assertEmpty($output, 'Content was deleted');
  }
}