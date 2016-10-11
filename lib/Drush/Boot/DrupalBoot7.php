<?php

namespace Drush\Boot;

class DrupalBoot7 extends DrupalBoot {

  function valid_root($path) {
    if (!empty($path) && is_dir($path) && file_exists($path . '/index.php')) {
      // Drupal 7 root.
      // We check for the presence of 'modules/field/field.module' to differentiate this from a D6 site
      $candidate = 'includes/common.inc';
      if (file_exists($path . '/' . $candidate) && file_exists($path . '/misc/drupal.js') && file_exists($path . '/modules/field/field.module')) {
        return $candidate;
      }
    }
  }

  function get_version($drupal_root) {
    $path = $drupal_root . '/includes/bootstrap.inc';
    if (is_file($path)) {
      require_once $path;
      if (defined('VERSION')) {
        return VERSION;
      }
    }
  }

  function get_profile() {
    return drupal_get_profile();
  }

  function add_logger() {
    // If needed, prod module_implements() to recognize our system_watchdog() implementation.
    $dogs = drush_module_implements('watchdog');
    if (!in_array('system', $dogs)) {
      // Note that we must never clear the module_implements() cache because
      // that would trigger larger cache rebuilds with system_cache_tables on
      // every drush invocation. Instead we inject our system_watchdog()
      // implementation direclty into the static cache.
      $implementations = &drupal_static('module_implements');
      $implementations['watchdog']['system'] = FALSE;
      $verified_implementations = &drupal_static('module_implements:verified');
      $verified_implementations['watchdog'] = TRUE;
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
    return parent::bootstrap_drupal_database_validate() && $this->bootstrap_drupal_database_has_table('blocked_ips');
  }

  function bootstrap_drupal_database() {
    drupal_bootstrap(DRUPAL_BOOTSTRAP_DATABASE);
    parent::bootstrap_drupal_database();
  }

  function bootstrap_drupal_configuration() {
    drupal_bootstrap(DRUPAL_BOOTSTRAP_CONFIGURATION);

    // Unset drupal error handler and restore drush's one.
    restore_error_handler();

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

    parent::bootstrap_drupal_full();
  }
}
