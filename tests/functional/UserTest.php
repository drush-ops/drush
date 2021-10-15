<?php

namespace Unish;

use Webmozart\PathUtil\Path;

/**
 *  @group slow
 *  @group commands
 */
class UserCase extends CommandUnishTestCase
{

    const NAME = 'example';

    public function setup(): void
    {
        if (!$this->getSites()) {
            $this->setUpDrupal(1, true);
            $this->userCreate();
        }
    }

    public function testBlockUnblock()
    {
        $this->drush('user-block', [self::NAME]);
        $this->drush('user-information', [self::NAME], ['format' => 'json']);
        $uid = 2;
        $output = $this->getOutputFromJSON($uid);
        $this->assertEquals(0, $output['user_status'], 'User is blocked.');

        // user-unblock
        $this->drush('user-unblock', [self::NAME]);
        $this->drush('user-information', [self::NAME], ['format' => 'json']);
        $output = $this->getOutputFromJSON($uid);
        $this->assertEquals(1, $output['user_status'], 'User is unblocked.');
    }

    public function testUserRole()
    {
        // First, create the role since we use testing install profile.
        $this->drush('role-create', ['test role']);
        $this->drush('user-add-role', ['test role', self::NAME]);
        $this->drush('user-information', [self::NAME], ['format' => 'json']);
        $uid = 2;
        $output = $this->getOutputFromJSON($uid);
        $expected = ['authenticated', 'test role'];
        $this->assertEquals($expected, array_values($output['roles']), 'User has test role.');

        // user-remove-role
        $this->drush('user-remove-role', ['test role', self::NAME]);
        $this->drush('user-information', [self::NAME], ['format' => 'json']);
        $output = $this->getOutputFromJSON($uid);
        $expected = ['authenticated'];
        $this->assertEquals($expected, array_values($output['roles']), 'User removed test role.');
    }

    public function testUserPassword()
    {
        $newpass = 'newpass';
        $name = self::NAME;
        $this->drush('user:password', [self::NAME, $newpass]);
        $eval = "return Drupal::service(\"user.auth\")->authenticate(\"$name\", \"$newpass\");";
        $this->drush('php:eval', [$eval]);
        $output = $this->getOutput();
        $this->assertEquals("2", $output, 'User can login with new password.');
    }

    public function testUserLoginNoBootstrappedSite()
    {
        $this->markTestSkipped('TODO: @none should prevent selection of site at cwd');
        // Check if user-login on a non-bootstrapped environment returns error.
        $this->drush('user-login', [], [], '@none', null, self::EXIT_ERROR);
    }

    public function testUserLogin()
    {
        // Check user-login
        $user_login_options = ['simulate' => null, 'browser' => 'unish'];
        // Collect full logs so we can check browser.
        $this->drush('user-login', [], $user_login_options + ['debug' => null]);
        $logOutput = $this->getErrorOutput();
        $url = parse_url($this->getOutput());
        $this->assertStringContainsString('/user/reset/1', $url['path'], 'Login returned a reset URL for uid 1 by default');
        $this->assertStringContainsString('Opening browser unish at http://', $logOutput);
        // Check specific user with a path argument.
        $uid = 2;
        $this->drush('user-login', ['node/add'], $user_login_options + ['name' => self::NAME]);
        $output = $this->getOutput();
        $url = parse_url($output);
        $query = $url['query'];
        $this->assertStringContainsString('/user/reset/' . $uid, $url['path'], 'Login with user argument returned a valid reset URL');
        $this->assertEquals('destination=node/add', $query, 'Login included destination path in URL');
        // Check path used as only argument when using uid option.
        $this->drush('user-login', ['node/add'], $user_login_options + ['name' => self::NAME]);
        $output = $this->getOutput();
        $url = parse_url($output);
        $this->assertStringContainsString('/user/reset/' . $uid, $url['path'], 'Login with uid option returned a valid reset URL');
        $query = $url['query'];
        $this->assertEquals('destination=node/add', $query, 'Login included destination path in URL');
        // Test specific user by uid.
        $uid = 2;
        $this->drush('user-login', [], $user_login_options + ['uid' => $uid]);
        $output = $this->getOutput();
        $url = parse_url($output);
        $this->assertStringContainsString('/user/reset/' . $uid, $url['path'], 'Login with uid option returned a valid reset URL');
        // Test specific user by mail.
        $uid = 2;
        $mail = 'example@example.com';
        $this->drush('user-login', [], $user_login_options + ['mail' => $mail]);
        $output = $this->getOutput();
        $url = parse_url($output);
        $this->assertStringContainsString('/user/reset/' . $uid, $url['path'], 'Login with mail option returned a valid reset URL');
    }

