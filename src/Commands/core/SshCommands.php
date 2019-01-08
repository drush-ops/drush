<?php
namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteProcess\SiteProcess;
use Drush\Drush;

class SshCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    /**
     * Connect to a Drupal site's server via SSH.
     *
     * @command site:ssh
     * @option cd Directory to change to if Drupal root is not desired (the default).
     * @optionset_proc_build
     * @handle-remote-commands
     * @usage drush @mysite ssh
     *   Open an interactive shell on @mysite's server.
     * @usage drush @prod ssh ls /tmp
     *   Run "ls /tmp" on @prod site. If @prod is a site list, then ls will be executed on each site.
     * @usage drush @prod ssh git pull
     *   Run "git pull" on the Drupal root directory on the @prod site.
     * @aliases ssh,site-ssh
     * @topics docs:aliases
     */
    public function ssh(array $args, $options = ['cd' => true, 'tty' => false, 'legacy' => true])
    {
        $alias = $this->siteAliasManager()->getSelf();
        if ($alias->isNone()) {
            throw new \Exception('A site alias is required. The way you call ssh command has changed to `drush @alias ssh`.');
        }

        if (empty($args)) {
            $args[] = 'bash';
            $args[] = '-l';

            // We're calling an interactive 'bash' shell, so we want to
            // force tty to true.
            $options['tty'] = true;
        }

        // Legacy support: if there is only one argument provided, then
        // explode it. This may be disabled via the --no-legacy option.
        if ((count($args) == 1) && $options['legacy']) {
            $args = explode(' ', $args[0]);
        }

        $process = $this->processManager()->siteProcess($alias, $args);
        $process->setTty($options['tty']);
        $process->chdirToSiteRoot($options['cd']);
        $process->mustRun($process->showRealtime());
    }
}
