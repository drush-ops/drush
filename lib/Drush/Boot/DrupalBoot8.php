<?php

namespace Drush\Boot;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\DrupalKernel;

class DrupalBoot8 extends DrupalBoot {

  /**
   * @var \Drupal\Core\DrupalKernelInterface
   */
  protected $kernel;

  /**
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

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
    return array(
      conf_path() . '/modules',
      'sites/all/modules',
      'modules',
    );
  }

  /**
   * @return array of strings - paths to directories where contrib
   * themes can be found
   */
  function contrib_themes_paths() {
    return array(
      conf_path() . '/themes',
      'sites/all/themes',
      'themes',
    );
  }

  function bootstrap_drupal_core($drupal_root) {
    $core = DRUPAL_ROOT . '/core';

    return $core;
  }

  function bootstrap_drupal_database_validate() {
    return parent::bootstrap_drupal_database_validate() && $this->bootstrap_drupal_database_has_table('key_value');
  }

  function bootstrap_drupal_database() {
    // D8 omits this bootstrap level as nothing special needs to be done.
    parent::bootstrap_drupal_database();
  }

  function bootstrap_drupal_configuration() {
    $this->request = Request::createFromGlobals();
    $classloader = drush_drupal_load_autoloader(DRUPAL_ROOT);
    $this->kernel = DrupalKernel::createFromRequest($this->request, $classloader, 'prod');

    // Unset drupal error handler and restore drush's one.
    restore_error_handler();

    parent::bootstrap_drupal_configuration();
  }

  function bootstrap_drupal_full() {
    if (!drush_get_context('DRUSH_QUIET', FALSE)) {
      ob_start();
    }
    $this->kernel->boot();
    $this->kernel->prepareLegacyRequest($this->request);
    if (!drush_get_context('DRUSH_QUIET', FALSE)) {
      ob_end_clean();
    }

    parent::bootstrap_drupal_full();
  }

  /**
   * {@inheritdoc}
   */
  public function terminate() {
    parent::terminate();

    if ($this->kernel) {
      $response = Response::create('');
      $this->kernel->terminate($this->request, $response);
    }
  }
}
