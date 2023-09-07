<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\SiteAlias\SiteAlias;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteProcess\ProcessManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Commands\config\ConfigImportCommands;
use Drush\Commands\core\DeployHookCommands;
use Drush\Drush;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Drush\Boot\DrupalBootLevels;

final class DeployCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    const DEPLOY = 'deploy';

    /**
     * Run several commands after performing a code deployment.
     */
    #[CLI\Command(name: self::DEPLOY)]
    #[CLI\Usage(name: 'drush deploy -v -y', description: 'Run updates with verbose logging and accept all prompts.')]
    #[CLI\Version(version: '10.3')]
    #[CLI\Topics(topics: [DocsCommands::DEPLOY])]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function deploy(): void
    {
        $self = $this->siteAliasManager()->getSelf();
        $redispatchOptions = Drush::redispatchOptions();
        $manager = $this->processManager();

        $this->logger()->notice("Database updates start.");
        $process = $manager->drush($self, UpdateDBCommands::UPDATEDB, [], $redispatchOptions);
        $process->mustRun($process->showRealtime());

        $this->logger()->success("Config import start.");
        $process = $manager->drush($self, ConfigImportCommands::IMPORT, [], $redispatchOptions);
        $process->mustRun($process->showRealtime());

        $this->cacheRebuild($manager, $self, $redispatchOptions);

        $this->logger()->success("Deploy hook start.");
        $process = $manager->drush($self, DeployHookCommands::HOOK, [], $redispatchOptions);
        $process->mustRun($process->showRealtime());
    }

    /**
     * @param ProcessManager $manager
     * @param SiteAlias $self
     * @param array $redispatchOptions
     */
    public function cacheRebuild(ProcessManager $manager, SiteAlias $self, array $redispatchOptions): void
    {
        // It is possible that no updates were pending and thus no caches cleared yet.
        $this->logger()->success("Cache rebuild start.");
        $process = $manager->drush($self, CacheRebuildCommands::REBUILD, [], $redispatchOptions);
        $process->mustRun($process->showRealtime());
    }
}
