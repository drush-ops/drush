<?php

use Drush\Log\LogLevel;

/**
 * No bootstrap.
 *
 * Commands that only preflight, but do not bootstrap, should use
 * a bootstrap level of DRUSH_BOOTSTRAP_NONE.
 */
define('DRUSH_BOOTSTRAP_NONE', -1);

/**
 * Use drush_bootstrap_max instead of drush_bootstrap_to_phase
 *
 * This constant is only usable as the value of the 'bootstrap'
 * item of a command object, or as the parameter to
 * drush_bootstrap_to_phase.  It is not a real bootstrap state.
 */
define('DRUSH_BOOTSTRAP_MAX', -2);

/**
 * @deprecated
 *
 * No longer used, but 0 remains reserved. Drush always runs preflight.
 * Commands may alternatively use DRUSH_BOOTSTRAP_NONE.
 */
define('DRUSH_BOOTSTRAP_DRUSH', 0);

// TODO: Move all of the constants below to a Drupal-specific file.
// We can't do this until commands are declaring which CMS they work
// with, because right now, commands that do not declare a 'bootstrap'
// level default to DRUSH_BOOTSTRAP_DRUPAL_LOGIN, so we need this constant,
// at least, available in non-Drupal contexts.

/**
 * Set up and test for a valid drupal root, either through the -r/--root options,
 * or evaluated based on the current working directory.
 *
 * Any code that interacts with an entire Drupal installation, and not a specific
 * site on the Drupal installation should use this bootstrap phase.
 */
define('DRUSH_BOOTSTRAP_DRUPAL_ROOT',  1);

/**
 * Set up a Drupal site directory and the correct environment variables to
 * allow Drupal to find the configuration file.
 *
 * If no site is specified with the -l / --uri options, Drush will assume the
 * site is 'default', which mimics Drupal's behaviour.
 *
 * If you want to avoid this behaviour, it is recommended that you use the
 * DRUSH_BOOTSTRAP_DRUPAL_ROOT bootstrap phase instead.
 *
 * Any code that needs to modify or interact with a specific Drupal site's
 * settings.php file should bootstrap to this phase.
 */
define('DRUSH_BOOTSTRAP_DRUPAL_SITE', 2);

/**
 * Load the settings from the Drupal sites directory.
 *
 * This phase is analagous to the DRUPAL_BOOTSTRAP_CONFIGURATION bootstrap phase in Drupal
 * itself, and this is also the first step where Drupal specific code is included.
 *
 * This phase is commonly used for code that interacts with the Drupal install API,
 * as both install.php and update.php start at this phase.
 */
define('DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION', 3);

/**
 * Connect to the Drupal database using the database credentials loaded
 * during the previous bootstrap phase.
 *
 * This phase is analogous to the DRUPAL_BOOTSTRAP_DATABASE bootstrap phase in
 * Drupal.
 *
 * Any code that needs to interact with the Drupal database API needs to
 * be bootstrapped to at least this phase.
 */
define('DRUSH_BOOTSTRAP_DRUPAL_DATABASE', 4);

/**
 * Fully initialize Drupal.
 *
 * This is analogous to the DRUPAL_BOOTSTRAP_FULL bootstrap phase in
 * Drupal.
 *
 * Any code that interacts with the general Drupal API should be
 * bootstrapped to this phase.
 */
define('DRUSH_BOOTSTRAP_DRUPAL_FULL', 5);

/**
 * Log in to the initialiased Drupal site.
 *
 * This is the default bootstrap phase all commands will try to reach,
 * unless otherwise specified.
 *
 * This bootstrap phase is used after the site has been
 * fully bootstrapped.
 *
 * This phase will log you in to the drupal site with the username
 * or user ID specified by the --user/ -u option.
 *
 * Use this bootstrap phase for your command if you need to have access
 * to information for a specific user, such as listing nodes that might
 * be different based on who is logged in.
 */
define('DRUSH_BOOTSTRAP_DRUPAL_LOGIN', 6);

/**
 * Return the list of bootstrap objects that are available for
 * initializing a CMS with Drush.  We insure that any given candidate
 * class is instantiated only once.
 *
 * @return \Drush\Boot\Boot[]
 */
