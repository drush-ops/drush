#!/usr/bin/env php
<?php
// $Id$

/**
 * @file
 * drush is a PHP script implementing a command line shell for Drupal.
 *
 * @requires PHP 4.3.0 / PHP 5.x or newer
 */

//////////////////////////////////////////////////////////////////////////////

define('DRUSH_CWD',       getcwd());
define('DRUSH_BOOTSTRAP', DRUSH_CWD . '/includes/bootstrap.inc');

require_once dirname(__FILE__) . '/drush.inc';

die(main($GLOBALS['argc'], $GLOBALS['argv']));

//////////////////////////////////////////////////////////////////////////////

function main($argc, $argv) {
  // Die immediately unless invoked as a command line script
  if (!empty($_SERVER['REQUEST_METHOD']))
    die();

  // Parse command line options and arguments
  array_shift($argv); // ignore program name
  define('DRUSH_VERBOSE',     _drush_get_option('v', $argv, FALSE));
  define('DRUSH_QUIET',       _drush_get_option('q', $argv, FALSE));
  define('DRUSH_AFFIRMATIVE', _drush_get_option('y', $argv, FALSE));
  define('DRUSH_SIMULATE',    _drush_get_option('s', $argv, FALSE));
  define('DRUSH_HOST',        _drush_get_option('h:', $argv, @$_SERVER['HTTP_HOST']));
  define('DRUSH_USER',        _drush_get_option('u:', $argv, '0'));

  // Fake the necessary HTTP headers that Drupal needs
  $_SERVER['HTTP_HOST'] = DRUSH_HOST;
  $_SERVER['PHP_SELF'] = '/index.php';

  // Boot Drupal up and load all available drush services
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
  exit(empty($result) || $result === TRUE ? 0 : $result);
}

//////////////////////////////////////////////////////////////////////////////
// DRUSH BOOTSTRAP

function _drush_bootstrap_drupal() {
  if (!file_exists(DRUSH_BOOTSTRAP))
    drush_die('Not in a Drupal installation directory.');

  require_once DRUSH_BOOTSTRAP;

  if (($conf_path = conf_path()) && !file_exists("./$conf_path/settings.php"))
    drush_die("Unable to load Drupal configuration from $conf_path/.");

  drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL); // FIXME: @

  if (!defined('VERSION'))
    drush_die('Drupal versions older than 4.7.x not supported.');

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
    '-v'      => 'Display action output (be verbose).',
    '-y'      => 'Assume that the answer to simple yes/no questions is \'yes\'.',
    '-s'      => 'Simulate actions, but do not actually perform them.',
    '-h host' => 'Drupal host name to use (for multi-site Drupal installations).',
    '-u uid'  => 'Drupal user name (or numeric ID) to execute actions under.',
  );
}

function _drush_get_option($option, &$argv, $default = NULL, $remove = TRUE) {
  $options = getopt($option);
  if (count($options) > 0) {
    $value = reset($options);
    if ($remove) {
      $argv = array_diff($argv, array("-$option", $value), array("-$option$value"));
    }
    return ($value === FALSE ? TRUE : $value);
  }
  return $default;
}

////////////////////////////////////////////////////////////////////////////
