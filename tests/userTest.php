<?php

/**
 * @file
 *   Tests for user.drush.inc
 */

/**
 *  @group slow
 *  @group commands
 */
class userCase extends Drush_CommandTestCase {

  /**
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
    $authenticated = 'authenticated';
    if (UNISH_DRUPAL_MAJOR_VERSION < 8) {
      $authenticated .= ' user';
    }
    $this->drush('user-create', array($name), $options + array('password' => 'password', 'mail' => "example@example.com"));
    $this->drush('user-information', array($name), $options + array('format' => 'json'));
    $output = $this->getOutputFromJSON('2');
    $this->assertEquals('example@example.com', $output->mail);
    $this->assertEquals($name, $output->name);
    $this->assertEquals(1, $output->status, 'Newly created user is Active.');
    $obj_authenticated = (object) array(2 => $authenticated);
    $this->assertEquals(json_encode($obj_authenticated), json_encode($output->roles), 'Newly created user has one role.');

    // user-block
    $this->drush('user-block', array($name), $options);
    $this->drush('user-information', array($name), $options + array('format' => 'json'));
    $output = $this->getOutputFromJSON('2');
    $this->assertEquals(0, $output->status, 'User is blocked.');

    // user-unblock
    $this->drush('user-unblock', array($name), $options);
    $this->drush('user-information', array($name), $options + array('format' => 'json'));
    $output = $this->getOutputFromJSON('2');
    $this->assertEquals(1, $output->status, 'User is unblocked.');

    // user-add-role
    // first, create the fole since we use testing install profile.
    $this->drush('role-create', array('test role'), $options);
    $this->drush('user-add-role', array('test role', $name), $options);
    $this->drush('user-information', array($name), $options + array('format' => 'json'));
    $output = $this->getOutputFromJSON('2');
    $expected = (object) array(2 => $authenticated, 3 => 'test role');
    $this->assertEquals(json_encode($expected), json_encode($output->roles), 'User has test role.');

    // user-remove-role
    $this->drush('user-remove-role', array('test role', $name), $options);
    $this->drush('user-information', array($name), $options + array('format' => 'json'));
    $output = $this->getOutputFromJSON('2');
    $this->assertEquals(json_encode($obj_authenticated), json_encode($output->roles), 'User removed test role.');

    // user-password
    $newpass = 'newpass';
    $this->drush('user-password', array($name), $options + array('password' => $newpass));
    // There is no user_check_password in D6
    if (UNISH_DRUPAL_MAJOR_VERSION >= 7) {
      $eval = "require_once DRUSH_DRUPAL_CORE . '/' . variable_get('password_inc', 'includes/password.inc');";
      $eval .= "\$account = user_load_by_name('example');";
      $eval .= "print (string) user_check_password('$newpass', \$account)";
      $this->drush('php-eval', array($eval), $options);
      $output = $this->getOutput();
      $this->assertEquals('1', $output, 'User can login with new password.');
    }

    // user-login
    // Check if user-login on non-bootstrapped environment returns error.
    $this->drush('user-login', array(), array(), NULL, NULL, self::EXIT_ERROR);

    // Check user-login
    $user_login_options = $options + array('simulate' => TRUE, 'browser' => 'unish');
    // Collect full logs so we can check browser.
    $this->drush('user-login', array(), $user_login_options + array('backend' => NULL));
    $parsed = parse_backend_output($this->getOutput());
    $url = parse_url($parsed['output']);
    $this->assertContains('/user/reset/1', $url['path'], 'Login returned a reset URL for uid 1 by default');
    $browser = FALSE;
    foreach ($parsed['log'] as $key => $log) {
      if (strpos($log['message'], 'Opening browser unish at http://') === 0) {
        $browser = TRUE;
      }
    }
    $this->assertEquals($browser, TRUE, 'Correct browser opened at correct URL');
    // Check specific user and path argument.
    $uid = 2;
    $this->drush('user-login', array($name, 'node/add'), $user_login_options);
    $output = $this->getOutput();
    $url = parse_url($output);
    // user_pass_reset_url encodes the URL in D6, but not in D7 or D8
    $query = $url['query'];
    if (UNISH_DRUPAL_MAJOR_VERSION < 7) {
      $query = urldecode($query);
    }
    $this->assertContains('/user/reset/' . $uid, $url['path'], 'Login with user argument returned a valid reset URL');
    $this->assertEquals('destination=node/add', $query, 'Login included destination path in URL');
    // Check path used as only argument when using uid option.
    $this->drush('user-login', array('node/add'), $user_login_options + array('uid' => $uid));
    $output = $this->getOutput();
    $url = parse_url($output);
    $this->assertContains('/user/reset/' . $uid, $url['path'], 'Login with uid option returned a valid reset URL');
    $query = $url['query'];
    if (UNISH_DRUPAL_MAJOR_VERSION < 7) {
      $query = urldecode($query);
    }
    $this->assertEquals('destination=node/add', $query, 'Login included destination path in URL');

    // user-cancel
    // create content
    if (UNISH_DRUPAL_MAJOR_VERSION >= 7) {
      // create_node_types script does not work for D6
      $this->drush('php-script', array('create_node_types'), $options + array('script-path' => dirname(__FILE__) . '/resources'));
      $this->drush('php-eval', array($eval), $options);
      $eval = "\$node = (object) array('title' => 'foo', 'uid' => 2, 'type' => 'page',);";
      if (UNISH_DRUPAL_MAJOR_VERSION >= 8) {
        $eval .= " \$node = node_submit(entity_create('node', \$node));";
      }
      $eval .= " node_save(\$node);";
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
}
