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
      'site-alias' => 'A remote site alias. Can be an alias list.',
      'bash' => 'Bash to execute on target. Optional, except when site-alias is a list.',
    ),
    'options' => drush_shell_exec_proc_build_options(),
    'handle-remote-commands' => TRUE,
    'mask-local-cli-options' => TRUE,
    'examples' => array(
      'drush @mysite ssh' => 'Open an interactive shell on @mysite\'s server.',
      'drush @prod ssh \'ls /tmp\'' => 'Run "ls /tmp" on @prod site. If @prod is a site list, then ls will be executed on each site.',
    ),
    'aliases' => array('ssh'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'topics' => array('docs-aliases'),
  );
  return $items;
}

/**
 * Command callback.
 */
function drush_ssh_site_ssh($command = NULL) {
  // The DRUSH_COMMAND_ARGS contain all args and options that appear after the command name.
  $args = drush_get_context('DRUSH_COMMAND_ARGS', array());
  // n.b. we do not escape the first (0th) arg to allow `drush ssh 'ls /path'`
  // to work in addition to the preferred form of `drush ssh ls /path`.
  // Supporting the legacy form means that we cannot give the full path to an
  // executable if it contains spaces.
  for ($x = 1; $x < sizeof($args); $x++) {
    $args[$x] = drush_escapeshellarg($args[$x]);
  }
  $command = implode(' ', $args);
  if (!$alias = drush_get_context('DRUSH_TARGET_SITE_ALIAS')) {
    return drush_set_error('DRUSH_MISSING_TARGET_ALIAS', 'A remote site alias is required.');
  }
  $site = drush_sitealias_get_record($alias);
  // We only accept remote aliases.
  if (empty($site['remote-host'])) {
    drush_set_error('DRUSH_SITE_SSH_REMOTE_ALIAS_REQUIRED', dt('@alias does not specify a remote-host.', array('@alias' => $alias)));
    return;
  }

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

  $cmd = drush_shell_proc_build($site, $command);
  $status = drush_shell_proc_open($cmd);
  if ($status != 0) {
    return drush_set_error('DRUSH_SITE_SSH_ERROR', dt('An error @code occurred while running the command `@command`', array('@command' => $cmd, '@code' => $status)));
  }
}
