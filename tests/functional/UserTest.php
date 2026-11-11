<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\UserCommands;
use Drush\Commands\user\UserLoginCommand;
use PHPUnit\Framework\Attributes\Group;

/**
 * Covers user:login, which spawns dr and so cannot run as a kernel test.
 * The rest of the user family is covered by tests/kernel/UserTest.php.
 */
#[Group('slow')]
#[Group('commands')]
class UserTest extends CommandUnishTestCase
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

    public function testUserLogin(): void
    {
        // Check user-login
        $user_login_options = ['simulate' => null];
        // Collect full logs.
        $this->drush(UserLoginCommand::NAME, [], $user_login_options + ['debug' => null]);
        $this->getErrorOutput();
        $url = parse_url($this->getOutput());
        $this->assertStringContainsString('/user/reset/1', $url['path'], 'Login returned a reset URL for uid 1 by default');
        // Check specific user with a path argument.
        $uid = 2;
        $this->drush(UserLoginCommand::NAME, ['node/add'], $user_login_options + ['name' => self::NAME]);
        $output = $this->getOutput();
        $url = parse_url($output);
        $query = $url['query'];
        $this->assertStringContainsString('/user/reset/' . $uid, $url['path'], 'Login with user argument returned a valid reset URL');
        $this->assertEquals('destination=node/add', $query, 'Login included destination path in URL');
        // Check path used as only argument when using uid option.
        $this->drush(UserLoginCommand::NAME, ['node/add'], $user_login_options + ['name' => self::NAME]);
        $output = $this->getOutput();
        $url = parse_url($output);
        $this->assertStringContainsString('/user/reset/' . $uid, $url['path'], 'Login with uid option returned a valid reset URL');
        $query = $url['query'];
        $this->assertEquals('destination=node/add', $query, 'Login included destination path in URL');
        // Test specific user by uid.
        $uid = 2;
        $this->drush(UserLoginCommand::NAME, [], $user_login_options + ['uid' => $uid]);
        $output = $this->getOutput();
        $url = parse_url($output);
        $this->assertStringContainsString('/user/reset/' . $uid, $url['path'], 'Login with uid option returned a valid reset URL');
        // Test specific user by mail.
        $uid = 2;
        $mail = self::MAIL;
        $this->drush(UserLoginCommand::NAME, [], $user_login_options + ['mail' => $mail]);
        $output = $this->getOutput();
        $url = parse_url($output);
        $this->assertStringContainsString('/user/reset/' . $uid, $url['path'], 'Login with mail option returned a valid reset URL');
        // A blocked account should produce a clear error, not a TypeError.
        $this->drush(UserCommands::BLOCK, [self::NAME]);
        $this->drush(UserLoginCommand::NAME, [], $user_login_options + ['name' => self::NAME], null, null, self::EXIT_ERROR);
        $this->assertStringContainsString('Account ' . self::NAME . ' is blocked and thus cannot login', $this->getErrorOutput());
        $this->drush(UserCommands::UNBLOCK, [self::NAME]);
    }

    public function userCreate(): void
    {
        $this->drush(UserCommands::CREATE, [self::NAME], ['password' => 'password', 'mail' => self::MAIL]);
        $this->drush(UserCommands::INFORMATION, [self::NAME], ['format' => 'json']);
        $uid = 2;
        $output = $this->getOutputFromJSON($uid);
        $this->assertEquals(self::MAIL, $output['mail']);
        $this->assertEquals(self::NAME, $output['name']);
        $this->assertEquals(1, $output['user_status'], 'Newly created user is Active.');
        $expected = ['authenticated'];
        $this->assertEquals($expected, array_values($output['roles']), 'Newly created user has one role.');
    }
}
