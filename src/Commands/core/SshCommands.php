<?php
namespace Drush\Commands\core;

use Consolidation\SiteProcess\Util\Shell;
use Drush\Commands\DrushCommands;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;

class SshCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    /**
     * Connect to a Drupal site's server via SSH.
     *
     * @command site:ssh
     * @option cd Directory to change to. Defaults to Drupal root.
     * @optionset_proc_build
     * @handle-remote-commands
     * @usage drush @mysite ssh
     *   Open an interactive shell on @mysite's server.
     * @usage drush @prod ssh ls /tmp
     *   Run "ls /tmp" on @prod site.
     * @usage drush @prod ssh git pull
     *   Run "git pull" on the Drupal root directory on the @prod site.
     * @aliases ssh,site-ssh
     * @topics docs:aliases
     */
    public function ssh(array $args, $options = ['cd' => self::REQ, 'tty' => false])
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

        if ((count($args) == 1)) {
            $args = [Shell::preEscaped($args[0])];
        }

        $process = $this->processManager()->siteProcess($alias, $args);
        $process->setTty($options['tty']);
        // The transport handles the chdir during processArgs().
        $fallback = $alias->hasRoot() ? $alias->root() : null;
        $process->setWorkingDirectory($options['cd'] ?: $fallback);
        $process->mustRun($process->showRealtime());
    }
}
