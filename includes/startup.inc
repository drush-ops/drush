<?php

/**
 * @file
 *   Functions used when Drush is starting up.
 *
 * This file is included and used early in the Drush
 * startup process, before any other Drush APIs are
 * available, and before the autoloader has been included.
 */

/**
 * Get the current enviornment.
 */
function drush_env() {
  // Fetch the current environment.  To ensure that
  // $_ENV is correctly populated, make sure that
  // the value of 'variables-order' in your php.ini
  // contains "E" ("Environment").  See:
  // http://us.php.net/manual/en/ini.core.php#ini.variables-order
  $env = $_ENV;

  // If PHP is not configured correctly, $_ENV will be
  // empty.  Drush counts on the fact that environment
  // variables will always be available, though, so we
  // need to repair this situation.  We can always access
  // individual environmnet values via getenv(); however,
  // there is no PHP API that will tell us all of the
  // available values, so we will get the environment
  // variable values using 'printenv'.
  if (empty($env)) {
    exec('printenv', $env_items);
    foreach ($env_items as $item) {
      // Each $item is 'key=value' or just 'key'.
      // If $item has no value, then explode will return
      // a single array, [0 => 'key'].  We add a default
      // value of [1 => 'value'] to cover this case.  If
      // explode returns two items, the default value is ignored.
      list($key, $value) = explode('=', $item, 2) + array(1 => '');
      $env[$key] = $value;
    }
  }

  return $env;
}

/**
 * Checks the provided location and return the appropriate
 * Drush wrapper or Drush launcher script, if found.
 *
 * If the provided location looks like it might be a web
 * root (i.e., it contains an index.php), then we will search
 * in a number of locations in the general vicinity of the
 * web root for a Drush executable.
 *
 * For other locations, we will look only in that specific
 * directory, or in vendor/bin.
 */
function find_wrapper_or_launcher($location) {
  if (file_exists($location. DIRECTORY_SEPARATOR. 'index.php')) {
    return find_wrapper_or_launcher_in_vicinity($location);
  }
  return find_wrapper_or_launcher_in_specific_locations($location, ["", 'vendor'. DIRECTORY_SEPARATOR. 'bin']);
}

/**
 * We look for a "Drush wrapper" script that might
 * be stored in the root of a site.  If there is
 * no wrapper script, then we look for the
 * drush.launcher script in vendor/bin.  We try just a
 * few of the most common locations; if the user relocates
 * their vendor directory anywhere else, then they must
 * use a wrapper script to locate it.  See the comment in
 * 'examples/drush' for details.
 */
function find_wrapper_or_launcher_in_vicinity($location) {
  $sep = DIRECTORY_SEPARATOR;
  $drush_locations = [
    "",
    "vendor{$sep}bin/",
    "..{$sep}vendor{$sep}bin{$sep}",
    "sites{$sep}all{$sep}vendor{$sep}bin{$sep}",
    "sites{$sep}all{$sep}vendor{$sep}drush{$sep}drush{$sep}",
    "sites{$sep}all{$sep}drush{$sep}drush{$sep}",
    "drush{$sep}drush{$sep}",
  ];

  return find_wrapper_or_launcher_in_specific_locations($location, $drush_locations);
}

function find_wrapper_or_launcher_in_specific_locations($location, $drush_locations) {
  $sep = DIRECTORY_SEPARATOR;
  foreach ($drush_locations as $d) {
    $found_script = find_wrapper_or_launcher_at_location("$location$sep$d");
    if (!empty($found_script)) {
      return $found_script;
    }
  }
  return "";
}

/**
 * We are somewhat "loose" about whether we are looking
 * for "drush" or "drush.launcher", because in old versions
 * of Drush, the "drush launcher" was named "drush".
 * Otherwise, there wouldn't be any point in looking for
 * "drush.launcher" at the root, or "drush" in a vendor directory.
 * We also allow users to rename their drush wrapper to
 * 'drush.wrapper' to avoid conflicting with a directory named
 * 'drush' at the site root.
 */
