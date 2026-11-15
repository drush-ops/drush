<?php

declare(strict_types=1);

namespace Unish;

use Drush\Command\HelpLinks;
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
        $this->assertSame('Run *drush status* now.', MkDocsCommand::cliTextToMarkdown('Run <info>drush status</info> now.'));

        $link = sprintf('<href=%s/output-formats-filters>Output formats</>', HelpLinks::getDocsUrlBase());
        $this->assertSame('See [Output formats](../output-formats-filters.md).', MkDocsCommand::cliTextToMarkdown("See $link."));
    }

    public function testOptionToArray(): void
    {
        $option = new InputOption('format', 'f', InputOption::VALUE_REQUIRED, 'The output format.', 'json');
        $this->assertSame([
            'name' => '--format',
            'accept_value' => true,
            'is_value_required' => true,
            'shortcut' => 'f',
            'description' => 'The output format.',
            'defaults' => ['json'],
        ], MkDocsCommand::optionToArray($option));

        $flag = new InputOption('all', null, InputOption::VALUE_NONE, 'Everything.');
        $this->assertArrayNotHasKey('defaults', MkDocsCommand::optionToArray($flag));
    }

    public function testArgToArray(): void
    {
        $argument = new InputArgument('names', InputArgument::IS_ARRAY | InputArgument::REQUIRED);
        $this->assertSame(['name' => 'names', 'is_array' => true, 'is_required' => true], MkDocsCommand::argToArray($argument));
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
}
