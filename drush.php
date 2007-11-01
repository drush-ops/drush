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
if (!empty($_SERVER['REQUEST_METHOD'])) {
  die();
}

exit(drush_bootstrap($GLOBALS['argc'], $GLOBALS['argv']));


function drush_bootstrap($argc, $argv) {
  global $args;
  // Parse command line options and arguments.
  $args = drush_parse_args($argv, array('h', 'u', 'r', 'l'));

  // Define basic options as constants.
  define('DRUSH_URI',         drush_get_option(array('l', 'uri'), FALSE));
  define('DRUSH_VERBOSE',     drush_get_option(array('v', 'verbose'), FALSE));
  define('DRUSH_AFFIRMATIVE', drush_get_option(array('y', 'yes'), FALSE));
  define('DRUSH_SIMULATE',    drush_get_option(array('s', 'simulate'), FALSE));

  // TODO: Make use of this as soon as the external
  define('DRUSH_USER',        drush_get_option(array('u', 'user'), 0));

  // If no root is defined, we try to guess from the current directory.
  define('DRUSH_DRUPAL_ROOT',  drush_get_option(array('r', 'root'), _drush_locate_root()));

  // If the Drupal directory can't be found, and no -r option was specified,
  // or the path specified in -r does not point to a Drupal directory,
  // we have no alternative but to give up the ghost at this point.
  // (NOTE: t() is not available yet.)
  if (!DRUSH_DRUPAL_ROOT || !is_dir(DRUSH_DRUPAL_ROOT) || !file_exists(DRUSH_DRUPAL_ROOT . '/' . DRUSH_DRUPAL_BOOTSTRAP))  {
    exit("E: Could not locate the Drupal installation directory. Aborting.\n");
  }

  // Fake the necessary HTTP headers that Drupal needs:
  $drupal_base_url = parse_url(DRUSH_URI);
  $_SERVER['HTTP_HOST'] = $drupal_base_url['host'];
  $_SERVER['PHP_SELF'] = $drupal_base_url['path'].'/index.php';
  $_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'];
  $_SERVER['REMOTE_ADDR'] = NULL;
  $_SERVER['REQUEST_METHOD'] = NULL;

  // Change to Drupal root dir.
  chdir(DRUSH_DRUPAL_ROOT);
  // Bootstrap Drupal.
  _drush_bootstrap_drupal();
  
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
 */
function _drush_locate_root() {

  $path = getcwd();
  // Convert windows paths.
  $path = drush_convert_path($path);
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
    drush_die("Unable to load Drupal configuration from $conf_path/.");
  }

  // The bootstrap can fail silently, so we catch that in a shutdown function.
  register_shutdown_function('drush_shutdown');
  drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
  require_once drupal_get_path('module', 'drush') . '/drush.inc';
}

function drush_shutdown() {
  if (!function_exists('drupal_set_content')) {
    // can't use drush.inc function here.
    die("Drush: Bootstrap failed. Perhaps you need to pass a valid value for the -l argument.\n");
  }
}

/**
 * Log a certain user in.
 */
function _drush_login($drush_user) {
  global $user;
  $user = user_load(is_numeric($drush_user) ? array('uid' => $drush_user) : array('name' => $drush_user));

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

?>
