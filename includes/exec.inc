<?php
// $Id$

/*
 * @file
 *   Functions for executing system commands. (e.g. exec(), system(), ...).
 */

/**
 * @defgroup commandwrappers Functions to execute commands.
 * @{
 */

/**
 * Calls 'system()' function, passing through all arguments unchanged.
 *
 * This should be used when calling possibly mutative or destructive functions
 * (e.g. unlink() and other file system functions) so that can be suppressed
 * if the simulation mode is enabled.
 *
 * @param $exec
 *   The shell command to execute.  Parameters should already be escaped.
 * @return
 *   The result code from system():  0 == success.
 *
 * @see drush_shell_exec()
 */
function drush_op_system($exec) {
  if (drush_get_context('DRUSH_VERBOSE') || drush_get_context('DRUSH_SIMULATE')) {
     drush_print("Calling system($exec);");
  }

  if (drush_get_context('DRUSH_SIMULATE')) {
    return 0;
  }

  // Throw away output.  Use drush_shell_exec() to capture output.
  system($exec, $result_code);

  return $result_code;
}

/**
 * Executes a shell command at a new working directory.
 * The old cwd is restored on exit.
 *
 * @param $effective_wd
 *   The new working directory to execute the shell command at.
 * @param $cmd
 *   The command to execute. May include placeholders used for sprintf.
 * @param ...
 *   Values for the placeholders specified in $cmd. Each of these will be passed through escapeshellarg() to ensure they are safe to use on the command line.
 * @return
 *   TRUE on success, FALSE on failure
 */
function drush_shell_cd_and_exec($effective_wd, $cmd) {
  $args = func_get_args();

  $effective_wd = array_shift($args);
  $cwd = getcwd();
  drush_op('chdir', $effective_wd);
  $result = call_user_func_array('drush_shell_exec', $args);
  drush_op('chdir', $cwd);
  return $result;
}

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
 * @return
 *   TRUE on success, FALSE on failure
 */
function drush_shell_exec($cmd) {
  return _drush_shell_exec(func_get_args());
}

/**
 * Executes a command in interactive mode.
 *
 * @see drush_shell_exec.
 */
function drush_shell_exec_interactive($cmd) {
  return _drush_shell_exec(func_get_args(), TRUE);
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
function _drush_shell_exec($args, $interactive = FALSE) {
  //do not change the command itself, just the parameters.
  for ($x = 1; $x < sizeof($args); $x++) {
    $args[$x] = drush_escapeshellarg($args[$x]);
  }
  $command = call_user_func_array('sprintf', $args);

  if (drush_get_context('DRUSH_VERBOSE') || drush_get_context('DRUSH_SIMULATE')) {
    drush_print('Executing: ' . $command);
  }

  if (!drush_get_context('DRUSH_SIMULATE')) {
    if ($interactive) {
      $result = proc_open($command, array(0 => STDIN, 1 => STDOUT, 2 => STDERR), $pipes);
      proc_close($result);
      // proc_open returns FALSE on failure, or a resource on success.
      return ($result === FALSE) ? FALSE : TRUE;
    }
    else {
      exec($command . ' 2>&1', $output, $result);
      _drush_shell_exec_output_set($output);

      if (drush_get_context('DRUSH_DEBUG')) {
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
 * Determine the appropriate os value for the
 * specified site record
 *
 * @returns
 *   NULL for 'same as local machine', 'Windows' or 'Linux'.
 */
function drush_os($site_record = NULL) {
  // Default to $os = NULL, meaning 'same as local machine'
  $os = NULL;
  // If the site record has an 'os' element, use it
  if (isset($site_record) && array_key_exists('os', $site_record)) {
    $os = $site_record['os'];
  }
  // Otherwise, we will assume that all remote machines are Linux
  elseif (isset($site_record) && array_key_exists('remote-host', $site_record)) {
    $os = drush_get_option('remote-os', 'Linux');
  }
  
  return $os;
}

/**
 * Platform-independent version of escapeshellarg().
 * This only works for local commands.
 * TODO: Make a unified drush_escapeshellarg
 * that works on Linux and Windows.
 */
function drush_escapeshellarg($arg, $os = NULL) {
  if (drush_is_windows($os)) {
    return _drush_escapeshellarg_windows($arg);
  }
  else {
    return escapeshellarg($arg);
  }
}

/**
 * Windows version of escapeshellarg().
 *
 * @deprecated escapeshellarg needs to be cross-platform,
 * because drush does not always know in advance whether an
 * escaped arg will be used locally or on a remote system.
 * See http://drupal.org/node/766080
 */
function _drush_escapeshellarg_windows($arg) {
  // Double the backslashes before any double quotes. Escape the double quotes.
  // (\" => \\\") && (" => \") =
  // (\" => \\") +
  $arg = preg_replace('/\\\"/', '\\\\\\"', $arg);
  // + (" => \")
  $arg = preg_replace('/"/', '\\"', $arg);

  // The same with single quotes.
  // (\' => \\\') && (' => \') =
  // (\' => \\') +
  $arg = preg_replace('/\\\'/', '\\\\\\\'', $arg);
  // + (' => \')
  $arg = preg_replace('/\'/', '\\\'', $arg);

  // Replace "\t", "\n", "\r", "\0", "\x0B" with a whitespace.
  $arg = str_replace(array("\t", "\n", "\r", "\0", "\x0B"), ' ', $arg);

  // Add surrounding quotes.
  $arg = '"' . $arg . '"';

  return $arg;
}

/**
 * Stores output for the most recent shell command.
 * This should only be run from drush_shell_exec().
 *
 * @param $output
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
 */
function drush_shell_exec_output() {
  return _drush_shell_exec_output_set();
}

/**
 * @} End of "defgroup commandwrappers".
 */
