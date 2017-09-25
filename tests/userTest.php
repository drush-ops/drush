<?php

namespace Unish;

use Webmozart\PathUtil\Path;

/**
 *  @group slow
 *  @group commands
 */
class userCase extends CommandUnishTestCase {

  const NAME = 'example';

  function setUp() {
    if (!$this->getSites()) {
      $this->setUpDrupal(1, TRUE);
      $this->userCreate();
    }
  }

  function testBlockUnblock() {
    $this->drush('user-block', array(self::NAME), $this->options());
    $this->drush('user-information', array(self::NAME), $this->options() + array('format' => 'json'));
    $uid = 2;
    $output = $this->getOutputFromJSON($uid);
    $this->assertEquals(0, $output->user_status, 'User is blocked.');

    // user-unblock
    $this->drush('user-unblock', array(self::NAME), $this->options());
    $this->drush('user-information', array(self::NAME), $this->options() + array('format' => 'json'));
    $output = $this->getOutputFromJSON($uid);
    $this->assertEquals(1, $output->user_status, 'User is unblocked.');
  }

   function testUserRole() {
    // First, create the role since we use testing install profile.
    $this->drush('role-create', array('test role'), $this->options());
    $this->drush('user-add-role', array('test role', self::NAME), $this->options());
    $this->drush('user-information', array(self::NAME), $this->options() + array('format' => 'json'));
    $uid = 2;
    $output = $this->getOutputFromJSON($uid);
    $expected = array('authenticated', 'test role');
    $this->assertEquals($expected, array_values((array)$output->roles), 'User has test role.');

    // user-remove-role
    $this->drush('user-remove-role', array('test role', self::NAME), $this->options());
    $this->drush('user-information', array(self::NAME), $this->options() + array('format' => 'json'));
    $output = $this->getOutputFromJSON($uid);
    $expected = array('authenticated');
    $this->assertEquals($expected, array_values((array)$output->roles), 'User removed test role.');
  }

  function testUserPassword() {
    $newpass = 'newpass';
    $name = self::NAME;
    $this->drush('user-password', array(self::NAME, $newpass), $this->options());
    $eval = "return \\Drupal::service('user.auth')->authenticate('$name', '$newpass');";
    $this->drush('php-eval', array($eval), $this->options());
    $output = $this->getOutput();
    $this->assertEquals("2", $output, 'User can login with new password.');
  }

   function testUserLogin() {
    // Check if user-login on non-bootstrapped environment returns error.
    $this->drush('user-login', array(), array(), NULL, NULL, self::EXIT_ERROR);

    // Check user-login
    $user_login_options = $this->options() + array('simulate' => null, 'browser' => 'unish');
    // Collect full logs so we can check browser.
    $this->drush('user-login', array(), $user_login_options + ['debug' => null]);
    $logOutput = $this->getErrorOutput();
    $url = parse_url($this->getOutput());
    $this->assertContains('/user/reset/1', $url['path'], 'Login returned a reset URL for uid 1 by default');
    $this->assertContains('Opening browser unish at http://', $logOutput);
    // Check specific user with a path argument.
    $uid = 2;
    $this->drush('user-login', array('node/add'), $user_login_options + ['name' => self::NAME]);
    $output = $this->getOutput();
    $url = parse_url($output);
    $query = $url['query'];
    $this->assertContains('/user/reset/' . $uid, $url['path'], 'Login with user argument returned a valid reset URL');
    $this->assertEquals('destination=node/add', $query, 'Login included destination path in URL');
    // Check path used as only argument when using uid option.
    $this->drush('user-login', array('node/add'), $user_login_options + ['name' => self::NAME]);
    $output = $this->getOutput();
    $url = parse_url($output);
    $this->assertContains('/user/reset/' . $uid, $url['path'], 'Login with uid option returned a valid reset URL');
    $query = $url['query'];
    $this->assertEquals('destination=node/add', $query, 'Login included destination path in URL');
  }

  function testUserCancel() {
    // Create a content entity type and enable its module.
    $answers = [
      'name' => 'UnishArticle',
      'machine_name' => 'unish_article',
      'package' => 'custom',
      'version' => '8.x-1.0-dev',
      'dependencies' => 'text',
      'entity_type_label' => 'UnishArticle',
      'entity_type_id' => 'unish_article',
      'entity_base_path' => 'admin/content/unish_article',
      'fieldable' => 'no',
      'revisionable' => 'no',
      'template' => 'no',
      'access_controller' => 'no',
      'title_base_field' => 'yes',
      'status_base_field' => 'yes',
      'created_base_field' => 'yes',
      'changed_base_field' => 'yes',
      'author_base_field' => 'yes',
      'description_base_field' => 'no',
      'rest_configuration' => 'no',
    ];
    $answers = json_encode($answers);
    $original = getenv('SHELL_INTERACTIVE');
    putenv('SHELL_INTERACTIVE=1');
    $this->drush('generate', ['content-entity'], $this->options() + ['answers' => $answers, 'directory' => Path::join(self::webroot(), 'modules/unish')]);
    putenv('SHELL_INTERACTIVE=' . $original);
    $this->drush('pm-enable', ['text,unish_article'], $this->options());
    // Create one unish_article owned by our example user.
    $this->drush('php-script', ['create_unish_articles'], $this->options() + ['script-path' => '../vendor/drush/drush/tests/resources']);
    // Verify that content entity exists.
    $code = "echo entity_load('unish_article', 1)->id()";
    $this->drush('php-eval', [$code], $this->options());
    $this->assertEquals(1, $this->getOutput());

    // Cancel user and verify that the account is deleted.
    $this->drush('user-cancel', array(self::NAME), $this->options() + array('delete-content' => NULL));
    $this->drush('user-information', [self::NAME], $this->options() + ['fields' => 'user_status', 'format' => 'string'], NULL, NULL, self::EXIT_ERROR);

    // Verify that the content is deleted.
    // Sigh - only nodes actually honor the cancellation methods. @see node_user_cancel().
    // $this->drush('php-eval', [$code], $this->options(), NULL, NULL, self::EXIT_ERROR);
    // $output = $this->getOutput();
    // $this->assertEquals('', $this->getOutput());
  }

  function UserCreate() {
    $this->drush('user-create', array(self::NAME), $this->options() + array('password' => 'password', 'mail' => "example@example.com"));
    $this->drush('user-information', array(self::NAME), $this->options() + array('format' => 'json'));
    $uid = 2;
    $output = $this->getOutputFromJSON($uid);
    $this->assertEquals('example@example.com', $output->mail);
    $this->assertEquals(self::NAME, $output->name);
    $this->assertEquals(1, $output->user_status, 'Newly created user is Active.');
    $expected = array('authenticated');
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
