<?php

/**
 * @file
 * Functions used by drush to query the environment and
 * setting the current configuration.
 *
 * Bootstrapping now occurs in bootstrap.inc.
 *
 * @see includes/bootstrap.inc
 */

/**
 * Supported version of Console Table. This is displayed in the manual install help.
 */
define('DRUSH_TABLE_VERSION', '1.1.3');

/**
 * Base URL for automatic file download of PEAR packages.
 */
define('DRUSH_PEAR_BASE_URL', 'http://download.pear.php.net/package/');

/**
 * Log PHP errors to the Drush log. This is in effect until Drupal's error
 * handler takes over.
 */
function drush_error_handler($errno, $message, $filename, $line, $context) {
  // E_DEPRECATED was added in PHP 5.3. Drupal 6 will not fix all the
  // deprecated errors, but suppresses them. So we suppress them as well.
  if (defined('E_DEPRECATED')) {
    $errno = $errno & ~E_DEPRECATED;
  }

  // "error_reporting" is usually set in php.ini, but may be changed by
  // drush_errors_on() and drush_errors_off().
  if ($errno & error_reporting()) {
    // By default we log notices.
    $type = drush_get_option('php-notices', 'notice');

    // Bitmask value that constitutes an error needing to be logged.
    $error = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;
    if ($errno & $error) {
      $type = 'error';
    }

    // Bitmask value that constitutes a warning being logged.
    $warning = E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING | E_USER_WARNING;
    if ($errno & $warning) {
      $type = 'warning';
    }

    drush_log($message . ' ' . basename($filename) . ':' . $line, $type);

    return TRUE;
  }
}

/*
 * Returns a localizable message about php.ini that
 * varies depending on whether the php_ini_loaded_file()
 * is available or not.
 */
function _drush_php_ini_loaded_file_message() {
  if (function_exists('php_ini_loaded_file')) {
    return dt('Please check your configuration settings in !phpini or in your drush.ini file; see examples/example.drush.ini for details.', array('!phpini' => php_ini_loaded_file()));
  }
  else {
    return dt('Please check your configuration settings in your php.ini file or in your drush.ini file; see examples/example.drush.ini for details.');
  }
}

/**
 * Evalute the environment after an abnormal termination and
 * see if we can determine any configuration settings that the user might
 * want to adjust.
 */
function _drush_postmortem() {
  // Make sure that the memory limit has been bumped up from the minimum default value of 32M.
  $php_memory_limit = drush_memory_limit();
  if (($php_memory_limit > 0) && ($php_memory_limit <= 32*DRUSH_DRUPAL_KILOBYTE*DRUSH_DRUPAL_KILOBYTE)) {
    drush_set_error('DRUSH_MEMORY_LIMIT', dt('Your memory limit is set to !memory_limit; drush needs as much memory to run as Drupal.  !php_ini_msg', array('!memory_limit' => $php_memory_limit / (DRUSH_DRUPAL_KILOBYTE*DRUSH_DRUPAL_KILOBYTE) . 'M', '!php_ini_msg' => _drush_php_ini_loaded_file_message())));
  }
}

/**
 * Evaluate the environment before command bootstrapping
 * begins.  If the php environment is too restrictive, then
 * notify the user that a setting change is needed and abort.
 */
function _drush_environment_check_php_ini() {
  $ini_checks = array('safe_mode' => '', 'open_basedir' => '', 'disable_functions' => array('exec', 'system'), 'disable_classes' => '');

  // Test to insure that certain php ini restrictions have not been enabled
  $prohibited_list = array();
  foreach ($ini_checks as $prohibited_mode => $disallowed_value) {
    $ini_value = ini_get($prohibited_mode);
    $invalid_value = FALSE;
    if (empty($disallowed_value)) {
      $invalid_value = !empty($ini_value);
    }
    else {
      foreach ($disallowed_value as $test_value) {
        if (strstr($ini_value, $test_value) !== FALSE) {
          $invalid_value = TRUE;
        }
      }
    }
    if ($invalid_value) {
      $prohibited_list[] = $prohibited_mode;
    }
  }
  if (!empty($prohibited_list)) {
    drush_log(dt('The following restricted PHP modes have non-empty values: !prohibited_list. This configuration is incompatible with drush.  !php_ini_msg', array('!prohibited_list' => implode(' and ', $prohibited_list), '!php_ini_msg' => _drush_php_ini_loaded_file_message())), 'error');
  }

  return TRUE;
}

