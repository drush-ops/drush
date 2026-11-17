<?php

declare(strict_types=1);

namespace Unish;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drush\Doctor\Commands\sql\SanitizeCommand;
use Drush\EventSubscriber\sanitize\SanitizeCommentsSubscriber;
use Drush\EventSubscriber\sanitize\SanitizeSessionsSubscriber;
use Drush\EventSubscriber\sanitize\SanitizeUserFieldsSubscriber;
use Drush\EventSubscriber\sanitize\SanitizeUserTableSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * Tests sql:sanitize and its subscribers.
 *
 * The subscribers add the options and do the work on console events, so the
 * command runs through an Application that has the container's command
 * loader and event dispatcher, as dr does.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(SanitizeCommand::class)]
#[CoversClass(SanitizeCommentsSubscriber::class)]
#[CoversClass(SanitizeSessionsSubscriber::class)]
#[CoversClass(SanitizeUserFieldsSubscriber::class)]
#[CoversClass(SanitizeUserTableSubscriber::class)]
class SqlSanitizeTest extends KernelTestBase
{
    protected static $modules = ['system', 'user', 'field', 'text', 'filter'];

    /**
     * The uids of the two accounts. Serial values differ between drivers.
     */
    protected int $joe;
    protected int $ed;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installEntitySchema('user');
        $this->installConfig(['system', 'user']);

        foreach (['field_bio' => 'text_long', 'field_nickname' => 'string', 'field_alt_mail' => 'email'] as $name => $type) {
            FieldStorageConfig::create(['field_name' => $name, 'entity_type' => 'user', 'type' => $type])->save();
            FieldConfig::create(['field_name' => $name, 'entity_type' => 'user', 'bundle' => 'user'])->save();
        }
        Role::create(['id' => 'editor', 'label' => 'Editor'])->save();

