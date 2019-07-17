<?php

/**
 * @file
 *   Functions for executing system commands. (e.g. exec(), system(), ...).
 */

use Drush\Drush;
use Drush\Log\LogLevel;
use \Consolidation\SiteAlias\SiteAlias;

/**
 * @defgroup commandwrappers Functions to execute commands.
 * @{
 */


/**
 * Executes a shell command.
 * Output is only printed if in verbose mode.
 * Output is stored and can be retrieved using drush_shell_exec_output().
 * If in simulation mode, no action is taken.
 *
 * @param $cmd
 *   The command to execute. May include placeholders used for sprintf.
 * @param ...
 *   Values for the placeholders specified in $cmd. Each of these will be passed through escapeshellarg() to ensure they are safe to use on the command line.
 *
 * @deprecated See Drush::process().
 *
 * @return
 *   TRUE on success, FALSE on failure
 */
function drush_shell_exec($cmd) {
  return _drush_shell_exec(func_get_args(), FALSE, Drush::simulate());
}

/**
 * A version of drush_shell_exec() that ignores simulate mode
 *
 * @deprecated See Drush::process().
 */
function drush_always_exec($cmd) {
  return _drush_shell_exec(func_get_args(), FALSE, FALSE);
}

/**
 * Returns executable code for invoking preferred test editor.
 *
 * @see \Drush\Drupal\Commands\config\ConfigCommands::edit, among others.
 *
 */
function drush_get_editor() {
    // See http://drupal.org/node/1740294
    return '${VISUAL-${EDITOR-vi}} %s';
}

/**
 * Internal function: executes a shell command on the
 * local machine.  This function should not be used
 * in instances where ssh is utilized to execute a
 * command remotely; otherwise, remote operations would
 * fail if executed from a Windows machine to a remote
 * Linux server.
 *
 * @param $args
 *   The command and its arguments.
 * @param $interactive
 *   Whether to run in
 *
 * @return
 *   TRUE on success, FALSE on failure
 *
 * @see drush_shell_exec.
 */
function _drush_shell_exec($args, $interactive = FALSE, $simulate = false) {
  // Do not change the command itself, just the parameters.
  for ($x = 1; $x < count($args); $x++) {
    $args[$x] = drush_escapeshellarg($args[$x]);
  }
  // Important: we allow $args to take one of two forms here.  If
  // there is only one item in the array, it is the already-escaped
  // command string, but otherwise sprintf is used.  In the case
  // of pre-escaped strings, sprintf will fail if any of the escaped
  // parameters contain '%', so we must not call sprintf unless necessary.
  if (count($args) == 1) {
    $command = $args[0];
  }
  else {
    $command = call_user_func_array('sprintf', $args);
  }

  Drush::logger()->info('Executing: ' . $command);
  if (!$simulate) {
    if ($interactive) {
      $result = drush_shell_proc_open($command);
      return ($result == 0) ? TRUE : FALSE;
    }
    else {
      exec($command . ' 2>&1', $output, $result);
      _drush_shell_exec_output_set($output);

      if (Drush::debug()) {
        foreach ($output as $line) {
          drush_print($line, 2);
        }
      }

      // Exit code 0 means success.
      return ($result == 0);
    }
  }
  else {
    return TRUE;
  }
}

/**
 * Execute bash command using proc_open().
 *
 * @returns
 *   Exit code from launched application
 *     0 no error
 *     1 general error
 *     127 command not found
 *
 * @deprecated See Drush::process().
 */
function drush_shell_proc_open($cmd) {
  if (Drush::verbose() || Drush::simulate()) {
    drush_print("Calling proc_open($cmd);", 0, STDERR);
  }
  if (!Drush::simulate()) {
    $process = proc_open($cmd, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);
    $proc_status = proc_get_status($process);
    $exit_code = proc_close($process);
    return ($proc_status["running"] ? $exit_code : $proc_status["exitcode"] );
  }
  return 0;
}

/**
 * Determine the appropriate os value for the
 * specified site record
 *
 * @returns
 *   NULL for 'same as local machine', 'Windows' or 'Linux'.
 *
 * @deprecated. See \Consolidation\SiteAlias\SiteAlias::os
 */
function drush_os($site_record = NULL) {
  if (!$site_record instanceof SiteAlias) {
    return legacy_drush_os($site_record);
  }
  // n.b. $options['remote-os'] has become 'ssh.os' in drush.yml
  return drush_get_site_alias_config($site_record, 'ssh.os', 'Linux');
}

function drush_get_site_alias_config($site_record, $key, $default)
{
  $siteAliasWithConfig = SiteAliasWithConfig::create($site_record, Drush::config());

  return $siteAliasWithConfig->get($key, $default);
}

function legacy_drush_os($site_record = NULL) {
  // Default to $os = NULL, meaning 'same as local machine'
  $os = NULL;
  // If the site record has an 'os' element, use it
  if (isset($site_record) && array_key_exists('os', $site_record)) {
    $os = $site_record['os'];
  }
  // Otherwise, we will assume that all remote machines are Linux
  // (or whatever value 'remote-os' is set to in drush.yml).
  elseif (isset($site_record) && array_key_exists('remote-host', $site_record) && !empty($site_record['remote-host'])) {
    $os = Drush::config()->get('ssh.os', 'Linux');
  }

  return $os;
}

/**
 * Stores output for the most recent shell command.
 * This should only be run from drush_shell_exec().
 *
 * @param array|bool $output
 *   The output of the most recent shell command.
 *   If this is not set the stored value will be returned.
 */
function _drush_shell_exec_output_set($output = FALSE) {
  static $stored_output;
  if ($output === FALSE) return $stored_output;
  $stored_output = $output;
}

/**
 * Returns the output of the most recent shell command as an array of lines.
 *
 * @deprecated Use Drush:process() and Drush::drush. Output is retried via $process->getOutput().
 */
function drush_shell_exec_output() {
  return _drush_shell_exec_output_set();
}


/**
 * @} End of "defgroup commandwrappers".
 */