/*
 * Check for the existence of the specified lib directory, and create if needed.
 */
function drush_environment_lib() {
  $lib = drush_get_option('lib', DRUSH_BASE_PATH . '/lib');
  drush_mkdir($lib);
  if (!is_dir($lib)) {
    return FALSE;
  }
}

function drush_environment_table_lib() {
  // Try using the PEAR installed version of Console_Table.
  $tablefile = 'Console/Table.php';
  if (@file_get_contents($tablefile, FILE_USE_INCLUDE_PATH) === FALSE) {
    $lib = drush_get_option('lib', DRUSH_BASE_PATH . '/lib');
    $tablefile = $lib . '/Console_Table-' . DRUSH_TABLE_VERSION . '/Table.php';
    // If it is not already present, download Console Table.
    if (!drush_file_not_empty($tablefile)) {
      // Attempt to remove the old Console Table file, from the legacy location.
      // TODO: Remove this (and associated .git.ignore) in Drush 6.x.
      $tablefile_legacy = DRUSH_BASE_PATH . '/includes/table.inc';
      if (drush_file_not_empty($tablefile_legacy)) {
        drush_op('unlink', $tablefile_legacy);
      }

      // Download and extract Console_Table, and confirm success.
      if (drush_lib_fetch(DRUSH_PEAR_BASE_URL . 'Console_Table-' . DRUSH_TABLE_VERSION . '.tgz')) {
        // Remove unneccessary package.xml file which ends up in /lib.
        drush_op('unlink', $lib . '/package.xml');
      }
      if (!drush_file_not_empty($tablefile)) {
        return drush_bootstrap_error('DRUSH_TABLES_LIB_NOT_FOUND', dt("Drush needs a copy of the PEAR Console_Table library in order to function, and the attempt to download this file automatically failed. To continue you will need to download the !version package from http://pear.php.net/package/Console_Table, extract it into !lib directory, such that Table.php exists at !tablefile.", array('!version' => DRUSH_TABLE_VERSION, '!tablefile' => $tablefile, '!lib' => $lib)));
      }
    }
  }
  require_once $tablefile;
}

/**
 * Returns the current working directory.
 *
 * This is the directory as it was when drush was started, not the
 * directory we are currently in. For that, use getcwd() directly.
 */
function drush_cwd() {
  if ($path = drush_get_context('DRUSH_OLDCWD')) {
    return $path;
  }
  // We use PWD if available because getcwd() resolves symlinks, which
  // could take us outside of the Drupal root, making it impossible to find.
  // $_SERVER['PWD'] isn't set on windows and generates a Notice.
  $path = isset($_SERVER['PWD']) ? $_SERVER['PWD'] : '';
  if (empty($path)) {
    $path = getcwd();
  }

  // Convert windows paths.
  $path = _drush_convert_path($path);

  // Save original working dir case some command wants it.
  drush_set_context('DRUSH_OLDCWD', $path);

  return $path;
}

/**
 * Converts a Windows path (dir1\dir2\dir3) into a Unix path (dir1/dir2/dir3).
 * Also converts a cygwin "drive emulation" path (/cygdrive/c/dir1) into a
 * proper drive path, still with Unix slashes (c:/dir1).
 */
function _drush_convert_path($path) {
  $path = str_replace('\\','/', $path);
  $path = preg_replace('/^\/cygdrive\/([A-Za-z])(.*)$/', '\1:\2', $path);

  return $path;
}

