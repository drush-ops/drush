<?php

/**
 * @deprecated see DrupalBootLevels::NONE
 */
define('DRUSH_BOOTSTRAP_NONE', 0);

/**
 * Use MAX instead of drush_bootstrap_to_phase
 *
 * This constant is only usable as the value of the 'bootstrap'
 * item of a command object, or as the parameter to
 * drush_bootstrap_to_phase.  It is not a real bootstrap state.
 *
 * @deprecated see DrupalBootLevels::NONE
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
 * @deprecated see DrupalBootLevels::ROOT
 */
define('DRUSH_BOOTSTRAP_DRUPAL_ROOT',  1);

/**
 * @deprecated see DrupalBootLevels::SITE
 */
define('DRUSH_BOOTSTRAP_DRUPAL_SITE', 2);

/**
 * @deprecated see DrupalBootLevels::CONFIGURATION
 */
define('DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION', 3);

/**
 * @deprecated see DrupalBootLevels::DATABASE
 */
define('DRUSH_BOOTSTRAP_DRUPAL_DATABASE', 4);

/**
 * @deprecated see DrupalBootLevels::FULL
 */
define('DRUSH_BOOTSTRAP_DRUPAL_FULL', 5);

