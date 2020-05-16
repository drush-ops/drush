<?php

namespace Drush\Boot;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\DrupalKernel;
use Drush\Drupal\DrupalKernel as DrushDrupalKernel;
use Drush\Drupal\DrushServiceModifier;

use Drush\Log\LogLevel;

/**
 * Drupal 8 and Drupal 9 are so similar at the moment that we will use
 * the same bootstrap class for each.
 */
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
    if (!empty($path) && is_dir($path) && file_exists($path . '/autoload.php')) {
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

  function get_version($drupal_root) {
    // Load the autoloader so we can access the class constants.
    drush_drupal_load_autoloader($drupal_root);
    // Drush depends on bootstrap being loaded at this point.
    require_once $drupal_root .'/core/includes/bootstrap.inc';
    if (defined('\Drupal::VERSION')) {
      return \Drupal::VERSION;
    }
  }

  function get_profile() {
    // Favor Drupal::installProfile() if it exists.
    if (method_exists('Drupal', 'installProfile')) {
      return \Drupal::installProfile();
    }
    return \drupal_get_profile();
  }

  function conf_path($require_settings = TRUE, $reset = FALSE, Request $request = NULL) {
    if (!isset($request)) {
      if (\Drupal::hasRequest()) {
        $request = \Drupal::request();
      }
      // @todo Remove once external CLI scripts (Drush) are updated.
      else {
        $request = Request::createFromGlobals();
      }
    }
    if (\Drupal::hasService('kernel')) {
      $site_path = \Drupal::service('kernel')->getSitePath();
    }
    if (!isset($site_path) || empty($site_path)) {
      $site_path = DrupalKernel::findSitePath($request, $require_settings);
    }
    return $site_path;
  }

  function add_logger() {
    // If we're running on Drupal 8 or later, we provide a logger which will send
    // output to drush_log(). This should catch every message logged through every
    // channel.
    $container = \Drupal::getContainer();
    $parser = $container->get('logger.log_message_parser');
    $drushLogger = drush_get_context('DRUSH_LOG_CALLBACK');
    $logger = new \Drush\Log\DrushLog($parser, $drushLogger);
    $container->get('logger.factory')->addLogger($logger);
  }

  function contrib_modules_paths() {
    return array(
      $this->conf_path() . '/modules',
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
      $this->conf_path() . '/themes',
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
    // @todo - use Request::create() and then no need to set PHP superglobals
    $kernelClass = new \ReflectionClass('\Drupal\Core\DrupalKernel');
    if ($kernelClass->hasMethod('addServiceModifier')) {
      $this->kernel = DrupalKernel::createFromRequest($this->request, $classloader, 'prod');
    }
    else {
      $this->kernel = DrushDrupalKernel::createFromRequest($this->request, $classloader, 'prod');
    }
    // @see Drush\Drupal\DrupalKernel::addServiceModifier()
    $this->kernel->addServiceModifier(new DrushServiceModifier());

    // Unset drupal error handler and restore Drush's one.
    restore_error_handler();

    // Disable automated cron if the module is enabled.
    $GLOBALS['config']['automated_cron.settings']['interval'] = 0;

    parent::bootstrap_drupal_configuration();
  }

  function bootstrap_drupal_full() {
    drush_log(dt('About to bootstrap the Drupal 8 Kernel.'), LogLevel::DEBUG);
    // TODO: do we need to do ob_start any longer?
    if (!drush_get_context('DRUSH_QUIET', FALSE)) {
      ob_start();
    }
    $this->kernel->boot();
    if (method_exists($this->kernel, 'preHandle')) {
      $this->kernel->preHandle($this->request);
    }
    else {
      $this->kernel->prepareLegacyRequest($this->request);
    }
    if (!drush_get_context('DRUSH_QUIET', FALSE)) {
      ob_end_clean();
    }
    drush_log(dt('Finished bootstraping the Drupal 8 Kernel.'), LogLevel::DEBUG);

    parent::bootstrap_drupal_full();

    // Get a list of the modules to ignore
    $ignored_modules = drush_get_option_list('ignored-modules', array());

    // We have to get the service command list from the container, because
    // it is constructed in an indirect way during the container initialization.
    // The upshot is that the list of console commands is not available
    // until after $kernel->boot() is called.
    $container = \Drupal::getContainer();
    if ($container->has('drush.service.consolecommands')) {
      $serviceCommandlist = $container->get('drush.service.consolecommands');
      foreach ($serviceCommandlist->getCommandList() as $command) {
        if (!$this->commandIgnored($command, $ignored_modules)) {
          drush_log(dt('Add a command: !name', ['!name' => $command->getName()]), LogLevel::DEBUG);
          annotationcommand_adapter_cache_module_console_commands($command);
        }
      }
    }
    // Do the same thing with the annotation commands.
    if ($container->has('drush.service.consolidationcommands')) {
      $serviceCommandlist = $container->get('drush.service.consolidationcommands');
      foreach ($serviceCommandlist->getCommandList() as $commandhandler) {
        if (!$this->commandIgnored($commandhandler, $ignored_modules)) {
          drush_log(dt('Add a commandhandler: !name', ['!name' => get_class($commandhandler)]), LogLevel::DEBUG);
          annotationcommand_adapter_cache_module_service_commands($commandhandler);
        }
      }
    }
  }

  public function commandIgnored($command, $ignored_modules) {
    if (empty($ignored_modules)) {
      return false;
    }
    $ignored_regex = '#\\\\(' . implode('|', $ignored_modules) . ')\\\\#';
    $class = new \ReflectionClass($command);
    $commandNamespace = $class->getNamespaceName();
    return preg_match($ignored_regex, $commandNamespace);
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