function find_wrapper_or_launcher_at_location($location) {
  $sep = DIRECTORY_SEPARATOR;
  // Sanity-check: empty $location means that we should search
  // at the cwd, not at the root of the filesystem.
  if (empty($location)) {
    $location = ".";
  }
  foreach (array('.launcher', '.wrapper', '') as $suffix) {
    $check_location = "$location{$sep}drush$suffix";
    if (is_file($check_location)) {
      return $check_location;
    }
  }
  return "";
}

/**
 * Determine whether current OS is a Windows variant.
 */
function drush_is_windows($os = NULL) {
  return strtoupper(substr($os ?: PHP_OS, 0, 3)) === 'WIN';
}

function drush_escapeshellarg($arg, $os = NULL, $raw = FALSE) {
  // Short-circuit escaping for simple params (keep stuff readable)
  if (preg_match('|^[a-zA-Z0-9.:/_-]*$|', $arg)) {
    return $arg;
  }
  elseif (drush_is_windows($os)) {
    return _drush_escapeshellarg_windows($arg, $raw);
  }
  else {
    return _drush_escapeshellarg_linux($arg, $raw);
  }
}

/**
 * Linux version of escapeshellarg().
 *
 * This is intended to work the same way that escapeshellarg() does on
 * Linux.  If we need to escape a string that will be used remotely on
 * a Linux system, then we need our own implementation of escapeshellarg,
 * because the Windows version behaves differently.
 */
function _drush_escapeshellarg_linux($arg, $raw = FALSE) {
  // For single quotes existing in the string, we will "exit"
  // single-quote mode, add a \' and then "re-enter"
  // single-quote mode.  The result of this is that
  // 'quote' becomes '\''quote'\''
  $arg = preg_replace('/\'/', '\'\\\'\'', $arg);

  // Replace "\t", "\n", "\r", "\0", "\x0B" with a whitespace.
  // Note that this replacement makes Drush's escapeshellarg work differently
  // than the built-in escapeshellarg in PHP on Linux, as these characters
  // usually are NOT replaced. However, this was done deliberately to be more
  // conservative when running _drush_escapeshellarg_linux on Windows
  // (this can happen when generating a command to run on a remote Linux server.)
  $arg = str_replace(array("\t", "\n", "\r", "\0", "\x0B"), ' ', $arg);

  // Only wrap with quotes when needed.
  if(!$raw) {
    // Add surrounding quotes.
    $arg = "'" . $arg . "'";
  }

  return $arg;
}

/**
 * Windows version of escapeshellarg().
 */
function _drush_escapeshellarg_windows($arg, $raw = FALSE) {
  // Double up existing backslashes
  $arg = preg_replace('/\\\/', '\\\\\\\\', $arg);

  // Double up double quotes
  $arg = preg_replace('/"/', '""', $arg);

  // Double up percents.
  // $arg = preg_replace('/%/', '%%', $arg);

  // Only wrap with quotes when needed.
  if(!$raw) {
    // Add surrounding quotes.
    $arg = '"' . $arg . '"';
  }

  return $arg;
}

/**
 * drush_startup is called once, by the Drush "finder"
 * script -- the "drush" script at the Drush root.
 * It finds the correct Drush "wrapper" or "launcher"
 * script to use, and executes it with process replacement.
 */
