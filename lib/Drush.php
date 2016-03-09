<?php

/**
 * @file
 * Contains \Drush.
 */

use League\Container\ContainerInterface;

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
   * @var League\Container\ContainerInterface|null
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
   * @param League\Container\Container $container
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
   * @return League\Container\Container|null
   *
   * @throws RuntimeException
   */
  public static function getContainer() {
    if (static::$container === NULL) {
      throw new \RuntimeException('\Drush::$container is not initialized yet. \Drupal::setContainer() must be called with a real container.');
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
  public static function bootstrap() {
    return static::bootstrapManager()->bootstrap();
  }

  /**
   * Read the drush info file.
   */
  private static function drush_read_drush_info() {
    $drush_info_file = dirname(__FILE__) . '/../drush.info';

    return parse_ini_file($drush_info_file);
  }
}
