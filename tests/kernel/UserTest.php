<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\UserInterface;
use Drush\Boot\BootstrapManager;
use Drush\Config\DrushConfig;
use Drush\Commands\user\UserBlockCommand;
use Drush\Commands\user\UserCancelCommand;
use Drush\Commands\user\UserCreateCommand;
use Drush\Commands\user\UserInformationCommand;
use Drush\Commands\user\UserLoginCommand;
use Drush\Commands\user\UserPasswordCommand;
use Drush\Commands\user\UserRoleAddCommand;
use Drush\Commands\user\UserRoleRemoveCommand;
use Drush\Commands\user\UserUnblockCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the user commands: each command is built from container services
 * and driven with a CommandTester.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(UserBlockCommand::class)]
#[CoversClass(UserCancelCommand::class)]
#[CoversClass(UserCreateCommand::class)]
#[CoversClass(UserInformationCommand::class)]
#[CoversClass(UserLoginCommand::class)]
#[CoversClass(UserPasswordCommand::class)]
#[CoversClass(UserRoleAddCommand::class)]
#[CoversClass(UserRoleRemoveCommand::class)]
#[CoversClass(UserUnblockCommand::class)]
class UserTest extends KernelTestBase
{
    protected static $modules = ['system', 'user'];

    const NAME = 'example';
    const MAIL = 'example@example.com';
    const PASSWORD = 'password';