function drush_startup($argv) {
  $sep = DIRECTORY_SEPARATOR;
  $found_script = "";
  $cwd = getcwd();
  $home = getenv("HOME");
  $use_dir = "$home{$sep}.drush{$sep}use";

  // Get the arguments for the command.  Shift off argv[0],
  // which contains the name of this script.
  $arguments = $argv;
  array_shift($arguments);

  // We need to do at least a partial parsing of the options,
  // so that we can find --root / -r and so on.
  $VERBOSE=FALSE;
  $DEBUG=FALSE;
  $ROOT=FALSE;
  $COMMAND=FALSE;
  $ALIAS=FALSE;
  $VAR=FALSE;

  foreach ($arguments as $arg) {
    // If a variable to set was indicated on the
    // previous iteration, then set the value of
    // the named variable (e.g. "ROOT") to "$arg".
    if ($VAR) {
      $$VAR = "$arg";
      $VAR = FALSE;
    }
    else {
      switch ($arg) {
        case "-r":
          $VAR = "ROOT";
          break;

        case "-dv":
        case "-vd":
        case "--debug":
        case "-d":
          $DEBUG = TRUE;
          break;

        case "-dv":
        case "-vd":
        case "--verbose":
        case "-v":
          $VERBOSE = TRUE;
          break;
      }
      if (!$COMMAND && !$ALIAS && ($arg[0] == '@')) {
        $ALIAS = $arg;
      }
      elseif (!$COMMAND && ($arg[0] != '-')) {
        $COMMAND = $arg;
      }
      if (substr($arg, 0, 7) == "--root=") {
        $ROOT = substr($arg, 7);
      }
    }
  }

  $NONE=($ALIAS == "@none");

  // If we have found the site-local Drush script, then
  // do not search for it again; use the environment value
  // we set last time.
  $found_script = getenv('DRUSH_FINDER_SCRIPT');

  // If the @none alias is used, then we skip the Drush wrapper,
  // and call the Drush launcher directly.
  //
  // In this instance, we are assuming that the 'drush' that is being
  // called is:
  //
  //  a) The global 'drush', or
  //  b) A site-local 'drush' in a vendor/bin directory.
  //
  // In either event, the appropriate 'drush.launcher' should be right next
  // to this script (stored in the same directory).
  if (empty($found_script) && $NONE) {
    if (is_file(dirname(__DIR__) . "{$sep}drush.launcher")) {
      $found_script = dirname(__DIR__) . "{$sep}drush.launcher";
    }
    else {
      fwrite(STDERR, "Could not find drush.launcher in " . dirname(__DIR__) . ". Check your installation.\n");
      exit(1);
    }
  }

  // Check for a root option:
  //
  //   drush --root=/path
  //
  // If the site root is specified via a commandline option, then we
  // should always use the Drush stored at this root, if there is one.
  // We will first check for a "wrapper" script at the root, and then
  // we will look for a "launcher" script in vendor/bin.
  if (empty($found_script) && !empty($ROOT)) {
    $found_script = find_wrapper_or_launcher($ROOT);
    if (!empty($found_script)) {
      chdir($ROOT);
    }
  }

  // If there is a .drush-use file, then its contents will
  // contain the path to the Drush to use.
  if (empty($found_script)) {
    if (is_file(".drush-use")) {
      $found_script = trim(file_get_contents(".drush-use"));
    }
  }

  // Look for a 'drush' wrapper or launcher at the cwd,
  // and in each of the directories above the cwd.  If
  // we find one, use it.
  if (empty($found_script)) {
    $c = getcwd();
    // Windows can give us lots of different strings to represent the root
    // directory as it often includes the drive letter. If we get the same
    // result from dirname() twice in a row, then we know we're at the root.
    $last = '';
    while (!empty($c) && ($c != $last)) {
      $found_script = find_wrapper_or_launcher($c);
      if ($found_script) {
        chdir($c);
        break;
      }
      $last = $c;
      $c = dirname($c);
    }
  }

  if (!empty($found_script)) {
    $found_script = realpath($found_script);

    // Guard against errors:  if we have found a "drush" script
    // (that is, theoretically a drush wrapper script), and
    // there is a "drush.launcher" script in the same directory,
    // then we will skip the "drush" script and use the drush launcher
    // instead.  This is because drush "wrapper" scripts should
    // only ever exist at the root of a site, and there should
    // never be a drush "launcher" at the root of a site.
    // Therefore, if we find a "drush.launcher" next to a script
    // called "drush", we have probably found a Drush install directory,
    // not a site root.  Adjust appropriately.  Note that this
    // also catches the case where a drush "finder" script finds itself.
    if (is_file(dirname($found_script) . "{$sep}drush.launcher")) {
      $found_script = dirname($found_script) . "{$sep}drush.launcher";
    }
  }

  // Didn't find any site-local Drush, or @use'd Drush.
  // Skip the Bash niceties of the launcher and proceed to drush_main() in either case:
  // - No script was found and we are running a Phar
  // - The found script *is* the Phar https://github.com/drush-ops/drush/pull/2246.
  $phar_path = class_exists('Phar') ? Phar::running(FALSE) : '';
  if ((empty($found_script) && $phar_path) || !empty($found_script) && $found_script == $phar_path) {
    drush_run_main($DEBUG, $sep, "Phar detected. Proceeding to drush_main().");
  }

  // Didn't find any site-local Drush, or @use'd Drush, or Phar.
  // There should be a drush.launcher in same directory as this script.
  if (empty($found_script)) {
    $found_script = dirname(__DIR__) . "{$sep}drush.launcher";
  }

  if (drush_is_windows()) {
    // Sometimes we found launcher in /bin, and sometimes not. Adjust accordingly.
    if (strpos($found_script, 'bin')) {
      $found_script = dirname($found_script). $sep. 'drush.php.bat';
    }
    else {
      array_unshift($arguments, dirname($found_script). $sep. 'drush.php');
      $found_script = 'php';
    }
  }

  // Always use pcntl_exec if it exists.
  $use_pcntl_exec = function_exists("pcntl_exec") && (strpos(ini_get('disable_functions'), 'pcntl_exec') === FALSE);

  // If we have posix_getppid, then pass in the shell pid so
  // that 'site-set' et. al. can work correctly.
  if (function_exists('posix_getppid')) {
    putenv("DRUSH_SHELL_PID=" . posix_getppid());
  }

  // Set an environment variable indicating which script
  // the Drush finder found. If we end up re-entrantly calling
  // another Drush finder, then we will skip searching for
  // a site-local Drush, and always use the drush.launcher
  // found previously.  This environment variable typically should
  // not be set by clients.
  putenv("DRUSH_FINDER_SCRIPT=$found_script");

  // Emit a message in debug mode advertising the location of the
  // script we found.
  if ($DEBUG) {
    $launch_method = $use_pcntl_exec ? 'pcntl_exec' : 'proc_open';
    fwrite(STDERR, "Using the Drush script found at $found_script using $launch_method\n");
  }

  if ($use_pcntl_exec) {
    // Get the current environment for pnctl_exec.
    $env = drush_env();

    // Launch the new script in the same process.
    // If the launch succeeds, then it will not return.
    $error = pcntl_exec($found_script, $arguments, $env);
    if (!$error) {
      $errno = pcntl_get_last_error();
      $strerror = pcntl_strerror($errno);
      fwrite(STDERR, "Error has occurred executing the Drush script found at $found_script\n");
      fwrite(STDERR, "(errno {$errno}) $strerror\n");
    }
    exit(1);
  }
  else {
    $escaped_args = array_map(function($item) { return drush_escapeshellarg($item); }, $arguments);
    // Double quotes around $found_script as it can contain spaces.
    $cmd = drush_escapeshellarg($found_script). ' '. implode(' ', $escaped_args);
    if (drush_is_windows()) {
      // Windows requires double quotes around whole command.
      // @see https://bugs.php.net/bug.php?id=49139
      // @see https://bugs.php.net/bug.php?id=60181
      $cmd = '"'. $cmd. '"';
    }
    $process = proc_open($cmd, array(0 => STDIN, 1 => STDOUT, 2 => STDERR), $pipes, $cwd);
    $proc_status = proc_get_status($process);
    $exit_code = proc_close($process);
    exit($proc_status["running"] ? $exit_code : $proc_status["exitcode"] );
  }
}

/**
 * Run drush_main() and then exit. Used when we cannot hand over execution to
 * the launcher.
 *
 * @param bool $DEBUG
 *   Are we in debug mode
 * @param string $sep
 *   Directory separator
 * @param string $msg
 *   Debug message to log before running drush_main()
 */
function drush_run_main($DEBUG, $sep, $msg) {
// Emit a message in debug mode advertising how we proceeded.
  if ($DEBUG) {
    fwrite(STDERR, $msg. "\n");
  }
  require __DIR__ . "{$sep}preflight.inc";
  exit(drush_main());
}