        // uid 0, as on an installed site.
        User::create(['uid' => 0, 'name' => '', 'status' => 0])->save();
        $this->joe = $this->createAccount('joe user', 'joe@example.com');
        $this->ed = $this->createAccount('ed', 'ed@example.com', ['editor']);
    }

    public function testSummaryAndDefaults(): void
    {
        $tester = $this->sanitize();
        $display = $tester->getDisplay();
        $this->assertStringContainsString('The following operations will be performed:', $display);
        foreach (['Sanitize user passwords.', 'Sanitize user emails.', 'Sanitize text fields associated with users.'] as $line) {
            $this->assertStringContainsString("* $line", $display);
        }
        // No such table, no ignored roles, and no comment module.
        $this->assertStringNotContainsString('Truncate sessions table.', $display);
        $this->assertStringNotContainsString('Preserve user emails', $display);
        $this->assertStringNotContainsString('comment', $display);
        $this->assertStringContainsString('User passwords sanitized.', $display);
        $this->assertStringContainsString('user__field_bio table sanitized.', $display);

        $anonymous = $this->account(0);
        $this->assertNull($anonymous->getEmail());
        $this->assertNull($anonymous->getPassword());

        foreach ([$this->joe => 'joe user', $this->ed => 'ed'] as $uid => $name) {
            $account = $this->account($uid);
            $this->assertSame($name, $account->getAccountName());
            $this->assertSame("user+$uid@localhost.localdomain", $account->getEmail());
            $this->assertSame("user+$uid@localhost.localdomain", $account->getInitialEmail());
            $this->assertFalse($this->passwordMatches($account, 'secret'));
            $this->assertNotSame('Private bio', $account->get('field_bio')->value);
            $this->assertNotSame('Joey', $account->get('field_nickname')->value);
            $this->assertNotSame('private@example.com', $account->get('field_alt_mail')->value);
        }
    }

    public function testAbort(): void
    {
        $this->createSessionsTable();
        $tester = $this->sanitize([], interactive: true, inputs: ['no'], expected: Command::FAILURE);
        $this->assertStringContainsString('Do you want to sanitize the current database?', $tester->getDisplay());
        $this->assertStringNotContainsString('sanitized.', $tester->getDisplay());

        $account = $this->account($this->joe);
        $this->assertSame('joe@example.com', $account->getEmail());
        $this->assertTrue($this->passwordMatches($account, 'secret'));
        $this->assertSame('Private bio', $account->get('field_bio')->value);
        $this->assertSame(1, $this->countSessions());
    }

    public function testConfirm(): void
    {
        $this->sanitize([], interactive: true, inputs: ['yes']);
        $this->assertSame("user+{$this->joe}@localhost.localdomain", $this->account($this->joe)->getEmail());
    }

    public function testKeepEmailAndPassword(): void
    {
        $tester = $this->sanitize(['--sanitize-email' => 'no', '--sanitize-password' => 'no']);
        $this->assertStringNotContainsString('Sanitize user passwords.', $tester->getDisplay());
        $this->assertStringNotContainsString('Sanitize user emails.', $tester->getDisplay());
        $this->assertStringContainsString('Sanitize text fields associated with users.', $tester->getDisplay());

        $account = $this->account($this->joe);
        $this->assertSame('joe@example.com', $account->getEmail());
        $this->assertTrue($this->passwordMatches($account, 'secret'));
        // The other sanitizers still ran.
        $this->assertNotSame('Private bio', $account->get('field_bio')->value);
    }

    public function testKeepEmailOnly(): void
    {
        $this->sanitize(['--sanitize-email' => 'no']);
        $account = $this->account($this->joe);
        $this->assertSame('joe@example.com', $account->getEmail());
        $this->assertFalse($this->passwordMatches($account, 'secret'));
    }

    public function testFixedEmailAndPassword(): void
    {
        $this->sanitize(['--sanitize-email' => 'user@mysite.org', '--sanitize-password' => 'known']);
        foreach ([$this->joe, $this->ed] as $uid) {
            $account = $this->account($uid);
            $this->assertSame('user@mysite.org', $account->getEmail());
            $this->assertTrue($this->passwordMatches($account, 'known'));
        }
    }

    public function testEmailPattern(): void
    {
        // Every replacement pattern, and a quote in the literal part.
        $this->sanitize(['--sanitize-email' => "%uid-%name-%mail+o'neil@test.invalid"]);
        $this->assertSame("{$this->joe}-joe_user-joe_example.com+o'neil@test.invalid", $this->account($this->joe)->getEmail());
        $this->assertSame("{$this->joe}-joe_user-joe_example.com+o'neil@test.invalid", $this->account($this->joe)->getInitialEmail());
        $this->assertSame("{$this->ed}-ed-ed_example.com+o'neil@test.invalid", $this->account($this->ed)->getEmail());
    }

    public function testIgnoredRoles(): void
    {
        $tester = $this->sanitize(['--ignored-roles' => 'missing, editor']);
        $this->assertStringContainsString('Preserve user emails and passwords for the specified roles.', $tester->getDisplay());
        $this->assertStringContainsString('User emails and passwords for the specified roles preserved.', $tester->getDisplay());

        $editor = $this->account($this->ed);
        $this->assertSame('ed@example.com', $editor->getEmail());
        $this->assertTrue($this->passwordMatches($editor, 'secret'));

        $other = $this->account($this->joe);
        $this->assertSame("user+{$this->joe}@localhost.localdomain", $other->getEmail());
        $this->assertFalse($this->passwordMatches($other, 'secret'));
    }

    public function testSessions(): void
    {
        $this->createSessionsTable();
        $tester = $this->sanitize();
        $this->assertStringContainsString('* Truncate sessions table.', $tester->getDisplay());
        $this->assertStringContainsString('Sessions table truncated.', $tester->getDisplay());
        $this->assertSame(0, $this->countSessions());
    }

    public function testAllowlistFields(): void
    {
        $tester = $this->sanitize(['--allowlist-fields' => 'field_nickname,field_alt_mail']);
        $this->assertStringContainsString('user__field_bio table sanitized.', $tester->getDisplay());
        $this->assertStringNotContainsString('user__field_nickname', $tester->getDisplay());

        $account = $this->account($this->joe);
        $this->assertSame('Joey', $account->get('field_nickname')->value);
        $this->assertSame('private@example.com', $account->get('field_alt_mail')->value);
        $this->assertNotSame('Private bio', $account->get('field_bio')->value);

        $tester = $this->sanitize(['--allowlist-fields' => 'field_nickname,field_alt_mail,field_bio']);
        $this->assertStringContainsString('No text fields for users need sanitizing.', $tester->getDisplay());
    }

    public function testComments(): void
    {
        // The subscriber is registered once the module is installed.
        $this->enableModules(['comment']);
        $this->installEntitySchema('comment');
        $database = $this->container->get('database');
        foreach ([0 => 'Visitor', $this->joe => 'joe user'] as $uid => $name) {
            $cid = $uid ? 2 : 1;
            $database->insert('comment_field_data')
                ->fields([
                    'cid' => $cid,
                    'comment_type' => 'comment',
                    'langcode' => 'en',
                    'default_langcode' => 1,
                    'status' => 1,
                    'created' => 1,
                    'changed' => 1,
                    'thread' => '01/',
                    'uid' => $uid,
                    'entity_type' => 'node',
                    'field_name' => 'comment',
                    'name' => $name,
                    'mail' => 'commenter@example.com',
                    'homepage' => 'https://private.example.com',
                ])
                ->execute();
        }

        $tester = $this->sanitize();
        $this->assertStringContainsString('* Remove comment display names and emails.', $tester->getDisplay());
        $this->assertStringContainsString('Comment display names and emails removed.', $tester->getDisplay());

        $rows = $database->select('comment_field_data', 'c')->fields('c', ['cid', 'name', 'mail', 'homepage'])->orderBy('cid')->execute()->fetchAll();
        $this->assertEquals([
            ['cid' => 1, 'name' => 'Anonymous', 'mail' => '', 'homepage' => 'http://example.com'],
            ['cid' => 2, 'name' => "User{$this->joe}", 'mail' => "user+{$this->joe}@example.com", 'homepage' => 'http://example.com'],
        ], array_map(fn(object $row): array => (array) $row, $rows));
    }

    /**
     * Subscribers add their options whichever command runs, so help has them.
     */
    public function testHelp(): void
    {
        $tester = new ApplicationTester($this->application());
        $tester->run(['command' => SanitizeCommand::NAME, '--help' => true]);
        $tester->assertCommandIsSuccessful();
        foreach (['--sanitize-email=SANITIZE-EMAIL', '--sanitize-password', '--ignored-roles', '--allowlist-fields'] as $option) {
            $this->assertStringContainsString($option, $tester->getDisplay());
        }
        $this->assertStringContainsString('[default: "user+%uid@localhost.localdomain"]', $tester->getDisplay());

        // Nothing was sanitized.
        $this->assertSame('joe@example.com', $this->account($this->joe)->getEmail());

        $tester = new ApplicationTester($this->application());
        $this->assertSame(Command::FAILURE, $tester->run(['command' => SanitizeCommand::NAME, '--no-such-option' => true]));
    }

    /**
     * Runs sql:sanitize the way dr does.
     *
     * @param string[] $inputs
     */
    protected function sanitize(array $options = [], bool $interactive = false, array $inputs = [], int $expected = Command::SUCCESS): ApplicationTester
    {
        $tester = new ApplicationTester($this->application());
        $tester->setInputs($inputs);
        $code = $tester->run(['command' => SanitizeCommand::NAME] + $options, ['interactive' => $interactive]);
        $this->assertSame($expected, $code, $tester->getDisplay());
        return $tester;
    }

    protected function application(): Application
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCommandLoader($this->container->get('console.command_loader'));
        $application->setDispatcher($this->container->get('event_dispatcher'));
        return $application;
    }

    /**
     * @param string[] $roles
     */
    protected function createAccount(string $name, string $mail, array $roles = []): int
    {
        $account = User::create([
            'name' => $name,
            'mail' => $mail,
            'init' => $mail,
            'pass' => 'secret',
            'status' => 1,
            'roles' => $roles,
            'field_bio' => 'Private bio',
            'field_nickname' => 'Joey',
            'field_alt_mail' => 'private@example.com',
        ]);
        $account->save();
        return (int) $account->id();
    }

    protected function account(int $uid): User
    {
        return $this->container->get('entity_type.manager')->getStorage('user')->loadUnchanged($uid);
    }

    protected function passwordMatches(User $account, string $password): bool
    {
        return $this->container->get('password')->check($password, $account->getPassword());
    }

    protected function createSessionsTable(): void
    {
        $database = $this->container->get('database');
        $database->schema()->createTable('sessions', [
            'fields' => ['sid' => ['type' => 'varchar_ascii', 'length' => 128, 'not null' => true]],
            'primary key' => ['sid'],
        ]);
        $database->insert('sessions')->fields(['sid' => 'abc'])->execute();
    }

    protected function countSessions(): int
    {
        return (int) $this->container->get('database')->select('sessions')->countQuery()->execute()->fetchField();
    }
}
