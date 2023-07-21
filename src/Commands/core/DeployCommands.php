<?php

namespace Drush\Commands\core;

use Consolidation\SiteAlias\SiteAlias;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteProcess\ProcessManager;
use Drush\Drupal\Commands\config\ConfigCommands;
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
     * @version 10.3
     *
     * @topics docs:deploy
     *
     * @throws \Exception
     */
    public function deploy(): void
    {
        $self = $this->siteAliasManager()->getSelf();
        $redispatchOptions = Drush::redispatchOptions();
        $manager = $this->processManager();

        // Prepare custom options for checking configuration state.
        $configStatusOptions = $redispatchOptions;
        $configStatusOptions['format'] = 'php';
        // Record the current state of configuration.
        $process = $manager->drush($self, 'config:status', [], $configStatusOptions);
        $process->mustRun();
        $originalConfigDiff = unserialize($process->getOutput());

        $this->logger()->notice("Database updates start.");
        $options = ['no-cache-clear' => true];
        $process = $manager->drush($self, 'updatedb', [], $options + $redispatchOptions);
        $process->mustRun($process->showRealtime());

        $this->cacheRebuild($manager, $self, $redispatchOptions);

        // Record the current state of configuration.
        $process = $manager->drush($self, 'config:status', [], $configStatusOptions);
        $process->mustRun();
        $newConfigDiff = unserialize($process->getOutput());

        // Check for new changes to active configuration that would be lost.
        if ($originalConfigDiff !== $newConfigDiff) {
            $this->io()->error('Update hooks altered config that is about to be reverted during config import. Aborting.');
            return;
        }

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
