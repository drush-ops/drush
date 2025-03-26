<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\SiteAlias\SiteAlias;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBoot;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Commands\config\ConfigImportCommands;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\SiteAlias\ProcessManager;

#[CLI\Bootstrap(DrupalBootLevels::NONE)]
final class DeployCommands extends DrushCommands
{
    use AutowireTrait;

    const DEPLOY = 'deploy';

    public function __construct(
        private readonly SiteAliasManagerInterface $siteAliasManager
    ) {
        parent::__construct();
    }

    /**
     * Run several commands after performing a code deployment.
     */
    #[CLI\Command(name: self::DEPLOY)]
    #[CLI\Usage(name: 'drush deploy -v -y', description: 'Run updates with verbose logging and accept all prompts.')]
    #[CLI\Version(version: '10.3')]
    #[CLI\Topics(topics: [DocsCommands::DEPLOY])]
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

        // Since this command is Bootstrap=None, we don't have access to the Drupal container.
        $boot_manager = Drush::bootstrapManager();
        $boot_object = Drush::bootstrap();
        if (($drupal_root = $boot_manager->getRoot()) && ($boot_object instanceof DrupalBoot && version_compare($boot_object->getVersion($drupal_root), '11.2-dev', '>='))) {
            $this->logger()->success("Cache prewarm start.");
            $process = $manager->drush($self, CacheWarmCommands::WARM, [], $redispatchOptions);
            $process->mustRun($process->showRealtime());
        }
    }

    public function cacheRebuild(ProcessManager $manager, SiteAlias $self, array $redispatchOptions): void
    {
        // It is possible that no updates were pending and thus no caches cleared yet.
        $this->logger()->success("Cache rebuild start.");
        $process = $manager->drush($self, CacheRebuildCommands::REBUILD, [], $redispatchOptions);
        $process->mustRun($process->showRealtime());
    }
}
