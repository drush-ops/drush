<?php

namespace Drush\Commands\core;

use Consolidation\SiteAlias\SiteAlias;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteProcess\ProcessManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;

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
    #[CLI\Topics(topics: ['docs:deploy'])]
    public function deploy(): void
    {
        $self = $this->siteAliasManager()->getSelf();
        $redispatchOptions = Drush::redispatchOptions();
        $manager = $this->processManager();

        $this->logger()->notice("Database updates start.");
        $options = ['no-cache-clear' => true];
        $process = $manager->drush($self, 'updatedb', [], $options + $redispatchOptions);
        $process->mustRun($process->showRealtime());

        $this->cacheRebuild($manager, $self, $redispatchOptions);

        $this->logger()->success("Config import start.");
        $process = $manager->drush($self, 'config:import', [], $redispatchOptions);
        $process->mustRun($process->showRealtime());

        $this->cacheRebuild($manager, $self, $redispatchOptions);

        $this->logger()->success("Deploy hook start.");
        $process = $manager->drush($self, 'deploy:hook', [], $redispatchOptions);
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
        $process = $manager->drush($self, 'cache:rebuild', [], $redispatchOptions);
        $process->mustRun($process->showRealtime());
    }
}
