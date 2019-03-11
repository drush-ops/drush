<?php

/**
* @file
*  The drush site-ssh command for connecting to a remote alias' server via
*  SSH, either for an interactive session or to run a shell command.
*/

function ssh_drush_command() {
  $items['site-ssh'] = array(
    'description' => 'Connect to a Drupal site\'s server via SSH for an interactive session or to run a shell command',
    'arguments' => array(
      'bash' => 'Bash to execute on target. Optional, except when site-alias is a list.',
    ),
    'options' => array(
      'cd' => "Directory to change to. Use a full path, TRUE for the site's Drupal root directory, or --no-cd for the ssh default (usually the remote user's home directory). Defaults to the Drupal root.",
    ) + drush_shell_exec_proc_build_options(),
    'handle-remote-commands' => TRUE,
    'strict-option-handling' => TRUE,
    'examples' => array(
      'drush @mysite ssh' => 'Open an interactive shell on @mysite\'s server.',
      'drush @prod ssh ls /tmp' => 'Run "ls /tmp" on @prod site. If @prod is a site list, then ls will be executed on each site.',
      'drush @prod ssh git pull' => 'Run "git pull" on the Drupal root directory on the @prod site.',
    ),
    'aliases' => array('ssh', 'site:ssh'),
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'topics' => array('docs-aliases'),
  );
  return $items;
}

/**
 * Command callback.
 */
function drush_ssh_site_ssh($command = NULL) {
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
    return drush_set_error('DRUSH_MISSING_TARGET_ALIAS', 'A site alias is required. The way you call ssh command has changed to `drush @alias ssh`.');
  }
  $site = drush_sitealias_get_record($alias);
  // If we have multiple sites, run ourselves on each one. Set context back when done.
  if (isset($site['site-list'])) {
    if (empty($command)) {
      drush_set_error('DRUSH_SITE_SSH_COMMAND_REQUIRED', dt('A command is required when multiple site aliases are specified.'));
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
    $return = drush_invoke_process('@self', 'core-execute', array($command), array('escape' => FALSE));
    return $return['object'];
  }

  // We have a remote site - build ssh command and run.
  $interactive = FALSE;
  $cd = drush_get_option('cd', TRUE);
  if (empty($command)) {
    $command = 'bash -l';
    $interactive = TRUE;
  }
  $cmd = drush_shell_proc_build($site, $command, $cd, $interactive);
  $status = drush_shell_proc_open($cmd);
  if ($status != 0) {
    return drush_set_error('DRUSH_SITE_SSH_ERROR', dt('An error @code occurred while running the command `@command`', array('@command' => $cmd, '@code' => $status)));
  }
}
