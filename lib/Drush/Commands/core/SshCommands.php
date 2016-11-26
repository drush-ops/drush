<?php
namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;
use Drush\Log\LogLevel;

class SshCommands extends DrushCommands {

  /**
   * Connect to a Drupal site's server via SSH for an interactive session or to run a shell command.
   *
   * @command site-ssh
   * @param string $bash Bash to execute on target. Optional, except when site-alias is a list.
   * @option cd Directory to change to if Drupal root is not desired (the default). Value should be a full path, or --no-cd for the ssh default (usually the remote user's home directory).
   * @handle-remote-commands
   * @strict-option-handling
   * @usage drush @mysite ssh
   *   Open an interactive shell on @mysite's server.
   * @usage drush @prod ssh ls /tmp
   *   Run "ls /tmp" on @prod site. If @prod is a site list, then ls will be executed on each site.
   * @usage drush @prod ssh git pull
   *   Run "git pull" on the Drupal root directory on the @prod site.
   * @aliases ssh
   * @bootstrap DRUSH_BOOTSTRAP_NONE
   * @topics docs-aliases
   */
  public function ssh($bash = '', $options = ['cd' => TRUE]) {
    // Get all of the args and options that appear after the command name.
    $args = drush_get_original_cli_args_and_options();
    // n.b. we do not escape the first (0th) arg to allow `drush ssh 'ls /path'`
    // to work in addition to the preferred form of `drush ssh ls /path`.
    // Supporting the legacy form means that we cannot give the full path to an
    // executable if it contains spaces.
    for ($x = 1; $x < count($args); $x++) {
      $args[$x] = drush_escapeshellarg($args[$x]);
    }
    $command = implode(' ', $args);

    if (!$alias = drush_get_context('DRUSH_TARGET_SITE_ALIAS')) {
      throw new \Exception('A site alias is required. The way you call ssh command has changed to `drush @alias ssh`.');
    }
    $site = drush_sitealias_get_record($alias);
    // If we have multiple sites, run ourselves on each one. Set context back when done.
    if (isset($site['site-list'])) {
      if (empty($command)) {
        throw new \Exception('A command is required when multiple site aliases are specified.');
        return;
      }
      foreach ($site['site-list'] as $alias_single) {
        drush_set_context('DRUSH_TARGET_SITE_ALIAS', $alias_single);
        drush_ssh_site_ssh($command);
      }
      drush_set_context('DRUSH_TARGET_SITE_ALIAS', $alias);
      return;
    }

    if (!drush_sitealias_is_remote_site($alias)) {
      // Local sites run their bash without SSH.
      $return = drush_invoke_process('@self', 'core-execute', array($bash), array('escape' => FALSE));
      return $return['object'];
    }

    // We have a remote site - build ssh command and run.
    $interactive = FALSE;
    $cd = $options['cd'];
    if (empty($command)) {
      $command = 'bash -l';
      $interactive = TRUE;
    }
    $cmd = drush_shell_proc_build($site, $command, $cd, $interactive);
    $status = drush_shell_proc_open($cmd);
    if ($status != 0) {
      throw new \Exception(dt('An error @code occurred while running the command `@command`', array('@command' => $cmd, '@code' => $status)));
    }
  }

  /**
   * @hook option site-ssh
   * @option ssh-options A string of extra options that will be passed to the ssh command (e.g. "-p 100")',
   * @option tty Create a tty (e.g. to run an interactive program).',
   * @option escaped Command string already escaped; do not add additional quoting.',
   *
   * @todo Reuse this for core-execute when that ports.
   */
  public function proc_build_options() {}
}