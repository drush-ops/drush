<?php

/*
 * @file
 *   Tests for user.drush.inc
 */

/*
 *  @group slow
 *  @group commands
 */
class userCase extends Drush_CommandTestCase {

  /*
   * Create, edit, block, and cancel users.
   */
  public function testUser() {
    // user-create
    $sites = $this->setUpDrupal(1, TRUE);
    $root = $this->webroot();
    $name = "example";
    $options = array(
      'root' => $root,
      'uri' => key($sites),
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
    $this->assertEquals('authenticated user,administrator', $row[4], 'User has administrator role.');

    // user-remove-role
    $this->drush('user-remove-role', array('administrator', $name), $options);
    $this->drush('user-information', array($name), $options + array('pipe' => NULL));
    $output = $this->getOutput();
    $row  = str_getcsv($output);
    $this->assertEquals('authenticated user', $row[4], 'User removed administrator role.');

    // user-password
    $newpass = 'newpass';
    $this->drush('user-password', array($name), $options + array('password' => $newpass));
    $eval = "require_once DRUSH_DRUPAL_CORE . '/' . variable_get('password_inc', 'includes/password.inc');";
    $eval .= "\$account = user_load_by_name('example');";
    $eval .= "print (string) user_check_password('$newpass', \$account)";
    $this->drush('php-eval', array($eval), $options);
    $output = $this->getOutput();
    $this->assertEquals('1', $output, 'User can login with new password.');

    // user-login
    $user_login_options = $options + array('simulate' => TRUE, 'browser' => 'unish');
    // Collect full logs so we can check browser.
    $this->drush('user-login', array(), $user_login_options + array('backend' => NULL));
    $parsed = parse_backend_output($this->getOutput());
    $url = parse_url($parsed['output']);
    $this->assertStringStartsWith('/user/reset/1', $url['path'], 'Login returned a reset URL for uid 1 by default');
    $browser = FALSE;
    foreach ($parsed['log'] as $key => $log) {
      if (strpos($log['message'], 'Opening browser unish at http://dev/user/reset/1') === 0) {
        $browser = TRUE;
      }
    }
    $this->assertEquals($browser, TRUE, 'Correct browser opened at correct URL');
    // Check specific user and path argument.
    $this->drush('user-login', array($name, 'node/add'), $user_login_options);
    $output = $this->getOutput();
    $url = parse_url($output);
    $this->assertStringStartsWith('/user/reset/' . $uid, $url['path'], 'Login with user argument returned a valid reset URL');
    $this->assertEquals('destination=node/add', $url['query'], 'Login included destination path in URL');
    // Check path used as only argument when using uid option.
    $this->drush('user-login', array('node/add'), $user_login_options + array('uid' => $uid));
    $output = $this->getOutput();
    $url = parse_url($output);
    $this->assertStringStartsWith('/user/reset/' . $uid, $url['path'], 'Login with uid option returned a valid reset URL');
    $this->assertEquals('destination=node/add', $url['query'], 'Login included destination path in URL');

    // user-cancel
    // create content
    $this->drush('php-script', array('create_node_types'), $options + array('script-path' => dirname(__FILE__) . '/resources'));
    $this->drush('php-eval', array($eval), $options);
    $eval = "\$node = (object) array('title' => 'foo', 'uid' => 2, 'type' => 'page',); node_save(\$node);";
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
