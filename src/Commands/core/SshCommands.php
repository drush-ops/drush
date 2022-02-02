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
     * Connect to a Drupal site's server via SSH, and optionally run a shell
     * command.
     *
     * @command site:ssh
     * @param $code Code which should run at remote host.
     * @option cd Directory to change to. Defaults to Drupal root.
     * @optionset_proc_build
     * @handle-remote-commands
     * @usage drush @mysite ssh
     *   Open an interactive shell on @mysite's server.
     * @usage drush @prod ssh ls /tmp
     *   Run <info>ls /tmp</info> on <info>@prod</info> site.
     * @usage drush @prod ssh git pull
     *   Run <info>git pull</info> on the Drupal root directory on the <info>@prod</info> site.
     * @usage drush ssh git pull
     *   Run <info>git pull</info> on the local Drupal root directory.
     * @aliases ssh,site-ssh
     * @topics docs:aliases
     */
    public function ssh(array $code, $options = ['cd' => self::REQ, 'tty' => false]): void
    {
        $alias = $this->siteAliasManager()->getSelf();

        if (empty($code)) {
            $code[] = 'bash';
            $code[] = '-l';

            // We're calling an interactive 'bash' shell, so we want to
            // force tty to true.
            $options['tty'] = true;
        }

        if ((count($code) == 1)) {
            $code = [Shell::preEscaped($code[0])];
        }

        $process = $this->processManager()->siteProcess($alias, $code);
        $process->setTty($options['tty']);
        // The transport handles the chdir during processArgs().
        $fallback = $alias->hasRoot() ? $alias->root() : null;
        $process->setWorkingDirectory($options['cd'] ?: $fallback);
        $process->mustRun($process->showRealtime());
    }
}