/**
 * Returns parent directory.
 *
 * @param string
 *   Path to start from.
 *
 * @return string
 *   Parent path of given path.
 */
function _drush_shift_path_up($path) {
  if (empty($path)) {
    return FALSE;
  }
  $path = explode('/', $path);
  // Move one directory up.
  array_pop($path);
  return implode('/', $path);
}

/**
 * Like Drupal conf_path, but searching from beneath.
 * Allows proper site uri detection in site sub-directories.
 *
 * Essentially looks for a settings.php file.  Drush uses this
 * function to find a usable site based on the user's current
 * working directory.
 *
 * @param string
 *   Search starting path. Defaults to current working directory.
 *
 * @return
 *   Current site path (folder containing settings.php) or FALSE if not found.
 */
function drush_site_path($path = NULL) {
  $site_path = FALSE;

  $path = empty($path) ? drush_cwd() : $path;
  // Check the current path.
  if (file_exists($path . '/settings.php')) {
    $site_path = $path;
  }
  else {
    // Move up dir by dir and check each.
    while ($path = _drush_shift_path_up($path)) {
      if (file_exists($path . '/settings.php')) {
        $site_path = $path;
        break;
      }
    }
  }

  $site_root = drush_get_context('DRUSH_SELECTED_DRUPAL_ROOT');
  if (file_exists($site_root . '/sites/sites.php')) {
    $sites = array();
    // This will overwrite $sites with the desired mappings.
    include($site_root . '/sites/sites.php');
    // We do a reverse lookup here to determine the URL given the site key.
    if ($match = array_search($site_path, $sites)) {
      $site_path = $match;
    }
  }

  // Last resort: try from site root
  if (!$site_path) {
    if ($site_root) {
      if (file_exists($site_root . '/sites/default/settings.php')) {
        $site_path = $site_root . '/sites/default';
      }
    }
  }

  return $site_path;
}

/**
 * This is a copy of Drupal's conf_path function, taken from D7 and
 * adjusted slightly to search from the selected Drupal Root.
 *
 * Drush uses this routine to find a usable site based on a URI
 * passed in via a site alias record or the --uri commandline option.
 *
 * Drush uses Drupal itself (specifically, the Drupal conf_path function)
 * to bootstrap the site itself.  If the implementation of conf_path
 * changes, the site should still bootstrap correctly; the only consequence
 * of this routine not working is that drush configuration files
 * (drushrc.php) stored with the site's settimight not be found.
 */
function drush_conf_path($server_uri, $require_settings = TRUE) {
  $drupal_root = drush_get_context('DRUSH_SELECTED_DRUPAL_ROOT');
  if(!isset($drupal_root) || !isset($server_uri)) {
    return NULL;
  }
  $parsed_uri = parse_url($server_uri);
  if (is_array($parsed_uri) && !array_key_exists('scheme', $parsed_uri)) {
    $parsed_uri = parse_url('http://' . $server_uri);
  }
  if (!is_array($parsed_uri)) {
    return NULL;
  }
  $server_host = $parsed_uri['host'];
  if (array_key_exists('path', $parsed_uri)) {
    $server_uri = $parsed_uri['path'] . '/index.php';
  }
  else {
    $server_uri = "/index.php";
  }
  $confdir = 'sites';

  $sites = array();
  if (file_exists($drupal_root . '/' . $confdir . '/sites.php')) {
    // This will overwrite $sites with the desired mappings.
    include($drupal_root . '/' . $confdir . '/sites.php');
  }

  $uri = explode('/', $server_uri);
  $server = explode('.', implode('.', array_reverse(explode(':', rtrim($server_host, '.')))));
  for ($i = count($uri) - 1; $i > 0; $i--) {
    for ($j = count($server); $j > 0; $j--) {
      $dir = implode('.', array_slice($server, -$j)) . implode('.', array_slice($uri, 0, $i));
      if (isset($sites[$dir]) && file_exists($drupal_root . '/' . $confdir . '/' . $sites[$dir])) {
        $dir = $sites[$dir];
      }
      if (file_exists($drupal_root . '/' . $confdir . '/' . $dir . '/settings.php') || (!$require_settings && file_exists(DRUPAL_ROOT . '/' . $confdir . '/' . $dir))) {
        $conf = "$confdir/$dir";
        return $conf;
      }
    }
  }
  $conf = "$confdir/default";
  return $conf;
}

