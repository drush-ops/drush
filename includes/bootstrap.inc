<?php

use Drush\Drush;
use Drush\Log\LogLevel;

/**
 * No bootstrap.
 *
 * Commands that only preflight, but do not bootstrap, should use
 * a bootstrap level of DRUSH_BOOTSTRAP_NONE.
 */
define('DRUSH_BOOTSTRAP_NONE', 0);

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
 * @see \Drush\Boot\Boot::bootstrapPhases()
 */
function _drush_bootstrap_phases($function_names = FALSE) {
  return Drush::bootstrapManager()->bootstrapPhases($phase, $phase_max);
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
 * @see \Drush\Boot\Boot::bootstrapPhases()
 */
function drush_bootstrap($phase, $phase_max = FALSE) {
  return Drush::bootstrapManager()->doBootstrap($phase, $phase_max);
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
  return Drush::bootstrapManager()->hasBootstrapped($phase);
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
 * @see \Drush\Boot\Boot::bootstrapPhases()
 */
function drush_bootstrap_validate($phase) {
  return Drush::bootstrapManager()->bootstrapValidate($phase);
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
  return Drush::bootstrapManager()->bootstrapToPhaseIndex($max_phase_index);
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
  return Drush::bootstrapManager()->bootstrapMax($max_phase_index);
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
    // drush_sitealias_set_alias_context($site_record);
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
