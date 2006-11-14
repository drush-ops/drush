#!/usr/bin/env php
<?php
// $Id$

/**
 * @file
 * drush is a PHP script implementing a command line shell for Drupal.
 *
 * @requires PHP CLI 4.3.0, PHP CLI 5.x, or newer.
 */

//////////////////////////////////////////////////////////////////////////////

// Terminate immediately unless invoked as a command line script
if (!empty($_SERVER['REQUEST_METHOD']))
  die();

exit(main($GLOBALS['argc'], $GLOBALS['argv']));

//////////////////////////////////////////////////////////////////////////////

function main($argc, $argv) {
  require_once dirname(__FILE__) . '/drush.inc';

  // Parse command line options and arguments
  array_shift($argv); // ignore program name
  define('DRUSH_VERBOSE',     _drush_get_option('v', $argv, FALSE));
  define('DRUSH_QUIET',       _drush_get_option('q', $argv, FALSE));
  define('DRUSH_AFFIRMATIVE', _drush_get_option('y', $argv, FALSE));
  define('DRUSH_SIMULATE',    _drush_get_option('s', $argv, FALSE));
  define('DRUSH_HOST',        _drush_get_option('h:', $argv, @$_SERVER['HTTP_HOST']));
  define('DRUSH_USER',        _drush_get_option('u:', $argv, '0'));

  // Try and locate the Drupal root directory
  define('DRUSH_BOOTSTRAP',   'includes/bootstrap.inc');
  define('DRUSH_ROOT',        _drush_get_option('r:', $argv, _drush_locate_root()));
  if (!DRUSH_ROOT) drush_die('Could not locate the Drupal installation directory.');

  // Fake the necessary HTTP headers that Drupal needs
  $_SERVER['HTTP_HOST'] = DRUSH_HOST;
  $_SERVER['PHP_SELF'] = '/index.php';

  // Boot Drupal up and load all available drush services
  chdir(DRUSH_ROOT);
  _drush_bootstrap_drupal();
  _drush_bootstrap_services();

  // If no actions given, let's show some usage instructions
  if (count($argv) == 0)
    $argv[] = 'usage';

  // Dispatch to the specified service and action
  if (DRUSH_QUIET) ob_start();
  $result = _drush_dispatch($argv);
  if (DRUSH_QUIET) ob_end_clean();

  // Terminate with the correct exit status
  return (empty($result) || $result === TRUE ? 0 : $result);
}

//////////////////////////////////////////////////////////////////////////////
// DRUSH BOOTSTRAP

/**
 * Exhaustive depth-first search to try and locate the Drupal root directory.
 */
function _drush_locate_root() {
  $paths = array_unique(array(getcwd(), dirname(__FILE__)));
  foreach ($paths as $path) {
    if ($result = _drush_locate_root_search($path))
      return $result;
  }
  return FALSE;
}

function _drush_locate_root_search($path) {
  if (empty($path))
    return FALSE;
  if (file_exists($path . '/' . DRUSH_BOOTSTRAP))
    return $path;

  $path = explode('/', $path);
  array_pop($path); // move one directory up
  return _drush_locate_root_search(implode('/', $path));
}

function _drush_bootstrap_drupal() {
  require_once DRUSH_BOOTSTRAP;

  if (($conf_path = conf_path()) && !file_exists("./$conf_path/settings.php"))
    drush_die("Unable to load Drupal configuration from $conf_path/.");

  drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL); // FIXME: @

  if (!defined('VERSION'))
    drush_die('Drupal versions older than 4.7.x are not supported.');

  return TRUE;
}

function _drush_bootstrap_services() {
  if (!drush_load_builtins())
    drush_die('Unable to load any drush services from ' . DRUSH_PATH . '.');

  return TRUE;
}

//////////////////////////////////////////////////////////////////////////////
// DRUSH OPTION HANDLING

function _drush_get_option_info() {
  return array(
    '-q'      => 'Don\'t output anything at all (be as quiet as possible).',
    '-v'      => 'Display all output from an action (be verbose).',
    '-y'      => 'Assume that the answer to simple yes/no questions is \'yes\'.',
    '-s'      => 'Simulate actions, but do not actually perform them.',
    '-h host' => 'HTTP host name to use (for multi-site Drupal installations).',
    '-u uid'  => 'Drupal user name (or numeric ID) to execute actions under.',
    '-r path' => 'Drupal root directory to use (default: current directory).',
  );
}

function _drush_get_option($option, &$argv, $default = NULL, $remove = TRUE) {
  $options = getopt($option);
  if (count($options) > 0) {
    $value = reset($options);
    if ($remove) {
      $option = substr($option, 0, 1); // just the actual option character
      $argv = array_diff($argv, array("-$option", $value), array("-$option$value"));
    }
    return ($value === FALSE ? TRUE : $value);
  }
  return $default;
}

////////////////////////////////////////////////////////////////////////////