/**
 * Exhaustive depth-first search to try and locate the Drupal root directory.
 * This makes it possible to run drush from a subdirectory of the drupal root.
 *
 * @param
 *   Search start path. Defaults to current working directory.
 * @return
 *   A path to drupal root, or FALSE if not found.
 */
function drush_locate_root($start_path = NULL) {
  $drupal_root = FALSE;

  $start_path = empty($start_path) ? drush_cwd() : $start_path;
  foreach (array(TRUE, FALSE) as $follow_symlinks) {
    $path = $start_path;
    if ($follow_symlinks && is_link($path)) {
      $path = realpath($path);
    }
    // Check the start path.
    if (drush_valid_drupal_root($path)) {
      $drupal_root = $path;
      break;
    }
    else {
      // Move up dir by dir and check each.
      while ($path = _drush_shift_path_up($path)) {
        if ($follow_symlinks && is_link($path)) {
          $path = realpath($path);
        }
        if (drush_valid_drupal_root($path)) {
          $drupal_root = $path;
          break 2;
        }
      }
    }
  }

  return $drupal_root;
}

/**
 * Checks whether given path qualifies as a Drupal root.
 *
 * @param string
 *   Path to check.
 *
 * @return string
 *   The relative path to common.inc (varies by Drupal version), or FALSE if not
 *   a Drupal root.
 */
function drush_valid_drupal_root($path) {
  if (!empty($path) && is_dir($path)) {
    $candidates = array('includes/common.inc', 'core/includes/common.inc');
    foreach ($candidates as $candidate) {
      if (file_exists($path . '/' . $candidate)) {
        return $candidate;
      }
    }
  }
  return FALSE;
}

/**
 * Tests the currently loaded database credentials to ensure a database connection can be made.
 */