    public function testUserCancel()
    {
        $answers = [
            'name' => 'Unish Article',
            'machine_name' => 'unish_article',
            'description' => 'A test module',
            'package' => 'unish',
            'dependencies' => 'drupal:text',
        ];
        $this->drush('generate', ['module'], ['v' => null, 'answer' => $answers, 'destination' => Path::join(self::webroot(), 'modules/contrib')], null, null, self::EXIT_SUCCESS, null, ['SHELL_INTERACTIVE' => 1]);
        // Create a content entity type and enable its module.
        // Note that only the values below are used. The keys are for documentation.
        $answers = [
            'name' => 'unish_article',
            'entity_type_label' => 'UnishArticle',
            'entity_type_id' => 'unish_article',
            'entity_base_path' => 'admin/content/unish_article',
            'fieldable' => 'no',
            'revisionable' => 'no',
            'translatable' => 'no',
            'bundle' => 'No',
            'canonical page' => 'No',
            'entity template' => 'No',
            'CRUD permissions' => 'No',
            'label base field' => 'Yes',
            'status_base_field' => 'yes',
            'created_base_field' => 'yes',
            'changed_base_field' => 'yes',
            'author_base_field' => 'yes',
            'description_base_field' => 'no',
            'rest_configuration' => 'no',
        ];
        $this->drush('generate', ['content-entity'], ['answer' => $answers, 'destination' => Path::join(self::webroot(), 'modules/contrib/unish_article')], null, null, self::EXIT_SUCCESS, null, ['SHELL_INTERACTIVE' => 1]);
        $this->drush('pm-enable', ['text,unish_article']);
        // Create one unish_article owned by our example user.
        $this->drush('php-script', ['create_unish_articles'], ['script-path' => Path::join(__DIR__, 'resources')]);
        // Verify that content entity exists.
        $code = "echo Drupal::entityTypeManager()->getStorage('unish_article')->load(1)->id()";
        $this->drush('php-eval', [$code]);
        $this->assertEquals(1, $this->getOutput());

        // Cancel user and verify that the account is deleted.
        $this->drush('user-cancel', [self::NAME], ['delete-content' => null]);
        $this->drush('user-information', [self::NAME], ['fields' => 'user_status', 'format' => 'string'], null, null, self::EXIT_ERROR);

        // Verify that the content is deleted.
        // Sigh - only nodes actually honor the cancellation methods. @see node_user_cancel().
        // $this->drush('php-eval', [$code], [], NULL, NULL, self::EXIT_ERROR);
        // $output = $this->getOutput();
        // $this->assertEquals('', $this->getOutput());
    }

    public function userCreate()
    {
        $this->drush('user-create', [self::NAME], ['password' => 'password', 'mail' => "example@example.com"]);
        $this->drush('user-information', [self::NAME], ['format' => 'json']);
        $uid = 2;
        $output = $this->getOutputFromJSON($uid);
        $this->assertEquals('example@example.com', $output['mail']);
        $this->assertEquals(self::NAME, $output['name']);
        $this->assertEquals(1, $output['user_status'], 'Newly created user is Active.');
        $expected = ['authenticated'];
        $this->assertEquals($expected, array_values($output['roles']), 'Newly created user has one role.');
    }
}
