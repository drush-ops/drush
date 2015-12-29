<?php

/**
 * @file
 * Contains \Drush.
 */

use Drush\Symfony\BootstrapCompilerPass;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerNotInitializedException;

/**
 * Static Service Container wrapper.
 *
 * This code is analagous to the \Drupal class in Drupal 8.
 *
 * We would like to move Drush towards the model of using constructor
 * injection rather than globals. This class serves as a unified global
 * accessor to arbitrary services for use by legacy Drush code.
 *
 * Advice from Drupal 8's 'Drupal' class:
 *
 * This class exists only to support legacy code that cannot be dependency
 * injected. If your code needs it, consider refactoring it to be object
 * oriented, if possible. When this is not possible, and your code is more
 * than a few non-reusable lines, it is recommended to instantiate an object
 * implementing the actual logic.
 *
 * @code
 *   // Legacy procedural code.
 *   $object = drush_get_context('DRUSH_CLASS_LABEL');
 *
 * Better:
 *   $object = \Drush::service('label');
 *
 * @endcode
 */
class Drush {

  /**
   * The version of Drush from the drush.info file, or FALSE if not read yet.
   *
   * @var string|FALSE
   */
  protected static $version = FALSE;
  protected static $majorVersion = FALSE;
  protected static $minorVersion = FALSE;

  /**
   * The currently active container object, or NULL if not initialized yet.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface|null
   */
  protected static $container;

  /**
   * Return the current Drush version.
   */
  public static function getVersion() {
    if (!static::$version) {
      $drush_info = static::drush_read_drush_info();
      static::$version = $drush_info['drush_version'];
    }
    return static::$version;
  }

  public static function getMajorVersion() {
    if (!static::$majorVersion) {
      $drush_version = static::getVersion();
      $version_parts = explode('.', $drush_version);
      static::$majorVersion = $version_parts[0];
    }
    return static::$majorVersion;
  }

  public static function getMinorVersion() {
    if (!static::$minorVersion) {
      $drush_version = static::getVersion();
      $version_parts = explode('.', $drush_version);
      static::$minorVersion = $version_parts[1];
    }
    return static::$minorVersion;
  }

  /**
   * Sets a new global container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   A new container instance to replace the current.
   */
  public static function setContainer(ContainerInterface $container) {
    static::$container = $container;
  }

  /**
   * Unsets the global container.
   */
  public static function unsetContainer() {
    static::$container = NULL;
  }

  /**
   * Returns the currently active global container.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface|null
   *
   * @throws \Drupal\Core\DependencyInjection\ContainerNotInitializedException
   */
  public static function getContainer() {
    if (static::$container === NULL) {
      throw new ContainerNotInitializedException('\Drush::$container is not initialized yet. \Drupal::setContainer() must be called with a real container.');
    }
    return static::$container;
  }

  /**
   * Returns TRUE if the container has been initialized, FALSE otherwise.
   *
   * @return bool
   */
  public static function hasContainer() {
    return static::$container !== NULL;
  }

  /**
   * Look for all of the services tagged 'bootstrap.boot', and
   * add them to the bootstrap manager.
   *
   * This replaces the BootstrapCompilerPass, because we need
   * to set up these references before we compile the container.
   * Reason: we bootstrap in order to find modules, and modules
   * are allowed to add extensions, and extensions must be added
   * before we compile the container.
   */
  public static function addBootstrapManagerReferences() {
      if (static::hasService('bootstrap.manager')) {
      $bootstrapManager = static::service('bootstrap.manager');
      $taggedServices = static::$container->findTaggedServiceIds(
        'bootstrap.boot'
      );
      foreach ($taggedServices as $id => $tags) {
        $boot = static::service($id);
        $bootstrapManager->add($boot);
      }
    }
  }

  /**
   * Retrieves a service from the container.
   *
   * Use this method if the desired service is not one of those with a dedicated
   * accessor method below. If it is listed below, those methods are preferred
   * as they can return useful type hints.
   *
   * @param string $id
   *   The ID of the service to retrieve.
   *
   * @return mixed
   *   The specified service.
   */
  public static function service($id) {
    return static::getContainer()->get($id);
  }

  /**
   * Indicates if a service is defined in the container.
   *
   * @param string $id
   *   The ID of the service to check.
   *
   * @return bool
   *   TRUE if the specified service exists, FALSE otherwise.
   */
  public static function hasService($id) {
    // Check hasContainer() first in order to always return a Boolean.
    return static::hasContainer() && static::getContainer()->has($id);
  }

  /**
   * Return the Drush logger object.
   *
   * @return LoggerInterface
   */
  public static function logger() {
    return static::service('logger');
  }

  /**
   * Return the Bootstrap Manager.
   *
   * @return Drush\Boot\BootstrapManager
   */
  public static function bootstrapManager() {
    return static::service('bootstrap.manager');
  }

  /**
   * Return the Bootstrap object.
   *
   * @return Drush\Boot\Boot
   */
  public static function getBootstrap() {
    return static::bootstrapManager()->getBootstrap();
  }

  /**
   * Read the drush info file.
   */
  private static function drush_read_drush_info() {
    $drush_info_file = dirname(__FILE__) . '/../drush.info';

    return parse_ini_file($drush_info_file);
  }

}
