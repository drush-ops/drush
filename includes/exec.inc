<?php

/**
 * @file
 *   Functions for executing system commands. (e.g. exec(), system(), ...).
 */

use Drush\Drush;
use Drush\Log\LogLevel;
use \Drush\SiteAlias\AliasRecord;

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
  if (Drush::verbose() || Drush::simulate()) {
    drush_print("Calling system($exec);", 0, STDERR);
  }
  if (Drush::simulate()) {
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
  return _drush_shell_exec(func_get_args(), FALSE, Drush::simulate());
}

/**
 * A versopm pf drush_shell_exec that ignores simulate mode
 */
function drush_always_exec($cmd) {
  return _drush_shell_exec(func_get_args(), FALSE, FALSE);
}

/**
 * Returns executable code for invoking preferred test editor.
 *
 * The next line after calling this function is usually
 *   @code drush_shell_exec_interactive($exec, $filepath, $filepath) @endcode
 *
 * @see drush_config_edit()
 */
function drush_get_editor() {
  $bg = drush_get_option('bg') ? '&' : '';
  // see http://drupal.org/node/1740294
  $exec = drush_get_option('editor', '${VISUAL-${EDITOR-vi}}') . " %s $bg";
  return $exec;
}

/**
 * Executes a command in interactive mode.
 *
 * @see drush_shell_exec.
 */
