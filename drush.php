#!/usr/bin/env php
<?php

// $Id$

/**
 * @file
 * drush is a PHP script implementing a command line shell for Drupal.
 *
 * @requires PHP CLI 4.3.0, PHP CLI 5.x, or newer.
 */

define('DRUSH_DRUPAL_BOOTSTRAP', 'includes/bootstrap.inc');

// Terminate immediately unless invoked as a command line script
if (!drush_verify_cli()) {
  die('drush.php is designed to run via the command line.');
}

exit(drush_bootstrap($GLOBALS['argc'], $GLOBALS['argv']));

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
  if (php_sapi_name() == 'cgi') {
    return (is_numeric($_SERVER['argc']) && $_SERVER['argc'] > 0);
  }
  
  return (php_sapi_name() == 'cli');
}

/**
 * Load drushrc files (if available) from several possible locations.
 * 
 * @param $root
 *   The Drupal root to check. 
 */
function drush_load_rc($root) {
  global $override, $args;
  
  # Specified rc file
  $configs[] = drush_get_option(array('c', 'config'), FALSE);
  # Rc file in same directory as the drush.php file
  $configs[] = dirname($_SERVER['SCRIPT_FILENAME']) . "/drushrc.php";
  # Rc file in current directory
  $configs[] = "drushrc.php";  
  # Rc file in located drupal root
  $configs[] = drush_get_option(array('r', 'root'), $root). '/drushrc.php'; 
  # Rc file in user's home directory
  $configs[] = $_SERVER['HOME'] . '/.drushrc.php';
  
  foreach ($configs as $config) {
    if (file_exists($config)) {
      define('DRUSH_CONFIG', $config);
      require_once($config);
      if (is_array($options)) {
        $args['options'] = array_merge($args['options'], $options); # Sets all the default options for drush
      }
      break;
    }
  }
}

