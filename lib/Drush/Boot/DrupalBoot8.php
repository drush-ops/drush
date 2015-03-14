<?php

namespace Drush\Boot;

use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\DrupalKernel;

class DrupalBoot8 extends DrupalBoot {

  function valid_root($path) {
    if (!empty($path) && is_dir($path) && file_exists($path . '/index.php')) {
      // Additional check for the presence of core/composer.json to
      // grant it is not a Drupal 7 site with a base folder named "core".
      $candidate = 'core/includes/common.inc';
      if (file_exists($path . '/' . $candidate) && file_exists($path . '/core/core.services.yml')) {
        if (file_exists($path . '/core/misc/drupal.js') || file_exists($path . '/core/assets/js/drupal.js')) {
          return $candidate;
        }
      }
    }
  }

  function get_profile() {
    return drupal_get_profile();
  }

  function add_logger() {
    // If we're running on Drupal 8 or later, we provide a logger which will send
    // output to drush_log(). This should catch every message logged through every
    // channel.
    \Drupal::getContainer()->get('logger.factory')->addLogger(new \Drush\Log\DrushLog);
  }

  function contrib_modules_paths() {
    return parent::contrib_modules_paths() + array(
      'modules',
    );
  }

  function contrib_themes_paths() {
    return parent::contrib_themes_paths() + array(
      'themes',
    );
  }

  function bootstrap_drupal_core($drupal_root) {
    $core = DRUPAL_ROOT . '/core';

    return $core;
  }

  function bootstrap_drupal_database() {
    // D8 omits this bootstrap level as nothing special needs to be done.
    parent::bootstrap_drupal_database();
  }

  function bootstrap_drupal_configuration() {
    $this->drupal8_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION);

    // Unset drupal error handler and restore drush's one.
    restore_error_handler();

    parent::bootstrap_drupal_configuration();
  }

  function bootstrap_drupal_full() {
    if (!drush_get_context('DRUSH_QUIET', FALSE)) {
      ob_start();
    }
    $this->drupal8_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_FULL);
    if (!drush_get_context('DRUSH_QUIET', FALSE)) {
      ob_end_clean();
    }

    parent::bootstrap_drupal_full();
  }

  /**
   * Ensures Drupal 8 is bootstrapped to the specified phase.
   *
   * In order to bootstrap Drupal from another PHP script, you can use this code:
   * @code
   *   require_once '/path/to/drupal/core/vendor/autoload.php';
   *   require_once '/path/to/drupal/core/includes/bootstrap.inc';
   *   drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);
   * @endcode
   *
   * @param $phase
   *   A constant telling which phase to bootstrap to. Possible values:
   *   - DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION: Initializes configuration and
   *     kernel.
   *   - DRUSH_BOOTSTRAP_DRUPAL_FULL: Boots the kernel.
   */
  function drupal8_bootstrap($phase = NULL) {
    // Temporary variables used for booting later legacy phases.
    /** @var \Drupal\Core\DrupalKernel $kernel */
    static $kernel;
    static $boot_level = 0;

    if (isset($phase)) {
      $request = Request::createFromGlobals();
      for ($current_phase = $boot_level; $current_phase <= $phase; $current_phase++) {

        switch ($current_phase) {
          case DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION:
            $classloader = drush_drupal_load_autoloader(DRUPAL_ROOT);
            $kernel = DrupalKernel::createFromRequest($request, $classloader, 'prod');
            break;

          case DRUSH_BOOTSTRAP_DRUPAL_FULL:
            $kernel->boot();
            $kernel->prepareLegacyRequest($request);
            break;
        }
      }
      $boot_level = $phase;
    }
  }
}
