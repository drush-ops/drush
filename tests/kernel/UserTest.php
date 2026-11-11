<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\UserInterface;
use Drush\Commands\user\UserBlockCommand;
use Drush\Commands\user\UserCancelCommand;
use Drush\Commands\user\UserCreateCommand;
use Drush\Commands\user\UserInformationCommand;
use Drush\Commands\user\UserPasswordCommand;
use Drush\Commands\user\UserRoleAddCommand;
use Drush\Commands\user\UserRoleRemoveCommand;
use Drush\Commands\user\UserUnblockCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the user commands: each command is built from container services
 * and driven with a CommandTester. user:login is not covered here because
 * it delegates to dr in a subprocess; see tests/functional/UserTest.php.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(UserBlockCommand::class)]
#[CoversClass(UserCancelCommand::class)]
#[CoversClass(UserCreateCommand::class)]
#[CoversClass(UserInformationCommand::class)]
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
        $this->installSchema('system', ['sequences']);
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
        $tester = new CommandTester(new UserCreateCommand($this->entityTypeManager, $this->dateFormatter, $this->logger));
        $status = $tester->execute(
            ['name' => self::NAME, '--mail' => self::MAIL, '--password' => self::PASSWORD],
            ['capture_stderr_separately' => true],
        );
        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('Created a new user with uid 2', $tester->getErrorOutput());
        $display = $tester->getDisplay();
        $this->assertMatchesRegularExpression('/User ID\s+\|\s+User name\s+\|\s+User mail\s+\|\s+User roles\s+\|\s+User status/', $display);
        $this->assertMatchesRegularExpression('/2\s+\|\s+example\s+\|\s+example@example.com\s+\|\s+authenticated\s+\|\s+1/', $display);

        $tester = new CommandTester(new UserCreateCommand($this->entityTypeManager, $this->dateFormatter, $this->logger));
        $status = $tester->execute(
            ['name' => 'another', '--mail' => 'another@example.com', '--format' => 'json'],
            ['capture_stderr_separately' => true],
        );
        $this->assertSame(Command::SUCCESS, $status);
        $json = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([3], array_keys($json));
        $info = $json[3];
        $this->assertSame('another', $info['name']);
        $this->assertSame('another@example.com', $info['mail']);
        $this->assertEquals(1, $info['user_status']);
        $this->assertSame('active', $info['status']);
        $this->assertSame(['authenticated'], $info['roles']);
        $this->assertArrayHasKey('uuid', $info);
        $this->assertArrayHasKey('created', $info);

        // Duplicate names and mails are rejected.
        $tester = new CommandTester(new UserCreateCommand($this->entityTypeManager, $this->dateFormatter, $this->logger));
        try {
            $tester->execute(['name' => 'another']);
            $this->fail('A duplicate user name was accepted.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('There is already a user account with the name another', $e->getMessage());
        }
        try {
            $tester->execute(['name' => 'third', '--mail' => self::MAIL]);
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

        $tester = new CommandTester(new UserCancelCommand($this->entityTypeManager, $this->logger));
        $tester->setInputs(['yes']);
        $this->assertSame(Command::SUCCESS, $tester->execute(['names' => self::NAME, '--delete-content' => true], ['interactive' => true]));
        $display = $tester->getDisplay();
        $this->assertStringContainsString('All content created by example will be deleted.', $display);
        $this->assertStringContainsString('Cancel user account?', $display);
        $this->assertNull($this->entityTypeManager->getStorage('user')->load($uid));

        // Declining leaves the account alone.
        $this->createExampleUser();
        $tester = new CommandTester(new UserCancelCommand($this->entityTypeManager, $this->logger));
        $tester->setInputs(['no']);
        $this->assertSame(Command::SUCCESS, $tester->execute(['names' => self::NAME, '--delete-content' => true], ['interactive' => true]));
        $this->assertInstanceOf(UserInterface::class, $this->loadExampleUser());
    }

    public function testNoMatchingUser(): void
    {
        $commands = [
            [new UserBlockCommand($this->entityTypeManager, $this->logger), ['names' => 'nobody']],
            [new UserUnblockCommand($this->entityTypeManager, $this->logger), ['--uid' => '99']],
            [new UserCancelCommand($this->entityTypeManager, $this->logger), ['--mail' => 'nobody@example.com']],
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

    protected function createExampleUser(): UserInterface
    {
        $tester = new CommandTester(new UserCreateCommand($this->entityTypeManager, $this->dateFormatter, $this->logger));
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