function drush_get_bootstrap_candidates() {
  $candidate_classes = drush_get_bootstrap_candidate_classnames();

  $cache =& drush_get_context('DRUSH_BOOTSTRAP_CANDIDATE_OBJECTS');

  $result = array();
  foreach($candidate_classes as $candidate_class) {
    if (array_key_exists($candidate_class, $cache)) {
      $result[$candidate_class] = $cache[$candidate_class];
    }
    else {
      $result[$candidate_class] = new $candidate_class;
    }
  }

  $cache = $result;
  return $result;
}

/**
 * Find the list of bootstrap classnames available for initializing a
 * CMS with Drush.
 *
 * @return array
 */
function drush_get_bootstrap_candidate_classnames() {
  // Give all commandfiles a chance to return candidates.  They should
  // return STRINGS with the class name of the bootstrap object they provide.
  $candidates = drush_command_invoke_all('bootstrap_candidates');
  // If a bootstrap class was specified on the command line, consider it first.
  $bootstrap_class = drush_get_option('bootstrap_class', FALSE);
  if ($bootstrap_class) {
    array_unshift($candidates, $bootstrap_class);
  }
  // Add candidate bootstrap classes for Drupal
  foreach (array('8', '7', '6') as $version) {
    $drupal_bootstrap_class = 'Drush\Boot\DrupalBoot' . $version;
    $candidates[] = $drupal_bootstrap_class;
  }
  // Always consider our default bootstrap class last.
  $candidates[] = 'Drush\Boot\EmptyBoot';

  return $candidates;
}

/**
 * Look up the best bootstrap class for the given location
 * from the set of available candidates.
 */
function drush_bootstrap_class_for_root($path) {
  drush_load_bootstrap_commandfile_at_path($path);
  $candidates = drush_get_bootstrap_candidates();
  foreach ($candidates as $candidate) {
    if ($candidate->valid_root($path)) {
      return $candidate;
    }
  }
  return NULL;
}

/**
 * Check to see if there is a bootstrap class available
 * at the specified location; if there is, load it.
 */
function drush_load_bootstrap_commandfile_at_path($path) {
  static $paths = array();

  if (!empty($path) && (!array_key_exists($path, $paths))) {
    $paths[$path] = TRUE;
    // Check to see if we have any bootstrap classes in this location.
    $bootstrap_class_dir = $path . '/drush/bootstrap';
    if (is_dir($bootstrap_class_dir)) {
      _drush_add_commandfiles(array($bootstrap_class_dir), DRUSH_BOOTSTRAP_NONE);
    }
  }
}

/**
 * Select the bootstrap class to use.  If this is called multiple
 * times, the bootstrap class returned might change on subsequent
 * calls, if the root directory changes.  Once the bootstrap object
 * starts changing the state of the system, however, it will
 * be 'latched', and further calls to drush_select_bootstrap_class()
 * will always return the same object.
 */
function drush_select_bootstrap_class() {
  $root = drush_get_context('DRUSH_SELECTED_DRUPAL_ROOT');

  // Once we have selected a Drupal root, we will reduce our bootstrap
  // candidates down to just the one used to select this site root.
  $bootstrap = drush_bootstrap_class_for_root($root);
  // If we have not found a bootstrap class by this point,
  // then take the last one and use it.  This should be our
  // default bootstrap class.  The default bootstrap class
  // should pass through all calls without doing anything that
  // changes state in a CMS-specific way.
  if ($bootstrap == NULL) {
    $candidates = drush_get_bootstrap_candidates();
    $bootstrap = array_pop($candidates);
  }

  return $bootstrap;
}

/**
 * Don't allow the bootstrap object to change once we start bootstrapping
 */
function drush_latch_bootstrap_object($bootstrap) {
  drush_set_context('DRUSH_BOOTSTRAP_OBJECT', $bootstrap);
}

/**
 * Get the appropriate bootstrap object.  We'll search for a new
 * bootstrap object every time someone asks for one until we start
 * bootstrapping; then we'll returned the same cached one every time.
 *
 * @return \Drush\Boot\Boot
 */
function drush_get_bootstrap_object() {
  $bootstrap = drush_get_context('DRUSH_BOOTSTRAP_OBJECT', FALSE);
  if (!$bootstrap) {
    $bootstrap = drush_select_bootstrap_class();
  }
  return $bootstrap;
}