function drush_shell_exec_interactive($cmd) {
  return _drush_shell_exec(func_get_args(), TRUE, Drush::simulate());
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

  drush_log('Executing: ' . $command, LogLevel::INFO);
  if (!$simulate) {
    if ($interactive) {
      $result = drush_shell_proc_open($command);
      return ($result == 0) ? TRUE : FALSE;
    }
    else {
      exec($command . ' 2>&1', $output, $result);
      _drush_shell_exec_output_set($output);

      if (drush_get_context('DRUSH_DEBUG_NOTIFY')) {
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
 * Determine whether 'which $command' can find
 * a command on this system.
 */
function drush_which($command) {
  exec("which $command 2>&1", $output, $result);
  return ($result == 0);
}

/**
 * Build an SSH string including an optional fragment of bash. Commands that use
 * this should also merge drush_shell_proc_build_options() into their
 * command options. @see ssh_drush_command().
 *
 * @param array $site
 *   A site alias record.
 * @param string $command
 *   An optional bash fragment.
 * @param string $cd
 *   An optional directory to change into before executing the $command. Set to
 *   boolean TRUE to change into $site['root'] if available.
 * @param boolean $interactive
 *   Force creation of a tty
 * @return string
 *   A string suitable for execution with drush_shell_remote_exec().
 *
 */
function drush_shell_proc_build(AliasRecord $site, $command = '', $cd = NULL, $interactive = FALSE) {
  // Build up the command. TODO: We maybe refactor this soon.
  $hostname = $site->remoteHostWithUser();
  $ssh_options = $site->getConfig(Drush::config(), 'ssh.options', "-o PasswordAuthentication=no");
  $os = drush_os($site);
  if ($site->get('tty') || $interactive) {
    $ssh_options .= ' -t';
  }

  $cmd = "ssh " . $ssh_options . " " . $hostname;

  if ($cd === TRUE) {
    if ($site->hasRoot()) {
      $cd = $site->root();
    }
    else {
      $cd = FALSE;
    }
  }
  if ($cd) {
    $command = 'cd ' . drush_escapeshellarg($cd, $os) . ' && ' . $command;
  }

  if (!empty($command)) {
    $cmd .= " " . drush_escapeshellarg($command, $os);
  }

  return $cmd;
}

/**
 * Execute bash command using proc_open().
 *
 * @returns
 *   Exit code from launched application
 *     0 no error
 *     1 general error
 *     127 command not found
 */
function drush_shell_proc_open($cmd) {
  if (Drush::verbose() || Drush::simulate()) {
    drush_print("Calling proc_open($cmd);", 0, STDERR);
  }
  if (!Drush::simulate()) {
    $process = proc_open($cmd, array(0 => STDIN, 1 => STDOUT, 2 => STDERR), $pipes);
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
 */
function drush_os($site_record = NULL) {
  if (!$site_record instanceof AliasRecord) {
    return legacy_drush_os($site_record);
  }
  // n.b. $options['remote-os'] has become 'ssh.os' in drush.yml
  return $site_record->getConfig(Drush::config(), 'ssh.os', 'Linux');
}

function legacy_drush_os($site_record = NULL) {
  // Default to $os = NULL, meaning 'same as local machine'
  $os = NULL;
  // If the site record has an 'os' element, use it
  if (isset($site_record) && array_key_exists('os', $site_record)) {
    $os = $site_record['os'];
  }
  // Otherwise, we will assume that all remote machines are Linux
  // (or whatever value 'remote-os' is set to in drushrc.php).
  elseif (isset($site_record) && array_key_exists('remote-host', $site_record) && !empty($site_record['remote-host'])) {
    $os = Drush::config()->get('ssh.os', 'Linux');
  }

  return $os;
}

/**
 * Make an attempt to simply wrap the arg with the
 * kind of quote characters it does not already contain.
 * If it contains both kinds, then this function reverts to drush_escapeshellarg.
 */
function drush_wrap_with_quotes($arg) {
  $has_double = strpos($arg, '"') !== FALSE;
  $has_single = strpos($arg, "'") !== FALSE;
  if ($has_double && $has_single) {
    return drush_escapeshellarg($arg);
  }
  elseif ($has_double) {
    return "'" . $arg . "'";
  }
  else {
    return '"' . $arg . '"';
  }
}

/**
 * Platform-dependent version of escapeshellarg().
 * Given the target platform, return an appropriately-escaped
 * string. The target platform may be omitted for args that
 * are /known/ to be for the local machine.
 * Use raw to get an unquoted version of the escaped arg.
 * Notice that you can't add quotes later until you know the platform.
 */

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
 */
function drush_shell_exec_output() {
  return _drush_shell_exec_output_set();
}

/**
 * Starts a background browser/tab for the current site or a specified URL.
 *
 * Uses a non-blocking proc_open call, so Drush execution will continue.
 *
 * @param $uri
 *   Optional URI or site path to open in browser. If omitted, or if a site path
 *   is specified, the current site home page uri will be prepended if the sites
 *   hostname resolves.
 * @return
 *   TRUE if browser was opened, FALSE if browser was disabled by the user or a,
 *   default browser could not be found.
 */
function drush_start_browser($uri = NULL, $sleep = FALSE, $port = FALSE, $browser = true) {
  if ($browser) {
    // We can only open a browser if we have a DISPLAY environment variable on
    // POSIX or are running Windows or OS X.
    if (!Drush::simulate() && !getenv('DISPLAY') && !drush_is_windows() && !drush_is_osx()) {
      drush_log(dt('No graphical display appears to be available, not starting browser.'), LogLevel::INFO);
      return FALSE;
    }
    $host = parse_url($uri, PHP_URL_HOST);
    if (!$host) {
      // Build a URI for the current site, if we were passed a path.
      $site = drush_get_context('DRUSH_URI');
      $host = parse_url($site, PHP_URL_HOST);
      $uri = $site . '/' . ltrim($uri, '/');
    }
    // Validate that the host part of the URL resolves, so we don't attempt to
    // open the browser for http://default or similar invalid hosts.
    $hosterror = (gethostbynamel($host) === FALSE);
    $iperror = (ip2long($host) && gethostbyaddr($host) == $host);
    if (!Drush::simulate() && ($hosterror || $iperror)) {
      drush_log(dt('!host does not appear to be a resolvable hostname or IP, not starting browser. You may need to use the --uri option in your command or site alias to indicate the correct URL of this site.', array('!host' => $host)), LogLevel::WARNING);
      return FALSE;
    }
    if ($port) {
      $uri = str_replace($host, "localhost:$port", $uri);
    }
    if ($browser === TRUE) {
      // See if we can find an OS helper to open URLs in default browser.
      if (drush_shell_exec('which xdg-open')) {
        $browser = 'xdg-open';
      }
      else if (drush_shell_exec('which open')) {
        $browser = 'open';
      }
      else if (!drush_has_bash()) {
        $browser = 'start';
      }
      else {
        // Can't find a valid browser.
        $browser = FALSE;
      }
    }
    $prefix = '';
    if ($sleep) {
      $prefix = 'sleep ' . $sleep . ' && ';
    }
    if ($browser) {
      drush_log(dt('Opening browser !browser at !uri', array('!browser' => $browser, '!uri' => $uri)));
      if (!Drush::simulate()) {
        $pipes = array();
        proc_close(proc_open($prefix . $browser . ' ' . drush_escapeshellarg($uri) . ' 2> ' . drush_bit_bucket() . ' &', array(), $pipes));
      }
      return TRUE;
    }
  }
  return FALSE;
}

/**
 * @} End of "defgroup commandwrappers".
 */
