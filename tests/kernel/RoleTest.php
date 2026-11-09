<?php

declare(strict_types=1);

namespace Unish;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\PermissionHandlerInterface;
use Drush\Commands\role\RoleCreateCommand;
use Drush\Commands\role\RoleDeleteCommand;
use Drush\Commands\role\RoleListCommand;
use Drush\Commands\role\RolePermAddCommand;
use Drush\Commands\role\RolePermRemoveCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests the role commands: each command is built from container services
 * and driven with a CommandTester. The user module supplies the roles and
 * the permissions under test.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(RoleCreateCommand::class)]
#[CoversClass(RoleDeleteCommand::class)]
#[CoversClass(RoleListCommand::class)]
#[CoversClass(RolePermAddCommand::class)]
#[CoversClass(RolePermRemoveCommand::class)]
class RoleTest extends KernelTestBase
{
    protected static $modules = ['system', 'user'];

    const PERM = 'administer permissions';

    protected EntityTypeManagerInterface $entityTypeManager;
    protected PermissionHandlerInterface $permissionHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installEntitySchema('user');
        // Provides the anonymous and authenticated roles.
        $this->installConfig(['user']);
        $this->entityTypeManager = $this->container->get('entity_type.manager');
        $this->permissionHandler = $this->container->get('user.permissions');
    }

    public function testListDefaultRoles(): void
    {
        $tester = $this->list([]);
        $display = $tester->getDisplay();
        $this->assertMatchesRegularExpression('/ID\s+\|\s+Role Label\s+\|\s+Permissions/', $display);
        $this->assertMatchesRegularExpression('/anonymous\s+\|\s+Anonymous user/', $display);
        $this->assertMatchesRegularExpression('/authenticated\s+\|\s+Authenticated user/', $display);

        $yaml = Yaml::decode($this->list(['--format' => 'yaml'])->getDisplay());
        $this->assertSame(['anonymous', 'authenticated'], array_keys($yaml));
        $this->assertSame('Anonymous user', $yaml['anonymous']['label']);
    }

    public function testRoleLifecycle(): void
    {
        $rid = 'foo';

        // Create the role and see it listed, as a table and as json.
        $tester = new CommandTester(new RoleCreateCommand($this->entityTypeManager));
        $this->assertSame(Command::SUCCESS, $tester->execute(['machine-name' => $rid, 'human-readable-name' => 'Foo role']));
        $this->assertStringContainsString('Created "foo" role', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/foo\s+\|\s+Foo role/', $this->list([])->getDisplay());
        $json = $this->listJson();
        $this->assertSame(['rid' => $rid, 'label' => 'Foo role', 'perms' => []], $json[$rid]);

        // Grant a permission and see it in the json output.
        $tester = new CommandTester(new RolePermAddCommand($this->entityTypeManager, $this->permissionHandler));
        $this->assertSame(Command::SUCCESS, $tester->execute(['machine-name' => $rid, 'permissions' => self::PERM]));
        $this->assertStringContainsString('Added "administer permissions" permission to "foo" role', $tester->getDisplay());
        $this->assertSame([self::PERM], $this->listJson()[$rid]['perms']);
        $this->assertStringContainsString(self::PERM, $this->list([])->getDisplay());

        // --filter keeps only roles holding the permission.
        $json = $this->listJson(['--filter' => self::PERM]);
        $this->assertSame([$rid], array_keys($json));
        $this->assertSame([], $this->listJson(['--filter' => 'access user profiles']));

        // Revoke it.
        $tester = new CommandTester(new RolePermRemoveCommand($this->entityTypeManager, $this->permissionHandler));
        $this->assertSame(Command::SUCCESS, $tester->execute(['machine-name' => $rid, 'permissions' => self::PERM]));
        $this->assertStringContainsString('Removed "administer permissions" permission from "foo" role', $tester->getDisplay());
        $this->assertSame([], $this->listJson()[$rid]['perms']);

        // Delete the role.
        $tester = new CommandTester(new RoleDeleteCommand($this->entityTypeManager));
        $this->assertSame(Command::SUCCESS, $tester->execute(['machine-name' => $rid]));
        $this->assertStringContainsString('Deleted foo role', $tester->getDisplay());
        $this->assertArrayNotHasKey($rid, $this->listJson());
    }

    public function testCreateExistingRole(): void
    {
        $tester = new CommandTester(new RoleCreateCommand($this->entityTypeManager));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The user_role already exists: anonymous');
        $tester->execute(['machine-name' => 'anonymous']);
    }

    public function testUnknownRole(): void
    {
        $commands = [
            [new RoleDeleteCommand($this->entityTypeManager), []],
            [new RolePermAddCommand($this->entityTypeManager, $this->permissionHandler), ['permissions' => self::PERM]],
            [new RolePermRemoveCommand($this->entityTypeManager, $this->permissionHandler), ['permissions' => self::PERM]],
        ];
        foreach ($commands as [$command, $extra]) {
            $tester = new CommandTester($command);
            try {
                $tester->execute(['machine-name' => 'no_such_role'] + $extra);
                $this->fail($command::class . ' accepted an unknown role.');
            } catch (\InvalidArgumentException $e) {
                $this->assertSame('Unable to load the user_role: no_such_role', $e->getMessage());
            }
        }
    }

    public function testUnknownPermission(): void
    {
        $commands = [
            new RolePermAddCommand($this->entityTypeManager, $this->permissionHandler),
            new RolePermRemoveCommand($this->entityTypeManager, $this->permissionHandler),
        ];
        foreach ($commands as $command) {
            $tester = new CommandTester($command);
            try {
                $tester->execute(['machine-name' => 'anonymous', 'permissions' => self::PERM . ',no such perm,nor this']);
                $this->fail($command::class . ' accepted an unknown permission.');
            } catch (\InvalidArgumentException $e) {
                $this->assertSame('Permission(s) not found: no such perm, nor this', $e->getMessage());
            }
        }
        // Nothing was granted on the way to the error.
        $this->assertSame([], $this->listJson()['anonymous']['perms']);
    }

    protected function list(array $input): CommandTester
    {
        $tester = new CommandTester(new RoleListCommand($this->entityTypeManager));
        $this->assertSame(Command::SUCCESS, $tester->execute($input), $tester->getDisplay());
        return $tester;
    }

    protected function listJson(array $input = []): array
    {
        $display = $this->list($input + ['--format' => 'json'])->getDisplay();
        return json_decode($display, true, flags: JSON_THROW_ON_ERROR);
    }
}