function drush_valid_db_credentials() {
  $creds = drush_get_context('DRUSH_DB_CREDENTIALS');
  // Do minimal checking that we have the necessary information.
  if (count($creds) == 0) {
    return FALSE;
  }
  $type = $creds['driver'];
  switch (drush_drupal_major_version()) {
    case 6:
      // Check availability of db extension in PHP and also Drupal support.
      if (file_exists('./includes/install.'. $type .'.inc')) {
        require_once './includes/install.'. $type .'.inc';
        $function = $type .'_is_available';
        if (!$function()) {
          drush_log(dt('!type extension for PHP is not installed. Check your php.ini to see how you can enable it.', array('!type' => $type)), 'bootstrap');
          return FALSE;
        }
      }
      else {
        drush_log('!type database type is unsupported.', array('!type' => $type), 'bootstrap');
        return FALSE;
      }
      // Verify connection settings.
      switch ($type) {
        case 'mysql':
          $hostspec = $creds['port'] ? $creds['host'] . ':' . $creds['port'] : $creds['host'];
          $connection = @mysql_connect($hostspec, $creds['user'], $creds['pass']);
          if (!$connection || !mysql_select_db($creds['name'])) {
            drush_log(mysql_error(), 'bootstrap');
            return FALSE;
          }
          break;
        case 'mysqli':
          $connection = mysqli_init();
          @mysqli_real_connect($connection, $creds['host'], $creds['user'], $creds['pass'], $creds['name'], (int)$creds['port']);
          if (mysqli_connect_errno() > 0) {
            drush_log(mysqli_connect_error(), 'bootstrap');
            return FALSE;
          }
          break;
        case 'pgsql':
          $conn_string = sprintf("host=%s user=%s password=%s dbname=%s", $creds['host'], $creds['user'], $creds['pass'], $creds['name']);
          if (isset($creds['port'])) {
            $conn_string .= ' port=' . $creds['port'];
          }
          // Copied from d6's database.pgsql.inc:
          // pg_last_error() does not return a useful error message for database
          // connection errors. We must turn on error tracking to get at a good error
          // message, which will be stored in $php_errormsg.
          $php_errormsg = '';
          $track_errors_previous = ini_get('track_errors');
          ini_set('track_errors', 1);
          $connection = @pg_connect($conn_string);
          // Restore error tracking setting
          ini_set('track_errors', $track_errors_previous);
          if (!$connection) {
            if (empty($php_errormsg)) {
              drush_log(dt("Unknown error connecting to pgsql database via !constr", array('!constr' => $conn_string)), 'bootstrap');
            }
            else {
              require_once './includes/unicode.inc';
              drush_log(decode_entities($php_errormsg), 'bootstrap');
            }
            return FALSE;
          }
          break;
      }
      break;
    case 7:
    default:
      // Drupal >=7 requires PDO and drush requires php 5.2, that ships with PDO
      // but it may be compiled with --disable-pdo.
      if (!class_exists('PDO')) {
        drush_log(dt('PDO support is required.'), 'bootstrap');
        return FALSE;
      }
      // Check the database specific driver is available.
      if (!in_array($type, PDO::getAvailableDrivers())) {
        drush_log(dt('!type extension for PHP PDO is not installed. Check your php.ini to see how you can enable it.', array('!type' => $type)), 'bootstrap');
        return FALSE;
      }
      // Build the connection string.
      if ($type === 'sqlite') {
        $constr = 'sqlite:' . $creds['name'];
      }
      elseif ($type === 'sqlsrv') {
        $server = $creds['host'];
        if (!empty($creds['port'])) {
          $server .= ", " . $creds['port'];
        }
        $constr = sprintf("%s:database=%s;server=%s", $type, $creds['name'], $server);
      }
      else {
        $constr = sprintf("%s:dbname=%s", $type, $creds['name']);
        // Use unix_socket if set instead of host:port.
        if (!empty($creds['unix_socket'])) {
          $constr .= sprintf(";unix_socket=%s", $creds['unix_socket']);
        }
        else {
          $constr .= sprintf(";host=%s", $creds['host']);
          if (!empty($creds['port'])) {
            $constr .= sprintf(";port=%d", $creds['port']);
          }
        }
      }
      try {
        $db = new PDO($constr, $creds['user'], $creds['pass']);
        $db = null;
      }
      catch (PDOException $e) {
        drush_log($e->getMessage(), 'bootstrap');
        return FALSE;
      }
      break;
  }

  return TRUE;
}

/**
 * Determine a proper way to call drush again
 *
 * This check if we were called directly or as an argument to some
 * wrapper command (php and sudo are checked now).
 *
 * Calling ./drush.php directly yields the following environment:
 *
 * _SERVER["argv"][0] => ./drush.php
 *
 * Calling php ./drush.php also yields the following:
 *
 * _SERVER["argv"][0] => ./drush.php
 *
 * Note that the $_ global is defined only in bash and therefore cannot
 * be relied upon.
 *
 * The DRUSH_COMMAND constant is initialised to the value of this
 * function when environment.inc is loaded.
 *
 * @see DRUSH_COMMAND
 */
function drush_find_drush() {
  $drush = realpath($_SERVER['argv']['0']);
  // TODO: On Windows, if we leave $drush as-is, then callbacks will
  // be done just as we were called by the batch file:  php.exe C:\path\drush.php
  // We could also convert drush.php to drush.bat to run the batch file again,
  // but this works just as well.
  return $drush;
}

/**
 * Verify that we are running PHP through the command line interface.
 *
 * This function is useful for making sure that code cannot be run via the web server,
 * such as a function that needs to write files to which the web server should not have
 * access to.
 *
 * @return
 *   A boolean value that is true when PHP is being run through the command line,
 *   and false if being run through cgi or mod_php.
 */
