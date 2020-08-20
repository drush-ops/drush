<?php
namespace Drush\Commands\core;

use Consolidation\SiteAlias\SiteAlias;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteProcess\ProcessManager;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;

class DeployCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    /**
     * Run several commands after performing a code deployment.
     *
     * @command deploy
     *
     * @usage drush deploy -v -y
     *   Run updates with verbose logging and accept all prompts.
     *
     * @topics docs:deploy
     *
     * @throws \Exception
     */
    public function deploy()
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
     * @param \Consolidation\SiteProcess\ProcessManager $manager
     * @param \Consolidation\SiteAlias\SiteAlias $self
     * @param array $redispatchOptions
     */
    public function cacheRebuild(ProcessManager $manager, SiteAlias $self, array $redispatchOptions)
    {
        // It is possible that no updates were pending and thus no caches cleared yet.
        $this->logger()->success("Cache rebuild start.");
        $process = $manager->drush($self, 'cache:rebuild', [], $redispatchOptions);
        $process->mustRun($process->showRealtime());
    }
}