function drush_bootstrap($argc, $argv) {
  global $args, $override, $conf;

  // Parse command line options and arguments.
  $args = drush_parse_args($argv, array('h', 'u', 'r', 'l'));
  
  // We use PWD if available because getcwd() resolves symlinks, which
  // could take us outside of the Drupal root, making it impossible to find. 
  $path = $_SERVER['PWD'];
  if (empty($path)) {
    $path = getcwd();
  }

  // Convert windows paths.
  $path = drush_convert_path($path);

  // Try and locate the Drupal root directory
  $root = _drush_locate_root($path);

  // Load .drushrc file if available. Allows you to provide defaults for options and variables.
  drush_load_rc($root);
  
  $uri = FALSE;
  // If the current directory contains a settings.php we assume that is the desired site URI.
  if (file_exists('./settings.php')) {
    // Export the following settings.php variables to the global namespace.
    global $db_url, $db_prefix, $cookie_domain, $conf, $installed_profile, $update_free_access;
    // If the settings.php has a defined path we use the URI from that.
    include_once('./settings.php');
    if (isset($base_url)) {
      $uri = $base_url;
    }
    else {
      // Alternatively we default to the name of the current directory, if it is not 'default'.
      $elements = explode('/', $path);
      $current = array_pop($elements);
      if ($current != 'default') {
        $uri = 'http://'. $current;
      }
    }
  }

  // Define basic options as constants.
  define('DRUSH_URI',         drush_get_option(array('l', 'uri'), $uri));
  define('DRUSH_VERBOSE',     drush_get_option(array('v', 'verbose'), FALSE));
  define('DRUSH_AFFIRMATIVE', drush_get_option(array('y', 'yes'), FALSE));
  define('DRUSH_SIMULATE',    drush_get_option(array('s', 'simulate'), FALSE));

  // TODO: Make use of this as soon as the external
  define('DRUSH_USER',        drush_get_option(array('u', 'user'), 0));

  // If no root is defined, we try to guess from the current directory.
  define('DRUSH_DRUPAL_ROOT',  drush_get_option(array('r', 'root'), $root));
  
  // Possible temporary. See http://drupal.org/node/312421.
  define('DRUPAL_ROOT', DRUSH_DRUPAL_ROOT);

  // If the Drupal directory can't be found, and no -r option was specified,
  // or the path specified in -r does not point to a Drupal directory,
  // we have no alternative but to give up the ghost at this point.
  // (NOTE: t() is not available yet.)
  if (!DRUSH_DRUPAL_ROOT || !is_dir(DRUSH_DRUPAL_ROOT) || !file_exists(DRUSH_DRUPAL_ROOT . '/' . DRUSH_DRUPAL_BOOTSTRAP))  {
    $dir = '';
    if (DRUSH_DRUPAL_ROOT) {
      $dir = ' in ' . DRUSH_DRUPAL_ROOT;
    }
    // Provide a helpful exit message, letting the user know where we looked
    // (if we looked at all) and a hint on how to specify the directory manually.
    $message = "E: Could not locate a Drupal installation directory$dir. Aborting.\n";
    $message .= "Hint: You can specify your Drupal installation directory with the --root\n";
    $message .= "parameter on the command line or \$options['root'] in your drushrc.php file.\n";
    die($message);
  }

  // Fake the necessary HTTP headers that Drupal needs:
  if (DRUSH_URI) {
    $drupal_base_url = parse_url(DRUSH_URI);
    $_SERVER['HTTP_HOST'] = $drupal_base_url['host'];
    $_SERVER['PHP_SELF'] = $drupal_base_url['path'].'/index.php';
  }
  else {
    $_SERVER['HTTP_HOST'] = NULL;
    $_SERVER['PHP_SELF'] = NULL;
  }
  $_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'];
  $_SERVER['REMOTE_ADDR'] = '';
  $_SERVER['REQUEST_METHOD'] = NULL;
  $_SERVER['SERVER_SOFTWARE'] = NULL;
  $_SERVER['HTTP_USER_AGENT'] = NULL;

  // Change to Drupal root dir.
  chdir(DRUSH_DRUPAL_ROOT);
  // Bootstrap Drupal.
  _drush_bootstrap_drupal();
  /**
   * Allow the drushrc.php file to override $conf settings.
   * This is a separate variable because the $conf array gets initialized to an empty array,
   * in the drupal bootstrap process, and changes in settings.php would wipe out the drushrc.php
   * settings
   */
  if (is_array($override)) {
    $conf = array_merge($conf, $override);
  }
    
  // Login the specified user (if given).
  if (DRUSH_USER) {
    _drush_login(DRUSH_USER);
  }

  // Now we can use all of Drupal.

  if (DRUSH_SIMULATE) {
    drush_print(t('SIMULATION MODE IS ENABLED. NO ACTUAL ACTION WILL BE TAKEN. SYSTEM WILL REMAIN UNCHANGED.'));
  }

  // Dispatch the command.
  $output = drush_dispatch($args['commands']);

  // prevent a '1' at the end of the outputs
  if ($output === true) {
    $output = '';
  }

  // TODO: Terminate with the correct exit status.
  return $output;
}


/**
 * Exhaustive depth-first search to try and locate the Drupal root directory.
 * This makes it possible to run drush from a subdirectory of the drupal root.
 * 
 * @param $path
 *   The path the start the search from.
 */
function _drush_locate_root($path) {
  // Check the current path.
  if (file_exists($path . '/' . DRUSH_DRUPAL_BOOTSTRAP)) {
    return $path;
  }
  // Move up dir by dir and check each.
  while ($path = _drush_locate_root_moveup($path)) {
    if (file_exists($path . '/' . DRUSH_DRUPAL_BOOTSTRAP)) {
      return $path;
    }
  }

  return FALSE;
}

function _drush_locate_root_moveup($path) {
  if (empty($path)) {
    return FALSE;
  }
  $path = explode('/', $path);
  // Move one directory up.
  array_pop($path);
  return implode($path, '/');
}

/**
 * Bootstrap Drupal.
 */
function _drush_bootstrap_drupal() {
  require_once DRUSH_DRUPAL_BOOTSTRAP;
  if (($conf_path = conf_path()) && !file_exists("./$conf_path/settings.php")) {
    // Provide a helpful exit message, letting the user know where we looked
    // (if we looked at all) and a hint on how to specify the URI manually.
    $message = "E: Unable to load Drupal configuration from $conf_path. Aborting.\n";
    $message .= "Hint: You can specify your Drupal URI to use with the --uri\n";
    $message .= "parameter on the command line or \$options['uri'] in your drushrc.php file.\n";
    die($message);
  }

  // The bootstrap can fail silently, so we catch that in a shutdown function.
  register_shutdown_function('drush_shutdown');
  drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
  if (module_exists('drush')) {
    require_once drupal_get_path('module', 'drush') . '/drush.inc';
  }
  else {
    $message = "E: You must enable the Drush module for the site you want to use.\n";
    $message .= "Hint: Drush was looking in the site '$conf_path'. You can select another site\n";
    $message .= "with Drush enabled by specifying the Drupal URI to use with the --uri\n";
    $message .= "parameter on the command line or \$options['uri'] in your drushrc.php file.\n";
    die($message);
  }
}