/**
 * Find the URI that has been selected by the cwd
 * if it was not previously set via the --uri / -l option
 */
function _drush_bootstrap_selected_uri() {
  $uri = drush_get_context('DRUSH_SELECTED_URI');
  if (empty($uri)) {
    $site_path = drush_site_path();
    $elements = explode('/', $site_path);
    $current = array_pop($elements);
    if (!$current) {
      $current = 'default';
    }
    $uri = 'http://'. $current;
    $uri = drush_set_context('DRUSH_SELECTED_URI', $uri);
    drush_sitealias_create_self_alias();
  }

  return $uri;
}

/**
 * Helper function to store any context settings that are being validated.
 */
function drush_bootstrap_value($context, $value = null) {
  $values =& drush_get_context('DRUSH_BOOTSTRAP_VALUES', array());

  if (isset($value)) {
    $values[$context] = $value;
  }

  if (array_key_exists($context, $values)) {
    return $values[$context];
  }

  return null;
}

/**
 * Returns an array that determines what bootstrap phases
 * are necessary to bootstrap the CMS.
 *
 * @param bool $function_names
 *   (optional) If TRUE, return an array of method names index by their
 *   corresponding phase values. Otherwise return an array of phase values.
 *
 * @return array
 *
 * @see \Drush\Boot\Boot::bootstrap_phases()
 */
function _drush_bootstrap_phases($function_names = FALSE) {
  $result = array();

  if ($bootstrap = drush_get_bootstrap_object()) {
    $result = $bootstrap->bootstrap_phases();
    if (!$function_names) {
      $result = array_keys($result);
    }
  }
  return $result;
}

/**
 * Bootstrap Drush to the desired phase.
 *
 * This function will sequentially bootstrap each
 * lower phase up to the phase that has been requested.
 *
 * @param int $phase
 *   The bootstrap phase to bootstrap to.
 * @param int $phase_max
 *   (optional) The maximum level to boot to. This does not have a use in this
 *   function itself but can be useful for other code called from within this
 *   function, to know if e.g. a caller is in the process of booting to the
 *   specified level. If specified, it should never be lower than $phase.
 *
 * @return bool
 *   TRUE if the specified bootstrap phase has completed.
 *
 * @see \Drush\Boot\Boot::bootstrap_phases()
 */
function drush_bootstrap($phase, $phase_max = FALSE) {
  $bootstrap = drush_get_bootstrap_object();
  $phases = _drush_bootstrap_phases(TRUE);
  $result = TRUE;

  // If the requested phase does not exist in the list of available
  // phases, it means that the command requires bootstrap to a certain
  // level, but no site root could be found.
  if (!isset($phases[$phase])) {
    $result = drush_bootstrap_error('DRUSH_NO_SITE', dt("We could not find an applicable site for that command."));
  }

  // Once we start bootstrapping past the DRUSH_BOOTSTRAP_DRUSH phase, we
  // will latch the bootstrap object, and prevent it from changing.
  if ($phase > DRUSH_BOOTSTRAP_DRUSH) {
    drush_latch_bootstrap_object($bootstrap);
  }

  drush_set_context('DRUSH_BOOTSTRAPPING', TRUE);
  foreach ($phases as $phase_index => $current_phase) {
    $bootstrapped_phase = drush_get_context('DRUSH_BOOTSTRAP_PHASE', -1);
    if ($phase_index > $phase) {
      break;
    }
    if ($phase_index > $bootstrapped_phase) {
      if ($result = drush_bootstrap_validate($phase_index)) {
        if (method_exists($bootstrap, $current_phase) && !drush_get_error()) {
          drush_log(dt("Drush bootstrap phase : !function()", array('!function' => $current_phase)), LogLevel::BOOTSTRAP);
          $bootstrap->{$current_phase}();

          // Reset commandfile cache and find any new command files that are available during this bootstrap phase.
          drush_get_commands(TRUE);
          _drush_find_commandfiles($phase_index, $phase_max);
        }
        drush_set_context('DRUSH_BOOTSTRAP_PHASE', $phase_index);
      }
    }
  }
  drush_set_context('DRUSH_BOOTSTRAPPING', FALSE);
  if (!$result || drush_get_error()) {
    $errors = drush_get_context('DRUSH_BOOTSTRAP_ERRORS', array());
    foreach ($errors as $code => $message) {
      drush_set_error($code, $message);
    }
  }
  return !drush_get_error();
}