    protected EntityTypeManagerInterface $entityTypeManager;
    protected DateFormatterInterface $dateFormatter;
    protected LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installEntitySchema('user');
        $this->installSchema('user', ['users_data']);
        $this->installConfig(['system', 'user']);
        $this->entityTypeManager = $this->container->get('entity_type.manager');
        $this->dateFormatter = $this->container->get('date.formatter');
        $this->logger = $this->container->get('logger.channel.default');
        // Occupy uid 1 so the example account gets a regular uid, as it would
        // on an installed site.
        $this->entityTypeManager->getStorage('user')->create(['name' => 'admin', 'status' => 1])->save();
    }

    public function testCreate(): void
    {
        $tester = new CommandTester(new UserCreateCommand($this->entityTypeManager, $this->logger));
        $this->assertSame(Command::SUCCESS, $tester->execute(['name' => self::NAME, '--mail' => self::MAIL, '--password' => self::PASSWORD]));
        $this->assertStringContainsString('Created a new user with uid 2', $tester->getDisplay());
        $info = $this->informationJson(['names' => self::NAME])[2];
        $this->assertSame(self::NAME, $info['name']);
        $this->assertSame(self::MAIL, $info['mail']);
        $this->assertEquals(1, $info['user_status']);
        $this->assertSame(['authenticated'], $info['roles']);
        $this->assertTrue($this->container->get('password')->check(self::PASSWORD, $info['pass']));

        // Duplicate names and mails are rejected.
        try {
            $tester->execute(['name' => self::NAME]);
            $this->fail('A duplicate user name was accepted.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('There is already a user account with the name example', $e->getMessage());
        }
        try {
            $tester->execute(['name' => 'another', '--mail' => self::MAIL]);
            $this->fail('A duplicate mail was accepted.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('There is already a user account with the email ' . self::MAIL, $e->getMessage());
        }
    }

    public function testInformation(): void
    {
        $account = $this->createExampleUser();
        $uid = (int) $account->id();

        // By name, as a table.
        $display = $this->information([self::NAME])->getDisplay();
        $this->assertMatchesRegularExpression('/User ID\s+\|\s+User name\s+\|\s+User mail\s+\|\s+User roles\s+\|\s+User status/', $display);
        $this->assertMatchesRegularExpression('/' . $uid . '\s+\|\s+example\s+\|\s+example@example.com\s+\|\s+authenticated\s+\|\s+1/', $display);

        // By uid and by mail, as json; the record carries every field.
        $info = $this->informationJson(['--uid' => (string) $uid])[$uid];
        $this->assertSame(self::NAME, $info['name']);
        $this->assertSame(self::MAIL, $info['mail']);
        $this->assertSame(array_keys($info), array_keys($this->informationJson(['--mail' => self::MAIL])[$uid]));
        $this->assertSame(['uid', 'name', 'pass', 'mail', 'user_created', 'created', 'user_access', 'access', 'user_login', 'login', 'user_status', 'status', 'timezone', 'roles', 'langcode', 'uuid'], array_keys($info));

        // yaml matches json, and several selectors merge by uid.
        $yaml = Yaml::decode($this->information(['names' => self::NAME, '--uid' => '1', '--format' => 'yaml'])->getDisplay());
        $this->assertSame([1, $uid], array_keys($yaml));
        $this->assertSame($info, $yaml[$uid]);

        // --fields narrows the table and the json alike.
        $display = $this->information(['names' => self::NAME, '--fields' => 'name,status'])->getDisplay();
        $this->assertMatchesRegularExpression('/User name\s+\|\s+Status/', $display);
        $this->assertStringNotContainsString('User mail', $display);
        $this->assertSame(['name' => self::NAME, 'status' => 'active'], $this->informationJson(['names' => self::NAME, '--fields' => 'name,status'])[$uid]);
        try {
            $this->information(['names' => self::NAME, '--fields' => 'name,nope']);
            $this->fail('An unknown field was accepted.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Unknown field(s): nope', $e->getMessage());
        }
    }

    public function testBlockUnblock(): void
    {
        $uid = (int) $this->createExampleUser()->id();

        $tester = new CommandTester(new UserBlockCommand($this->entityTypeManager, $this->logger));
        $this->assertSame(Command::SUCCESS, $tester->execute(['names' => self::NAME]));
        $this->assertStringContainsString('Blocked user(s): example', $tester->getDisplay());
        $this->assertEquals(0, $this->informationJson(['names' => self::NAME])[$uid]['user_status']);

        $tester = new CommandTester(new UserUnblockCommand($this->entityTypeManager, $this->logger));
        $this->assertSame(Command::SUCCESS, $tester->execute(['--uid' => (string) $uid]));
        $this->assertStringContainsString('Unblocked user(s): example', $tester->getDisplay());
        $this->assertEquals(1, $this->informationJson(['names' => self::NAME])[$uid]['user_status']);

        $tester = new CommandTester(new UserBlockCommand($this->entityTypeManager, $this->logger));
        $this->assertSame(Command::SUCCESS, $tester->execute(['--mail' => self::MAIL]));
        $this->assertEquals(0, $this->informationJson(['names' => self::NAME])[$uid]['user_status']);
    }

    public function testRoleAddRemove(): void
    {
        $uid = (int) $this->createExampleUser()->id();
        $this->entityTypeManager->getStorage('user_role')->create(['id' => 'test_role', 'label' => 'Test role'])->save();

        $tester = new CommandTester(new UserRoleAddCommand($this->entityTypeManager, $this->logger));
        $this->assertSame(Command::SUCCESS, $tester->execute(['role' => 'test_role', 'names' => self::NAME]));
        $this->assertStringContainsString('Added test_role role to example', $tester->getDisplay());
        $this->assertSame(['authenticated', 'test_role'], $this->informationJson(['names' => self::NAME])[$uid]['roles']);

        $tester = new CommandTester(new UserRoleRemoveCommand($this->entityTypeManager, $this->logger));
        $this->assertSame(Command::SUCCESS, $tester->execute(['role' => 'test_role', '--uid' => (string) $uid]));
        $this->assertStringContainsString('Removed test_role role from example', $tester->getDisplay());
        $this->assertSame(['authenticated'], $this->informationJson(['names' => self::NAME])[$uid]['roles']);
    }

    public function testUnknownRole(): void
    {
        $this->createExampleUser();
        $commands = [
            new UserRoleAddCommand($this->entityTypeManager, $this->logger),
            new UserRoleRemoveCommand($this->entityTypeManager, $this->logger),
        ];
        foreach ($commands as $command) {
            $tester = new CommandTester($command);
            try {
                $tester->execute(['role' => 'no_such_role', 'names' => self::NAME]);
                $this->fail($command::class . ' accepted an unknown role.');
            } catch (\InvalidArgumentException $e) {
                $this->assertSame('Unable to load the user_role: no_such_role', $e->getMessage());
            }
        }
    }

    public function testPassword(): void
    {
        $account = $this->createExampleUser();
        $password = $this->container->get('password');
        $this->assertTrue($password->check(self::PASSWORD, $account->getPassword()));

        $tester = new CommandTester(new UserPasswordCommand($this->entityTypeManager));
        $this->assertSame(Command::SUCCESS, $tester->execute(['name' => self::NAME, 'password' => 'newpass']));
        $this->assertStringContainsString('Changed password for example.', $tester->getDisplay());

        $account = $this->loadExampleUser();
        $this->assertTrue($password->check('newpass', $account->getPassword()));
        $this->assertFalse($password->check(self::PASSWORD, $account->getPassword()));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to load user: nobody');
        $tester->execute(['name' => 'nobody', 'password' => 'newpass']);
    }

    public function testCancelDeleteContent(): void
    {
        $uid = (int) $this->createExampleUser()->id();

        $tester = new CommandTester(new UserCancelCommand($this->entityTypeManager, $this->logger, $this->container->get('request_stack')));
        $tester->setInputs(['yes']);
        $this->assertSame(Command::SUCCESS, $tester->execute(['names' => self::NAME, '--delete-content' => true], ['interactive' => true]));
        $display = $tester->getDisplay();
        $this->assertStringContainsString('All content created by example will be deleted.', $display);
        $this->assertStringContainsString('Cancel user account?', $display);
        $this->assertNull($this->entityTypeManager->getStorage('user')->load($uid));

        // Declining leaves the account alone.
        $this->createExampleUser();
        $tester = new CommandTester(new UserCancelCommand($this->entityTypeManager, $this->logger, $this->container->get('request_stack')));
        $tester->setInputs(['no']);
        $this->assertSame(Command::SUCCESS, $tester->execute(['names' => self::NAME, '--delete-content' => true], ['interactive' => true]));
        $this->assertInstanceOf(UserInterface::class, $this->loadExampleUser());
    }

    public function testNoMatchingUser(): void
    {
        $commands = [
            [new UserBlockCommand($this->entityTypeManager, $this->logger), ['names' => 'nobody']],
            [new UserUnblockCommand($this->entityTypeManager, $this->logger), ['--uid' => '99']],
            [new UserCancelCommand($this->entityTypeManager, $this->logger, $this->container->get('request_stack')), ['--mail' => 'nobody@example.com']],
            [new UserInformationCommand($this->entityTypeManager, $this->dateFormatter, $this->logger), []],
        ];
        foreach ($commands as [$command, $input]) {
            $tester = new CommandTester($command);
            try {
                $tester->execute($input);
                $this->fail($command::class . ' found a user that does not exist.');
            } catch (\InvalidArgumentException $e) {
                $this->assertSame('Unable to find any matching user', $e->getMessage());
            }
        }
    }

    /**
     * user:login forwards its input to dr. A stand-in dr script records what
     * it received, so the test needs neither core's command nor a site.
     */
    public function testLogin(): void
    {
        // KernelTestBase's site directory is a vfs:// stream, which cannot
        // hold an executable, so the stand-in lives in the real temp dir.
        $vendorDir = sys_get_temp_dir() . '/' . uniqid('drush-user-test-');
        mkdir("$vendorDir/bin", 0777, true);
        $script = "#!/usr/bin/env php\n<?php\narray_shift(\$argv);\necho implode(' ', \$argv), \"\\n\";\nexit(3);\n";
        file_put_contents("$vendorDir/bin/dr", $script);
        chmod("$vendorDir/bin/dr", 0755);
        $config = new DrushConfig();
        $config->set('drush.vendor-dir', $vendorDir);
        $command = new UserLoginCommand($config, new BootstrapManager(), new NullLogger());

        $tester = new CommandTester($command);
        $this->assertSame(3, $tester->execute(['path' => 'node/add', '--name' => self::NAME, '--uid' => '7']));
        $this->assertSame("user:login node/add --name=example --uid=7\n", $tester->getDisplay());

        // Options are only passed when given, and the path is optional.
        $tester = new CommandTester($command);
        $tester->execute(['--mail' => self::MAIL]);
        $this->assertSame("user:login --mail=example@example.com\n", $tester->getDisplay());

        // A missing dr is reported, with the core requirement.
        unlink("$vendorDir/bin/dr");
        rmdir("$vendorDir/bin");
        rmdir($vendorDir);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("$vendorDir/bin/dr was not found. user:login runs dr user:login, which requires Drupal core 11.4 or later.");
        (new CommandTester($command))->execute([]);
    }

    protected function createExampleUser(): UserInterface
    {
        $tester = new CommandTester(new UserCreateCommand($this->entityTypeManager, $this->logger));
        $this->assertSame(Command::SUCCESS, $tester->execute(['name' => self::NAME, '--mail' => self::MAIL, '--password' => self::PASSWORD]));
        return $this->loadExampleUser();
    }

    protected function loadExampleUser(): ?UserInterface
    {
        $accounts = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => self::NAME]);
        return $accounts ? reset($accounts) : null;
    }

    protected function information(array $input): CommandTester
    {
        if (array_is_list($input)) {
            $input = ['names' => implode(',', $input)];
        }
        $tester = new CommandTester(new UserInformationCommand($this->entityTypeManager, $this->dateFormatter, $this->logger));
        $this->assertSame(Command::SUCCESS, $tester->execute($input), $tester->getDisplay());
        return $tester;
    }

    protected function informationJson(array $input): array
    {
        $display = $this->information($input + ['--format' => 'json'])->getDisplay();
        return json_decode($display, true, flags: JSON_THROW_ON_ERROR);
    }
}