function drush_shutdown() {
  if (!function_exists('drupal_set_content')) {
    $message = "E: Drush was not able to start (bootstrap) Drupal.\n";
    $message .= "Hint: This error often occurs when Drush is trying to bootstrap a site\n";
    $message .= "that has not been installed or does not have a configured \$db_url.\n";
    $message .= "Drush was looking in the site '$conf_path'. You can select another site\n";
    $message .= "with a working database setup by specifying the URI to use with the --uri\n";
    $message .= "parameter on the command line or \$options['uri'] in your drushrc.php file.\n";
    die($message);
  }
}

/**
 * Log a certain user in.
 */
function _drush_login($drush_user) {
  global $user;
  $user = module_invoke('user', 'load', is_numeric($drush_user) ? array('uid' => $drush_user) : array('name' => $drush_user));

  if (empty($user)) {
    if (is_numeric($drush_user)) {
      drush_die(t('Could not login with user ID #%user.', array('%user' => $drush_user)));
    }
    else {
      drush_die(t('Could not login with user account `%user\'.', array('%user' => $drush_user)));
    }
    return FALSE;
  }

  return TRUE;
}

/**
 * Parse console arguments.
 *
 * @param $args
 *   The console argument array (usually $argv)
 * @param $arg_opts
 *   An array of options that are followed by an argument.
 *   e.g. shell.php -u admin -v --> $arg_opts = array('u')
 * @param $default_options
 * @return
 *   A associative array:
 *   $return['commands'] ia a numeric array of all commands,
 *   $return['options'] contains the options. The option keys
 *   are always noted without - or -- and are set to TRUE if they were
 *   invoked, to the argument if followed by an argument, and if not present
 *   to their default value or FALSE if no default value was specified.
 */
function drush_parse_args($args = array(), $arg_opts = array(), $default_options = array()) {
  $options = $default_options;
  $commands = array();

  for ($i = 1; $i < count($args); $i++) {
    $opt = $args[$i];
    // Is the arg an option (starting with '-')?
    if ($opt{0} == "-" && strlen($opt) != 1) {
      // Do we have multiple options behind one '-'?
      if (strlen($opt) > 2 && $opt{1} != "-") {
        // Each char becomes a key of its own.
        for ($j = 1; $j < strlen($opt); $j++) {
          $options[substr($opt, $j, 1)] = true;
        }
      }
      // Do we have a longopt (starting with '--')?
      elseif ($opt{1} == "-") {
        if ($pos = strpos($opt, '=')) {
          $options[substr($opt, 2, $pos - 2)] = substr($opt, $pos + 1);
        }
        else {
          $options[substr($opt, 2)] = true;
        }
      }
      else {
        $opt = substr($opt, 1);
        // Check if the current opt is in $arg_opts (= has to be followed by an argument).
        if ((in_array($opt, $arg_opts))) {
          if (($args[$i+1] == NULL) || ($args[$i+1] == "") || ($args[$i + 1]{0} == "-")) {
            exit("Invalid input: -$opt needs to be followed by an argument.");
          }
          $options[$opt] = $args[$i + 1];
          $i++;
        }
        else {
          $options[$opt] = true;
        }
      }
    }
    // If it's not an option, it's a command.
    else {
      $commands[] = $opt;
    }
  }
  return array('options' => $options, 'commands' => $commands);
}

/**
 * Get the value for an option.
 *
 * If the first argument is an array, then it checks wether one of the options
 * exists and return the value of the first one found. Useful for allowing both
 * -h and --host-name
 *
 */
function drush_get_option($option, $default = NULL) {
  $options = $GLOBALS['args']['options'];
  if (is_array($option)) {
    foreach ($option as $current) {
      if (array_key_exists($current, $options)) {
        return $options[$current];
      }
    }
    return $default;
  }

  if (!array_key_exists($option, $options)) {
    return $default;
  }
  else {
    return $options[$option];
  }
}

/**
 * Converts a Windows path (dir1\dir2\dir3) into a Unix path (dir1/dir2/dir3).
 */
function drush_convert_path($path) {
  return str_replace('\\','/', $path);
}