/**
 * Determine whether a given bootstrap phase has been completed
 *
 * This function name has a typo which makes me laugh so we choose not to
 * fix it. Take a deep breath, and smile. See
 * http://en.wikipedia.org/wiki/HTTP_referer
 *
 *
 * @param int $phase
 *   The bootstrap phase to test
 *
 * @return bool
 *   TRUE if the specified bootstrap phase has completed.
 */
function drush_has_boostrapped($phase) {
  $phase_index = drush_get_context('DRUSH_BOOTSTRAP_PHASE');

  return isset($phase_index) && ($phase_index >= $phase);
}

/**
 * Validate whether a bootstrap phase can be reached.
 *
 * This function will validate the settings that will be used
 * during the actual bootstrap process, and allow commands to
 * progressively bootstrap to the highest level that can be reached.
 *
 * This function will only run the validation function once, and
 * store the result from that execution in a local static. This avoids
 * validating phases multiple times.
 *
 * @param int $phase
 *   The bootstrap phase to validate to.
 *
 * @return bool
 *   TRUE if bootstrap is possible, FALSE if the validation failed.
 *
 * @see \Drush\Boot\Boot::bootstrap_phases()
 */
function drush_bootstrap_validate($phase) {
  $bootstrap = drush_get_bootstrap_object();
  $phases = _drush_bootstrap_phases(TRUE);
  static $result_cache = array();

  if (!array_key_exists($phase, $result_cache)) {
    drush_set_context('DRUSH_BOOTSTRAP_ERRORS', array());
    drush_set_context('DRUSH_BOOTSTRAP_VALUES', array());

    foreach ($phases as $phase_index => $current_phase) {
      $validated_phase = drush_get_context('DRUSH_BOOTSTRAP_VALIDATION_PHASE', -1);
      if ($phase_index > $phase) {
        break;
      }
      if ($phase_index > $validated_phase) {
        $current_phase .= '_validate';
        if (method_exists($bootstrap, $current_phase)) {
          $result_cache[$phase_index] = $bootstrap->{$current_phase}();
        }
        else {
          $result_cache[$phase_index] = TRUE;
        }
        drush_set_context('DRUSH_BOOTSTRAP_VALIDATION_PHASE', $phase_index);
      }
    }
  }
  return $result_cache[$phase];
}

/**
 * Bootstrap to the specified phase.
 *
 * @param int $max_phase_index
 *   Only attempt bootstrap to the specified level.
 *
 * @return bool
 *   TRUE if the specified bootstrap phase has completed.
 */
function drush_bootstrap_to_phase($max_phase_index) {
  if ($max_phase_index == DRUSH_BOOTSTRAP_MAX) {
    // Bootstrap as far as we can without throwing an error, but log for
    // debugging purposes.
    drush_log(dt("Trying to bootstrap as far as we can."), 'debug');
    drush_bootstrap_max();
    return TRUE;
  }

  drush_log(dt("Bootstrap to phase !phase.", array('!phase' => $max_phase_index)), LogLevel::BOOTSTRAP);
  $phases = _drush_bootstrap_phases();
  $result = TRUE;

  // Try to bootstrap to the maximum possible level, without generating errors
  foreach ($phases as $phase_index) {
    if ($phase_index > $max_phase_index) {
      // Stop trying, since we achieved what was specified.
      break;
    }

    if (drush_bootstrap_validate($phase_index)) {
      if ($phase_index > drush_get_context('DRUSH_BOOTSTRAP_PHASE', DRUSH_BOOTSTRAP_NONE)) {
        $result = drush_bootstrap($phase_index, $max_phase_index);
      }
    }
    else {
      $result = FALSE;
      break;
    }
  }

  return $result;
}

/**
 * Bootstrap to the highest level possible, without triggering any errors.
 *
 * @param int $max_phase_index
 *   (optional) Only attempt bootstrap to the specified level.
 *
 * @return int
 *   The maximum phase to which we bootstrapped.
 */
