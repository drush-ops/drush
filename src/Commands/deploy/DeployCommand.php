<?php

declare(strict_types=1);

namespace Drush\Commands\deploy;

use Consolidation\SiteAlias\SiteAlias;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Commands\cache\CacheRebuildCommand;
use Drush\Commands\cache\CacheWarmCommand;
use Drush\Commands\config\ConfigImportCommand;
use Drush\Commands\core\UpdateDBCommands;
use Drush\Drush;
use Drush\SiteAlias\ProcessManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Run several commands after performing a code deployment.',
)]
#[CLI\Bootstrap(DrupalBootLevels::NONE)]
#[CLI\Version(version: '10.3')]
#[CLI\HelpLinks(links: [HelpLinks::Deploy])]
final class DeployCommand extends Command
{
    use AutowireTrait;

    public const NAME = 'deploy';

    public function __construct(
        private readonly SiteAliasManagerInterface $siteAliasManager,
        private readonly LoggerInterface $logger,
        private readonly ProcessManager $processManager,
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $self = $this->siteAliasManager->getSelf();
        $redispatchOptions = Drush::redispatchOptions();
        $this->logger->notice("Database updates start.");
        $process = $this->processManager->drush($self, UpdateDBCommands::UPDATEDB, [], $redispatchOptions);
        $process->mustRun($process->showRealtime());

        $this->logger->notice("Config import start.");
        $process = $this->processManager->drush($self, ConfigImportCommand::NAME, [], $redispatchOptions);
        $process->mustRun($process->showRealtime());

        $this->cacheRebuild($this->processManager, $self, $redispatchOptions);

        $this->logger->notice("Deploy hook start.");
        $process = $this->processManager->drush($self, DeployHookCommand::NAME, [], $redispatchOptions);
        $process->mustRun($process->showRealtime());

        // Since this command is Bootstrap=None, we don't have access to the Drupal container.
        $boot_manager = Drush::bootstrapManager();
        $boot_object = Drush::bootstrap();
        if ($boot_manager->getRoot() && version_compare($boot_object->getVersion($boot_manager->getRoot()), '11.2-dev', '>=')) {
            $this->logger->notice("Cache prewarm start.");
            $process = $this->processManager->drush($self, CacheWarmCommand::NAME, [], $redispatchOptions);
            $process->mustRun($process->showRealtime());
        }
        return Command::SUCCESS;
    }

    public function cacheRebuild(ProcessManager $manager, SiteAlias $self, array $redispatchOptions): void
    {
        // It is possible that no updates were pending and thus no caches cleared yet.
        $this->logger->notice("Cache rebuild start.");
        $process = $manager->drush($self, CacheRebuildCommand::NAME, [], $redispatchOptions);
        $process->mustRun($process->showRealtime());
    }
}
