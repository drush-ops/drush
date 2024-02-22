<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\SiteAlias\SiteAlias;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Consolidation\SiteProcess\ProcessManager;
use Drupal\Component\DependencyInjection\ContainerInterface;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\config\ConfigImportCommands;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use League\Container\Container as DrushContainer;

final class DeployCommands extends DrushCommands
{
    const DEPLOY = 'deploy';

    public function __construct(
        private readonly SiteAliasManagerInterface $siteAliasManager
    ) {
        parent::__construct();
    }

    public static function create(ContainerInterface $container, DrushContainer $drush_container): self
    {
        $commandHandler = new static(
            $drush_container->get('site.alias.manager'),
        );

        return $commandHandler;
    }

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
        $self = $this->siteAliasManager->getSelf();
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
