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
    const MAIL = 'example@example.com';

    public function setup(): void
    {
        if (!$this->getSites()) {
            $this->setUpDrupal(1, true);
            $this->userCreate();
        }
    }

    public function testBlockUnblock()
    {
        $uid = 2;

        $this->drush('user-block', [self::NAME]);
        $this->drush('user-information', [self::NAME], ['format' => 'json']);
        $output = $this->getOutputFromJSON($uid);
        $this->assertEquals(0, $output['user_status'], 'User is blocked.');

        // user-unblock
        $this->drush('user-unblock', [self::NAME]);
        $this->drush('user-information', [self::NAME], ['format' => 'json']);
        $output = $this->getOutputFromJSON($uid);
        $this->assertEquals(1, $output['user_status'], 'User is unblocked.');

        // user-block user by uid.
        $this->drush('user-block', [], ['uid' => $uid]);
        $this->drush('user-information', [self::NAME], ['format' => 'json']);
        $output = $this->getOutputFromJSON($uid);
        $this->assertEquals(0, $output['user_status'], 'User (id) is blocked.');

        $this->drush('user-unblock', [], ['uid' => $uid]);
        $this->drush('user-information', [self::NAME], ['format' => 'json']);
        $output = $this->getOutputFromJSON($uid);
        $this->assertEquals(1, $output['user_status'], 'User (id) is unblocked.');


        // user-block user by mail.
        $this->drush('user-block', [], ['mail' => self::MAIL]);
        $this->drush('user-information', [self::NAME], ['format' => 'json']);
        $output = $this->getOutputFromJSON($uid);
        $this->assertEquals(0, $output['user_status'], 'User (mail) is blocked.');

        $this->drush('user-unblock', [], ['uid' => $uid]);
        $this->drush('user-information', [self::NAME], ['format' => 'json']);
        $output = $this->getOutputFromJSON($uid);
        $this->assertEquals(1, $output['user_status'], 'User (mail) is unblocked.');
    }

    public function testUserRole()
    {
        $uid = 2;
        // First, create the role since we use testing install profile.
        $this->drush('role-create', ['test role']);
        $this->drush('user-add-role', ['test role', self::NAME]);
        $this->drush('user-information', [self::NAME], ['format' => 'json']);
        $output = $this->getOutputFromJSON($uid);
        $expected = ['authenticated', 'test role'];
        $this->assertEquals($expected, array_values($output['roles']), 'User has test role.');

        // user-remove-role
        $this->drush('user-remove-role', ['test role', self::NAME]);
        $this->drush('user-information', [self::NAME], ['format' => 'json']);
        $output = $this->getOutputFromJSON($uid);
        $expected = ['authenticated'];
        $this->assertEquals($expected, array_values($output['roles']), 'User removed test role.');

        // user-add-role by uid.
        $this->drush('user-add-role', ['test role'], ['uid' => $uid]);
        $this->drush('user-information', [self::NAME], ['format' => 'json']);
        $output = $this->getOutputFromJSON($uid);
        $expected = ['authenticated', 'test role'];
        $this->assertEquals($expected, array_values($output['roles']), 'User (id) has test role.');

        $this->drush('user-remove-role', ['test role'], ['uid' => $uid]);
        $this->drush('user-information', [self::NAME], ['format' => 'json']);
        $output = $this->getOutputFromJSON($uid);
        $expected = ['authenticated'];
        $this->assertEquals($expected, array_values($output['roles']), 'User (id) removed test role.');

        // user-add-role by mail.
        $this->drush('user-add-role', ['test role'], ['mail' => self::MAIL]);
        $this->drush('user-information', [self::NAME], ['format' => 'json']);
        $output = $this->getOutputFromJSON($uid);
        $expected = ['authenticated', 'test role'];
        $this->assertEquals($expected, array_values($output['roles']), 'User (mail) has test role.');

        $this->drush('user-remove-role', ['test role'], ['mail' => self::MAIL]);
        $this->drush('user-information', [self::NAME], ['format' => 'json']);
        $output = $this->getOutputFromJSON($uid);
        $expected = ['authenticated'];
        $this->assertEquals($expected, array_values($output['roles']), 'User (mail) removed test role.');
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
        $mail = self::MAIL;
        $this->drush('user-login', [], $user_login_options + ['mail' => $mail]);
        $output = $this->getOutput();
        $url = parse_url($output);
        $this->assertStringContainsString('/user/reset/' . $uid, $url['path'], 'Login with mail option returned a valid reset URL');
    }

    public function testUserCancel()
    {
        CreateEntityType::createContentEntity($this);
        $this->drush('pm-enable', ['text,unish_article']);
        $this->drush('php:script', ['create_unish_article_bundles'], ['script-path' => Path::join(__DIR__, 'resources')]);
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
        $this->drush('user-create', [self::NAME], ['password' => 'password', 'mail' => self::MAIL]);
        $this->drush('user-information', [self::NAME], ['format' => 'json']);
        $uid = 2;
        $output = $this->getOutputFromJSON($uid);
        $this->assertEquals(self::MAIL, $output['mail']);
        $this->assertEquals(self::NAME, $output['name']);
        $this->assertEquals(1, $output['user_status'], 'Newly created user is Active.');
        $expected = ['authenticated'];
        $this->assertEquals($expected, array_values($output['roles']), 'Newly created user has one role.');
    }
}