function drush_verify_cli() {
  return (php_sapi_name() == 'cli' || (is_numeric($_SERVER['argc']) && $_SERVER['argc'] > 0));
}

/**
 * Build a drush command suitable for use for drush to call itself
 * e.g. in backend_invoke.
 */
function drush_build_drush_command($drush_path = NULL, $php = NULL, $os = NULL, $remote_command = FALSE) {
  $os = _drush_get_os($os);
  $additional_options = '';
  if (is_null($drush_path)) {
    if (!$remote_command) {
      $drush_path = DRUSH_COMMAND;
    }
    else {
      $drush_path = drush_is_windows($os) ? 'drush.bat' : 'drush';
    }
  }
  // If the path to drush points to drush.php, then we will need to
  // run it via php rather than direct execution.  By default, we
  // will use 'php' unless something more specific was passed in
  // via the --php flag.
  if (substr($drush_path, -4) == ".php") {
    if (!isset($php)) {
      $php = drush_get_option('php');
      if (!isset($php)) {
        $php = 'php';
      }
    }
    if (isset($php) && ($php != "php")) {
      $additional_options .= ' --php=' . drush_escapeshellarg($php, $os);
    }
    // We will also add in the php options from --php-options
    $php = drush_escapeshellarg($php, $os) . ' ';
    $php_options = drush_get_option('php-options','');
    if (!empty($php_options)) {
      $php .= $php_options . ' ';
      $additional_options .= ' --php-options=' . drush_escapeshellarg($php_options, $os);
    }
  }
  else {
    $php = '';
  }
  return $php . drush_escapeshellarg($drush_path, $os) . $additional_options;
}

/**
 * Check if the operating system is Windows.
 * This will return TRUE under DOS, Powershell
 * Cygwin and MSYSGIT shells, so test for the
 * Windows variant FIRST if you care.
 */
function drush_is_windows($os = NULL) {
  return _drush_test_os($os, array("WIN","CYGWIN","CWRSYNC","MINGW"));
}

/**
 * Check if the operating system is Winodws
 * running some variant of cygwin -- either
 * Cygwin or the MSYSGIT shell.  If you care
 * which is which, test mingw first.
 */
function drush_is_cygwin($os = NULL) {
  return _drush_test_os($os, array("CYGWIN","CWRSYNC","MINGW"));
}

function drush_is_mingw($os = NULL) {
  return _drush_test_os($os, array("MINGW"));
}

/**
 * Return the OS we are running under.
 *
 * @return string
 *   Linux
 *   WIN* (e.g. WINNT)
 *   CYGWIN
 *   MINGW* (e.g. MINGW32)
 */
function _drush_get_os($os = NULL) {
  // The special os "CWRSYNC" can be used to indicate that we are testing
  // a path that will be passed as an argument to cwRsync, which requires
  // that the path be converted to /cygdrive/c/path, even on DOS or Powershell.
  // The special os "RSYNC" can be used to indicate that we want to assume
  // "CWRSYNC" when cwrsync is installed, or default to the local OS otherwise.
  if (strtoupper($os) == "RSYNC") {
    $os = _drush_get_os("LOCAL");
    // For now we assume that cwrsync is always installed on Windows, and never installed son any other platform.
    return drush_is_windows($os) ? "CWRSYNC" : $os;
  }
  // We allow "LOCAL" to document, in instances where some parameters are being escaped
  // for use on a remote machine, that one particular parameter will always be used on
  // the local machine (c.f. drush_backend_invoke).
  if (isset($os) && ($os != "LOCAL")) {
    return $os;
  }
  if (_drush_test_os(getenv("MSYSTEM"), array("MINGW"))) {
    return getenv("MSYSTEM");
  }
  // QUESTION: Can we differentiate between DOS and POWERSHELL? They appear to have the same environment.
  // At the moment, it does not seem to matter; they behave the same from PHP.
  // At this point we will just return PHP_OS.
  return PHP_OS;
}

