<?php

namespace Unish;

/**
 *  @group slow
 *  @group commands
 */
class userCase extends CommandUnishTestCase {

  const NAME = 'example';
  private static $authenticated;
  private static $status_prop;

  function setUp() {
    if (!$this->getSites()) {
      $this->setUpDrupal(1, TRUE);
      self::$authenticated = 'authenticated';
      self::$status_prop = 'status';
      if (UNISH_DRUPAL_MAJOR_VERSION < 8) {
        self::$authenticated .= ' user';
      }
      else {
        self::$status_prop = 'user_status';
      }

      $this->userCreate();
    }
  }

  function testBlockUnblock() {
    $this->drush('user-block', array(self::NAME), $this->options());
    $this->drush('user-information', array(self::NAME), $this->options() + array('format' => 'json'));
    $uid = UNISH_DRUPAL_MAJOR_VERSION == 6 ? 3 : 2;
    $output = $this->getOutputFromJSON($uid);
    $this->assertEquals(0, $output->{self::$status_prop}, 'User is blocked.');

    // user-unblock
    $this->drush('user-unblock', array(self::NAME), $this->options());
    $this->drush('user-information', array(self::NAME), $this->options() + array('format' => 'json'));
    $output = $this->getOutputFromJSON($uid);
    $this->assertEquals(1, $output->{self::$status_prop}, 'User is unblocked.');
  }

   function testUserRole() {
    // First, create the role since we use testing install profile.
    $this->drush('role-create', array('test role'), $this->options());
    $this->drush('user-add-role', array('test role', self::NAME), $this->options());
    $this->drush('user-information', array(self::NAME), $this->options() + array('format' => 'json'));
     $uid = UNISH_DRUPAL_MAJOR_VERSION == 6 ? 3 : 2;
     $output = $this->getOutputFromJSON($uid);
    $expected = array(self::$authenticated, 'test role');
    $this->assertEquals($expected, array_values((array)$output->roles), 'User has test role.');

    // user-remove-role
    $this->drush('user-remove-role', array('test role', self::NAME), $this->options());
    $this->drush('user-information', array(self::NAME), $this->options() + array('format' => 'json'));
    $output = $this->getOutputFromJSON($uid);
    $expected = array(self::$authenticated);
    $this->assertEquals($expected, array_values((array)$output->roles), 'User removed test role.');
  }

  function testUserPassword() {
    $newpass = 'newpass';
    $name = self::NAME;
    $this->drush('user-password', array(self::NAME), $this->options() + array('password' => $newpass));
    // user_authenticate() is more complex in D6 so skip it.
    switch (UNISH_DRUPAL_MAJOR_VERSION) {
      case 6:
        $this->markTestSkipped('Drupal 6 authentication too complex for testing.');
        break;
      case 7:
        $eval = "return user_authenticate('$name', '$newpass')";
        break;
      case 8:
      case 9:
        $eval = "return \\Drupal::service('user.auth')->authenticate('$name', '$newpass');";
        break;
    }
    $this->drush('php-eval', array($eval), $this->options());
    $output = $this->getOutput();
    $this->assertEquals("'2'", $output, 'User can login with new password.');
  }

   function testUserLogin() {
    // Check if user-login on non-bootstrapped environment returns error.
    $this->drush('user-login', array(), array(), NULL, NULL, self::EXIT_ERROR);

    // Check user-login
    $user_login_options = $this->options() + array('simulate' => TRUE, 'browser' => 'unish');
    // Collect full logs so we can check browser.
    $this->drush('user-login', array(), $user_login_options + array('backend' => NULL));
    $parsed = $this->parse_backend_output($this->getOutput());
    $url = parse_url($parsed['output']);
    $this->assertContains('/user/reset/1', $url['path'], 'Login returned a reset URL for uid 1 by default');
    $browser = FALSE;
    foreach ($parsed['log'] as $key => $log) {
      // Regarding 'strip_tags', see https://github.com/drush-ops/drush/issues/1637
      if (strpos(strip_tags($log['message']), 'Opening browser unish at http://') === 0) {
        $browser = TRUE;
      }
    }
    $this->assertEquals($browser, TRUE, 'Correct browser opened at correct URL');
    // Check specific user and path argument.
     $uid = UNISH_DRUPAL_MAJOR_VERSION == 6 ? 3 : 2;
    $this->drush('user-login', array(self::NAME, 'node/add'), $user_login_options);
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
  }

  function testUserCancel() {
    // create content
    // @todo Creation of node types and content has changed in D8.
    if (UNISH_DRUPAL_MAJOR_VERSION >= 8) {
      $this->markTestSkipped("@todo Creation of node types and content has changed in D8. Started to fix this");
    }
    if (UNISH_DRUPAL_MAJOR_VERSION >= 7) {
      // create_node_types script does not work for D6
      $this->drush('php-script', array('create_node_types'), $this->options() + array('script-path' => dirname(__FILE__) . '/resources'));
      $name = self::NAME;
      $newpass = 'newpass';
      $eval = "return user_authenticate('$name', '$newpass')";
      $this->drush('php-eval', array($eval), $this->options());
      $eval = "\$node = (object) array('title' => 'foo', 'uid' => 2, 'type' => 'page',);";
      if (UNISH_DRUPAL_MAJOR_VERSION >= 8) {
        $eval .= " \$node = node_submit(entity_create('node', \$node));";
      }
      if (UNISH_DRUPAL_MAJOR_VERSION >= 9) {
        $eval .= " \\Drupal::entityTypeManager()->getStorage('node')->create(\$node)->save();";
      }
      else {
        $eval .= " node_save(\$node);";
      }
      $this->drush('php-eval', array($eval), $this->options());
      $this->drush('user-cancel', array(self::NAME), $this->options() + array('delete-content' => NULL));
      $eval = 'print (string) user_load(2)';
      $this->drush('php-eval', array($eval), $this->options());
      $output = $this->getOutput();
      $this->assertEmpty($output, 'User was deleted');
      $eval = 'print (string) node_load(2)';
      $this->drush('php-eval', array($eval), $this->options());
      $output = $this->getOutput();
      $this->assertEmpty($output, 'Content was deleted');
    }
  }

  function UserCreate() {
    $this->drush('user-create', array(self::NAME), $this->options() + array('password' => 'password', 'mail' => "example@example.com"));
    $this->drush('user-information', array(self::NAME), $this->options() + array('format' => 'json'));
    $uid = UNISH_DRUPAL_MAJOR_VERSION == 6 ? 3 : 2;
    $output = $this->getOutputFromJSON($uid);
    $this->assertEquals('example@example.com', $output->mail);
    $this->assertEquals(self::NAME, $output->name);
    $this->assertEquals(1, $output->{self::$status_prop}, 'Newly created user is Active.');
    $expected = array(self::$authenticated);
    $this->assertEquals($expected, array_values((array)$output->roles), 'Newly created user has one role.');
  }

  function options() {
    return array(
      'root' => $this->webroot(),
      'uri' => key($this->getSites()),
      'yes' => NULL,
    );
  }
}