function drush_bootstrap_max($max_phase_index = FALSE) {
  $phases = _drush_bootstrap_phases(TRUE);
  if (!$max_phase_index) {
    $max_phase_index = count($phases);
  }

  // Try to bootstrap to the maximum possible level, without generating errors.
  foreach ($phases as $phase_index => $current_phase) {
    if ($phase_index > $max_phase_index) {
      // Stop trying, since we achieved what was specified.
      break;
    }

    if (drush_bootstrap_validate($phase_index)) {
      if ($phase_index > drush_get_context('DRUSH_BOOTSTRAP_PHASE')) {
        drush_bootstrap($phase_index, $max_phase_index);
      }
    }
    else {
      // drush_bootstrap_validate() only logs successful validations. For us,
      // knowing what failed can also be important.
      $previous = drush_get_context('DRUSH_BOOTSTRAP_PHASE');
      drush_log(dt("Bootstrap phase !function() failed to validate; continuing at !current().", array('!function' => $current_phase, '!current' => $phases[$previous])), 'debug');
      break;
    }
  }

  return drush_get_context('DRUSH_BOOTSTRAP_PHASE');
}

/**
 * Bootstrap the specified site alias.  The site alias must
 * be a valid alias to a local site.
 *
 * @param $site_record
 *   The alias record for the given site alias.
 *   @see drush_sitealias_get_record().
 * @param $max_phase_index
 *   Only attempt bootstrap to the specified level.
 * @returns TRUE if attempted to bootstrap, or FALSE
 *   if no bootstrap attempt was made.
 */
function drush_bootstrap_max_to_sitealias($site_record, $max_phase_index = NULL) {
  if ((array_key_exists('root', $site_record) && !array_key_exists('remote-host', $site_record))) {
    drush_sitealias_set_alias_context($site_record);
    drush_bootstrap_max($max_phase_index);
    return TRUE;
  }
  return FALSE;
}

/**
 * Helper function to collect any errors that occur during the bootstrap process.
 * Always returns FALSE, for convenience.
 */
function drush_bootstrap_error($code, $message = null) {
  $errors = drush_get_context('DRUSH_BOOTSTRAP_ERRORS');
  $errors[$code] = $message;
  drush_set_context('DRUSH_BOOTSTRAP_ERRORS', $errors);
  return FALSE;
}

function _drush_bootstrap_output_prepare() {
  // Note that as soon as we set the DRUSH_BACKEND context, we change
  // the behavior of drush_log().  It is therefore important that we
  // should not set this context until immediately before we call ob_start
  // (i.e., in this function).
  $backend = drush_set_context('DRUSH_BACKEND', drush_get_option('backend'));
  $quiet = drush_get_context('DRUSH_QUIET');

  if ($backend) {
    // Load options passed as a JSON encoded string through STDIN.
    $stdin_options = _drush_backend_get_stdin();
    if (is_array($stdin_options)) {
      drush_set_context('stdin', $stdin_options);
    }
    // Add an output buffer handler to collect output/pass through backend
    // packets. Using a chunksize of 2 ensures that each line is flushed
    // straight away.
    if ($quiet) {
      // Pass through of backend packets, discard regular output.
      ob_start('drush_backend_output_discard', 2);
    }
    else {
      // Collect output.
      ob_start('drush_backend_output_collect', 2);
    }
  }

  // In non-backend quiet mode we start buffering and discards it on command
  // completion.
  if ($quiet && !$backend) {
    ob_start();
  }
}

/**
 * Used by a Drush extension to request that its Composer autoload
 * files be loaded by Drush, if they have not already been.
 *
 * Usage:
 *
 * function mycommandfile_drush_init() {
 *   drush_autoload(__FILE__)
 * }
 *
 */
function drush_autoload($commandfile) {
  $already_added = commandfiles_cache()->add($commandfile);

  $dir = dirname($commandfile);
  $candidates = array("vendor/autoload.php", "../../../vendor/autoload.php");
  $drush_autoload_file = drush_get_context('DRUSH_VENDOR_PATH', '');

  foreach ($candidates as $candidate) {
    $autoload = $dir . '/' . $candidate;
    if (file_exists($autoload) && (realpath($autoload) != $drush_autoload_file)) {
      include $autoload;
    }
  }
}
