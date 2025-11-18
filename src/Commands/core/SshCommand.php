<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Consolidation\SiteProcess\Util\Shell;
use Consolidation\SiteProcess\Util\Tty;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\SiteAlias\ProcessManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Connect to a webserver via SSH, and optionally run a shell command.',
    aliases: ['ssh', 'site-ssh']
)]
#[CLI\OptionsetProcBuild]
#[CLI\HandleRemoteCommands]
#[CLI\HelpLinks(links: [HelpLinks::Aliases])]
#[CLI\Bootstrap(DrupalBootLevels::NONE)]
final class SshCommand extends Command
{
    use AutowireTrait;

    public const string NAME = 'site:ssh';

    public function __construct(
        private readonly SiteAliasManagerInterface $siteAliasManager,
        private readonly ProcessManager $processManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('code', InputArgument::IS_ARRAY, 'Code which should run at remote host.')
            ->addOption('cd', null, InputOption::VALUE_REQUIRED, 'Directory to change to. Defaults to Drupal root.')
            ->addUsage('ssh "ls /tmp"')
            ->addUsage('ssh "git pull"');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $code = $input->getArgument('code');
        $cdOption = $input->getOption('cd');
        $ttyOption = $input->getOption('tty');

        $alias = $this->siteAliasManager->getSelf();

        if ($code === []) {
            $code[] = 'bash';
            $code[] = '-l';

            // We're calling an interactive 'bash' shell, so we want to
            // force tty to true.
            $ttyOption = true;
        }

        if ((count($code) === 1)) {
            $code = [Shell::preEscaped($code[0])];
        }

        $process = $this->processManager->siteProcess($alias, $code);
        if (!Tty::isTtySupported()) {
            // See https://github.com/symfony/symfony/issues/37835#issuecomment-674386588.
            // If testing this will get input added by `CommandTester::setInputs` method.
            $inputStream = ($input instanceof StreamableInputInterface) ? $input->getStream() : STDIN;
            $process->setInput($inputStream);
        } else {
            $process->setTty($ttyOption);
        }
        // The transport handles the chdir during processArgs().
        $fallback = $alias->hasRoot() ? $alias->root() : null;
        $process->setWorkingDirectory($cdOption ?: $fallback);
        $process->mustRun($process->showRealtime());

        return self::SUCCESS;
    }
}