function _drush_test_os($os, $os_list_to_check) {
  $os = _drush_get_os($os);
  foreach ($os_list_to_check as $test) {
    if (strtoupper(substr($os, 0, strlen($test))) == strtoupper($test)) {
      return TRUE;
    }
  }
  return FALSE;
}

/**
 * Read the drush info file.
 */
function drush_read_drush_info() {
  $drush_info_file = dirname(__FILE__) . '/../drush.info';

  return parse_ini_file($drush_info_file);
}

/**
 * Make a determination whether or not the given
 * host is local or not.
 *
 * @param host
 *   A hostname, 'localhost' or '127.0.0.1'.
 * @return
 *   True if the host is local.
 */
function drush_is_local_host($host) {
  // In order for this to work right, you must use 'localhost' or '127.0.0.1'
  // or the machine returned by 'uname -n' for your 'remote-host' entry in
  // your site alias.
  if (($host == 'localhost') || ($host == '127.0.0.1')) {
    return TRUE;
  }

  // If uname -n returns an fqdn (that is, uname -n == hostname -f),
  // then we will require that it exactly match the host in order
  // to be considered local.  However, the usual convention is for
  // uname -n to return only the node name (that is, uname -n == hostname -a).
  // When this is the case, we will consider $host to be local if the
  // machine portion of it (everything up to the first dot) matches the
  // current value of uname -n.  We prefer uname -n to hostname as
  // the output of uname is more regular than hostname.
  $uname = php_uname('n');
  return (strpos($uname,'.') !== FALSE) ? ($host == $uname) : substr($host . '.',0,strlen($uname)+1) == $uname . '.';
}

/**
 * Return the user's home directory.
 */
function drush_server_home() {
  // Cannot use $_SERVER superglobal since that's empty during Drush_UnitTestCase
  // getenv('HOME') isn't set on windows and generates a Notice.
  $home = getenv('HOME');
  if (empty($home)) {
    if (!empty($_SERVER['HOMEDRIVE']) && !empty($_SERVER['HOMEPATH'])) {
      // home on windows
      $home = $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];
    }
  }
  return empty($home) ? NULL : $home;
}

/*
 * The path to the global cache directory.
 */
function drush_directory_cache() {
  if (getenv('CACHE_PREFIX')) {
    return getenv('CACHE_PREFIX') . '/cache';
  }
  elseif ($home = drush_server_home()) {
    return "$home/.drush/cache";
  }
  else {
    return FALSE;
  }
}

/**
 * Get complete information for all available extensions (modules and themes).
 *
 * @return
 *   An array containing info for all available extensions.
 */
function drush_get_extensions() {
  drush_include_engine('drupal', 'environment');
  $extensions = array_merge(drush_get_modules(), drush_get_themes());
  foreach ($extensions as $name => $extension) {
    $extensions[$name]->label = $extension->info['name'].' ('.$extension->name.')';
    if (empty($extension->info['package'])) {
      $extensions[$name]->info['package'] = dt('Other');
    }
  }
  return $extensions;
}

/**
 * Test compatibility of a extension with version of drupal core and php.
 *
 * @param $file Extension object as returned by system_rebuild_module_data().
 * @return TRUE if the extension is incompatible.
 */
function drush_extension_check_incompatibility($file) {
  if (!isset($file->info['core'])
       || $file->info['core'] != DRUPAL_CORE_COMPATIBILITY
       || version_compare(phpversion(), $file->info['php']) < 0) {
    return TRUE;
  }

  return FALSE;
}

/**
 *
 */
function drush_drupal_required_modules($modules) {
  drush_include_engine('drupal', 'environment');
  return _drush_drupal_required_modules($modules);
}

/**
 * Return the default theme.
 *
 * @return
 *  Machine name of the default theme.
 */
function drush_theme_get_default() {
  return variable_get('theme_default', 'garland');
}

/**
 * Return the administration theme.
 *
 * @return
 *  Machine name of the administration theme.
 */
function drush_theme_get_admin() {
  return variable_get('admin_theme', drush_theme_get_default());
}
