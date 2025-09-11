<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drupal\Core\Url;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Drush;
use Drush\Exec\ExecTrait;
use Drush\Log\DrushLoggerManager;
use Drush\SiteAlias\ProcessManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Display a link to a given path or open link in a browser',
)]
#[CLI\HandleRemoteCommands]
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
final class BrowseCommand extends Command
{
    use AutowireTrait;
    use ExecTrait;

    const NAME = 'browse';

    public function __construct(
        private readonly SiteAliasManagerInterface $siteAliasManager,
        private readonly ProcessManager $processManager,
        protected readonly DrushLoggerManager $logger,
    ) {
        parent::__construct();
    }

    protected function configure() {
        $this
            ->addArgument(name: 'path', mode: InputOption::VALUE_REQUIRED, description: 'Path to open. If omitted, the site front page will be opened.')
            ->addOption(name: 'browser', mode: InputOption::VALUE_NEGATABLE, description: 'Open the URL in the default browser.')
            ->addOption(name: 'redirect-port', mode: InputOption::VALUE_REQUIRED, description: 'The port that the web server is redirected to (e.g. when running within a Vagrant environment).')
            ->addUsage(usage: 'browse node/1')
            ->addUsage(usage: '@example.prod browse');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $aliasRecord = $this->siteAliasManager->getSelf();
        // Redispatch if called against a remote-host so a browser is started on
        // the *local* machine.
        if ($this->processManager->hasTransport($aliasRecord)) {
            $process = $this->processManager->drush($aliasRecord, self::NAME, [$input->getArgument('path')], Drush::redispatchOptions());
            $process->mustRun();
            $link = $process->getOutput();
        } else {
            if (!Drush::bootstrapManager()->doBootstrap(DrupalBootLevels::FULL)) {
                // Fail gracefully if unable to bootstrap Drupal. drush_bootstrap() has
                // already logged an error.
                return self::FAILURE;
            }
            $link = Url::fromUserInput('/' . $input->getArgument('path'), ['absolute' => true])->toString();
        }

        $this->startBrowser($link, 0, $input->getOption('redirect-port'), $input->getOption('browser'));
        $output->writeln($link);
        return self::SUCCESS;
    }
}
