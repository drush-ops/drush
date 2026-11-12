<?php

declare(strict_types=1);

namespace Unish;

use Drupal\KernelTests\KernelTestBase;
use Drush\Commands\core\RouteCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests core:route, built from the route provider and driven with a
 * CommandTester.
 */
#[Group('commands')]
#[RunTestsInSeparateProcesses]
#[CoversClass(RouteCommand::class)]
class RouteTest extends KernelTestBase
{
    protected static $modules = ['system', 'user'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->container->get('router.builder')->rebuild();
    }

    public function testList(): void
    {
        $tester = $this->route([]);
        $this->assertStringContainsString("system.admin: /admin\n", $tester->getDisplay());
        $this->assertStringContainsString("user.login: /user/login\n", $tester->getDisplay());

        $json = json_decode($this->route(['--format' => 'json'])->getDisplay(), true);
        $this->assertSame('/admin', $json['system.admin']);
    }

    public function testByName(): void
    {
        $display = $this->route(['--name' => 'system.admin'])->getDisplay();
        $this->assertStringStartsWith("name: system.admin\npath: /admin\n", $display);
        $this->assertStringContainsString("requirements:\n  _permission: 'access administration pages'\n", $display);
        $this->assertStringContainsString("  _title: Administration\n", $display, 'the TranslatableMarkup title is a plain string');
        $this->assertStringNotContainsString('compiler_class', $display);

        $json = json_decode($this->route(['--name' => 'system.admin', '--format' => 'json'])->getDisplay(), true);
        $this->assertSame('system.admin', $json['name']);
        $this->assertSame('/admin', $json['path']);
    }

    public function testByPath(): void
    {
        // An internal path, and the same path as a URL.
        foreach (['/user/login', 'https://example.com/user/login'] as $path) {
            $json = json_decode($this->route(['--path' => $path, '--format' => 'json'])->getDisplay(), true);
            $this->assertSame('user.login', $json['name']);
            $this->assertSame('/user/login', $json['path']);
        }
    }

    public function testUnknownName(): void
    {
        $tester = new CommandTester(new RouteCommand($this->container->get('router.route_provider')));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Route no.such.route does not exist.');
        $tester->execute(['--name' => 'no.such.route']);
    }

    protected function route(array $input): CommandTester
    {
        $tester = new CommandTester(new RouteCommand($this->container->get('router.route_provider')));
        $this->assertSame(Command::SUCCESS, $tester->execute($input));
        return $tester;
    }
}
