<?php

declare(strict_types=1);

namespace Unish;

use Drush\Commands\core\MkDocsCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[CoversClass(MkDocsCommand::class)]
#[Group('base')]
class MkDocsTest extends TestCase
{
    public function testCliTextToMarkdown(): void
    {
        $this->assertSame('Run *dr status* now.', MkDocsCommand::cliTextToMarkdown('Run <info>dr status</info> now.'));
        $this->assertSame('See [jq](https://jqlang.org/).', MkDocsCommand::cliTextToMarkdown('See <href=https://jqlang.org/>jq</>.'));
    }

    public function testFormatOption(): void
    {
        $option = new InputOption('format', 'f', InputOption::VALUE_REQUIRED, 'The output format.', 'json');
        $this->assertSame('-f, --format=FORMAT', MkDocsCommand::formatOptionKeys($option));
        $this->assertSame('The output format. [default: <info>json</info>]', MkDocsCommand::formatOptionDescription($option));

        $optional = new InputOption('editor', null, InputOption::VALUE_OPTIONAL, 'An editor.');
        $this->assertSame('--editor[=EDITOR]', MkDocsCommand::formatOptionKeys($optional));

        $flag = new InputOption('all', null, InputOption::VALUE_NONE, 'Everything.');
        $this->assertSame('--all', MkDocsCommand::formatOptionKeys($flag));
        $this->assertSame('Everything.', MkDocsCommand::formatOptionDescription($flag));
    }

    public function testEditUrl(): void
    {
        $root = '/app';
        $core = '/app/sut/core';
        $this->assertSame(
            'https://github.com/drush-ops/drush/blob/15.x/src/Commands/queue/QueueRunCommand.php',
            MkDocsCommand::editUrl("$root/src/Commands/queue/QueueRunCommand.php", $root, $core, '12.0-dev', 'v8.0.1')
        );
        $this->assertSame(
            'https://git.drupalcode.org/project/drupal/-/blob/main/core/lib/Drupal/Core/Command/InstallCommand.php',
            MkDocsCommand::editUrl("$core/lib/Drupal/Core/Command/InstallCommand.php", $root, $core, '12.0-dev', 'v8.0.1')
        );
        $this->assertSame(
            'https://git.drupalcode.org/project/drupal/-/blob/11.2.5/core/lib/Drupal/Core/Command/InstallCommand.php',
            MkDocsCommand::editUrl("$core/lib/Drupal/Core/Command/InstallCommand.php", $root, $core, '11.2.5', 'v8.0.1')
        );
        $this->assertSame(
            'https://github.com/symfony/console/blob/8.1/Command/HelpCommand.php',
            MkDocsCommand::editUrl("$root/vendor/symfony/console/Command/HelpCommand.php", $root, $core, '12.0-dev', '8.1.x-dev')
        );
        $this->assertNull(MkDocsCommand::editUrl("$root/vendor/acme/foo/FooCommand.php", $root, $core, '12.0-dev', 'v8.0.1'));
    }

    public function testFormatArgument(): void
    {
        $this->assertSame('name', MkDocsCommand::formatArgument(new InputArgument('name', InputArgument::REQUIRED)));
        $this->assertSame('[name]', MkDocsCommand::formatArgument(new InputArgument('name', InputArgument::OPTIONAL)));
        $this->assertSame('names (names)...', MkDocsCommand::formatArgument(new InputArgument('names', InputArgument::IS_ARRAY | InputArgument::REQUIRED)));
        $this->assertSame('[names]...', MkDocsCommand::formatArgument(new InputArgument('names', InputArgument::IS_ARRAY)));
    }

    public function testGetFilename(): void
    {
        $this->assertSame('pm_install.md', MkDocsCommand::getFilename('pm:install'));
        $this->assertSame('status.md', MkDocsCommand::getFilename('status'));
    }

    public function testGetRedirectMap(): void
    {
        $command = new Command('pm:install');
        // pm-install only differs from the name by a dash, so gets no redirect.
        $command->setAliases(['in', 'pm-install', 'pm:enable']);
        $this->assertSame([
            'commands/in.md' => 'commands/pm_install.md',
            'commands/pm_enable.md' => 'commands/pm_install.md',
        ], MkDocsCommand::getRedirectMap($command, 'commands'));
    }

    public function testCategorize(): void
    {
        $install = new Command('pm:install')->setAliases(['in']);
        $list = new Command('pm:list');
        $route = new Command('core:route');
        $hidden = new Command('pm:secret')->setHidden(true);

        $namespaced = MkDocsCommand::categorize([
            'pm:list' => $list,
            'pm:install' => $install,
            'in' => $install,
            'core:route' => $route,
            'pm:secret' => $hidden,
        ]);
        // Aliases and hidden commands are dropped; a namespace with a single
        // command moves to _global.
        $this->assertSame(['_global', 'pm'], array_keys($namespaced));
        $this->assertSame(['core:route'], array_keys($namespaced['_global']));
        $this->assertSame(['pm:install', 'pm:list'], array_keys($namespaced['pm']));
    }
}
