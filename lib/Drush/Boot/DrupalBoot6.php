<?php

namespace Drush\Boot;

class DrupalBoot6 extends DrupalBoot {

  function valid_root($path) {
    if (!empty($path) && is_dir($path) && file_exists($path . '/index.php')) {
      // Drupal 6 root.
      // We check for the absence of 'modules/field/field.module' to differentiate this from a D7 site.
      // n.b. we want D5 and earlier to match here, if possible, so that we can print a 'not supported'
      // error durring bootstrap.  If someone later adds a commandfile that adds a boot class for
      // Drupal 5, it will be tested first, so we shouldn't get here.
      $candidate = 'includes/common.inc';
      if (file_exists($path . '/' . $candidate) && file_exists($path . '/misc/drupal.js') && !file_exists($path . '/modules/field/field.module')) {
        return $candidate;
      }
    }
  }

  function get_version($drupal_root) {
    $path = $drupal_root . '/modules/system/system.module';
    if (is_file($path)) {
      require_once $path;
      if (defined('VERSION')) {
        return VERSION;
      }
    }
  }

  function get_profile() {
    return variable_get('install_profile', 'standard');
  }

  function add_logger() {
    // If needed, prod module_implements() to recognize our system_watchdog() implementation.
    $dogs = drush_module_implements('watchdog');
    if (!in_array('system', $dogs)) {
      // Note that this resets module_implements cache.
      drush_module_implements('watchdog', FALSE, TRUE);
    }
  }

  function contrib_modules_paths() {
    return array(
      $this->conf_path() . '/modules',
      'sites/all/modules',
    );
  }

  function contrib_themes_paths() {
    return array(
      $this->conf_path() . '/themes',
      'sites/all/themes',
    );
  }

  function bootstrap_drupal_core($drupal_root) {
    define('DRUPAL_ROOT', $drupal_root);
    require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
    $core = DRUPAL_ROOT;

    return $core;
  }

  function bootstrap_drupal_database_validate() {
    return parent::bootstrap_drupal_database_validate() && $this->bootstrap_drupal_database_has_table('cache');
  }

  function bootstrap_drupal_database() {
    drupal_bootstrap(DRUPAL_BOOTSTRAP_DATABASE);
    parent::bootstrap_drupal_database();
  }

  function bootstrap_drupal_configuration() {
    drupal_bootstrap(DRUPAL_BOOTSTRAP_CONFIGURATION);

    parent::bootstrap_drupal_configuration();
  }

  function bootstrap_drupal_full() {
    if (!drush_get_context('DRUSH_QUIET', FALSE)) {
      ob_start();
    }
    drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
    if (!drush_get_context('DRUSH_QUIET', FALSE)) {
      ob_end_clean();
    }

    // Unset drupal error handler and restore drush's one.
    restore_error_handler();

    parent::bootstrap_drupal_full();
  }
}
