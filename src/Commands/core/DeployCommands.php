<?php
namespace Drush\Commands\core;

use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;

class DeployCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    /**
     * Run several update related commands after performing a code deployment.
     *
     * @command deploy
     *
     * @usage drush deploy -v -y
     *   Run updates with verbose logging and accept all prompts.
     *
     * @todo Add a topic. Add a test.
     *
     * @throws \Exception
     */
    public function deploy()
    {
        $self = $this->siteAliasManager()->getSelf();
        $redispatchOptions = Drush::redispatchOptions();

        $this->logger()->notice("Database updates start.");
        $options = ['no-cache-clear' => TRUE];
        $process = $this->processManager()->drush($self, 'updatedb', [], $options + $redispatchOptions);
        $process->mustRun($process->showRealtime());
        $this->logger()->success("Database updates complete.");

        $this->logger()->success("Config import start.");
        $process = $this->processManager()->drush($self, 'config:import', [], $redispatchOptions);
        $process->mustRun($process->showRealtime());
        $this->logger()->success("Config import complete.");

        $this->logger()->success("Deploy hook start.");
        $process = $this->processManager()->drush($self, 'deploy:hook', [], $redispatchOptions);
        // $process->mustRun($process->showRealtime());
        $this->logger()->success("Deploy hook complete.");

        // It is possible that no updates were pending and thus no caches cleared yet.
        $this->logger()->success("Cache rebuild start.");
        $process = $this->processManager()->drush($self, 'cache:rebuild', [], $redispatchOptions);
        // To avoid occasional rmdir errors, disable Drush cache for this request.
        $process->setEnv(['DRUSH_PATHS_CACHE_DIRECTORY ' => file_exists('/dev/null') ? '/dev/null' : 'nul']);
        $process->mustRun($process->showRealtime());
        $this->logger()->success("Cache rebuild complete.");
    }
}
