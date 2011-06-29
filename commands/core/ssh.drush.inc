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
    'options' => array(
      'ssh-options' => 'A string of extra options that will be passed to the ssh command (e.g. "-p 100")',
    ),
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

  // Build up the command. TODO: We maybe refactor this soon.
  $hostname = drush_escapeshellarg($site['remote-host'], "LOCAL");
  $username = isset($site['remote-user']) ? drush_escapeshellarg($site['remote-user'], "LOCAL") . "@" : '';
  $ssh_options = isset($site['ssh-options']) ? $site['ssh-options'] : drush_get_option('ssh-options', "-o PasswordAuthentication=no");

  $cmd = "ssh " . $ssh_options . " " . $username . $hostname;
  if (!empty($command)) {
    $cmd .= " " . drush_escapeshellarg($command);
  }

  // Execute.
  if (drush_get_context('DRUSH_VERBOSE') || drush_get_context('DRUSH_SIMULATE')) {
    drush_print($cmd);
  }
  if (!drush_get_context('DRUSH_SIMULATE')) {
    proc_close(proc_open($cmd, array(0 => STDIN, 1 => STDOUT, 2 => STDERR), $pipes));
  }
}
