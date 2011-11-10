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
    'required-arguments' => 1,
    'options' => drush_shell_exec_proc_build_options(),
    'examples' => array(
      'drush ssh @mysite' => 'Open an interactive shell on @mysite\'s server.',
      'drush ssh @prod \'ls /tmp\'' => 'Run "ls /tmp" on @prod site. If @prod is a site list, then ls will be executed on each site.',
    ),
    'aliases' => array('ssh'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'topics' => array('docs-aliases'),
  );
  return $items;
}

/**
 * Command argument complete callback.
 *
 * @return
 *   Array of available site aliases.
 */
function ssh_site_ssh_complete() {
  return array('values' => array_keys(_drush_sitealias_all_list()));
}

/**
 * Command callback.
 */
function drush_ssh_site_ssh($alias, $command = NULL) {
  $site = drush_sitealias_get_record($alias);

  // If we have multiple sites, run ourselves on each one.
  if (isset($site['site-list'])) {
    if (empty($command)) {
      drush_set_error('DRUSH_SITE_SSH_COMMAND_REQUIRED', dt('A command is required when multiple site aliases are specified.'));
      return;
    }
    foreach ($site['site-list'] as $alias) {
      drush_ssh_site_ssh($alias, $command);
    }
    return;
  }

  // We only accept remote aliases.
  if (empty($site['remote-host'])) {
    drush_set_error('DRUSH_SITE_SSH_REMOTE_ALIAS_REQUIRED', dt('@alias does not specify a remote-host.', array('@alias' => $alias)));
    return;
  }

  $cmd = drush_shell_proc_build($site, $command);
  drush_shell_proc_open($cmd);
}
