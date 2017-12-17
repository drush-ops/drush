<?php
namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Drush\SiteAlias\SiteAliasManagerAwareTrait;

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
    public function ssh(array $args, $options = ['cd' => true])
    {
        // n.b. we do not escape the first (0th) arg to allow `drush ssh 'ls /path'`
        // to work in addition to the preferred form of `drush ssh ls /path`.
        // Supporting the legacy form means that we cannot give the full path to an
        // executable if it contains spaces.
        for ($x = 1; $x < count($args); $x++) {
            $args[$x] = drush_escapeshellarg($args[$x]);
        }
        $command = implode(' ', $args);

        $alias = $this->siteAliasManager()->getSelf();
        if ($alias->isNone()) {
            throw new \Exception('A site alias is required. The way you call ssh command has changed to `drush @alias ssh`.');
        }

        // Local sites run their bash without SSH.
        if (!$alias->isRemote()) {
            $return = drush_invoke_process('@self', 'core-execute', [$command], ['escape' => false]);
            return $return['object'];
        }

        // We have a remote site - build ssh command and run.
        $interactive = false;
        $cd = $options['cd'];
        if (empty($command)) {
            $command = 'bash -l';
            $interactive = true;
        }

        $cmd = drush_shell_proc_build($alias, $command, $cd, $interactive);
        $status = drush_shell_proc_open($cmd);
        if ($status != 0) {
            throw new \Exception(dt('An error @code occurred while running the command `@command`', ['@command' => $cmd, '@code' => $status]));
        }
    }
}
