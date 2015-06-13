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

  function get_profile() {
    return drupal_get_profile();
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
      conf_path() . '/modules',
      'sites/all/modules',
    );
  }

  function contrib_themes_paths() {
    return array(
      conf_path() . '/themes',
      'sites/all/themes',
    );
  }

  function bootstrap_drupal_core($drupal_root) {
    define('DRUPAL_ROOT', $drupal_root);
    $core = DRUPAL_ROOT;

    return $core;
  }

  function bootstrap_drupal_database_validate() {
    if (!parent::bootstrap_drupal_database_validate()) {
      return FALSE;
    }
    // This is like drush_valid_db_credentials, but
    // for Drupal 7, we also want to know if the
    // {blocked_ips} table exists, as the bootstrap will
    // fail if it does not.  If that is the situation,
    // then we'll return FALSE here in validate, so that
    // we do not attempt to start the database bootstrap.
    try {
      $sql = drush_sql_get_class();
      $result = $sql->query('SELECT * from blocked_ips limit 1;');
      if ($result === false) {
        return FALSE;
      }
    }
    catch (Exception $e) {
      // Usually the query above should return a result without
      // throwing an exception, but we'll catch any that are
      // thrown just in case.
      return FALSE;
    }
    return TRUE;
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
