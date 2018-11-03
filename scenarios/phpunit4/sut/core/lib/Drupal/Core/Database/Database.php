<?php

namespace Drupal\Core\Database;

/**
 * Primary front-controller for the database system.
 *
 * This class is uninstantiatable and un-extendable. It acts to encapsulate
 * all control and shepherding of database connections into a single location
 * without the use of globals.
 */
abstract class Database {

  /**
   * Flag to indicate a query call should simply return NULL.
   *
   * This is used for queries that have no reasonable return value anyway, such
   * as INSERT statements to a table without a serial primary key.
   */
  const RETURN_NULL = 0;

  /**
   * Flag to indicate a query call should return the prepared statement.
   */
  const RETURN_STATEMENT = 1;

  /**
   * Flag to indicate a query call should return the number of affected rows.
   */
  const RETURN_AFFECTED = 2;

  /**
   * Flag to indicate a query call should return the "last insert id".
   */
  const RETURN_INSERT_ID = 3;

  /**
   * An nested array of all active connections. It is keyed by database name
   * and target.
   *
   * @var array
   */
  static protected $connections = [];

  /**
   * A processed copy of the database connection information from settings.php.
   *
   * @var array
   */
  static protected $databaseInfo = [];

  /**
   * A list of key/target credentials to simply ignore.
   *
   * @var array
   */
  static protected $ignoreTargets = [];

  /**
   * The key of the currently active database connection.
   *
   * @var string
   */
  static protected $activeKey = 'default';

  /**
   * An array of active query log objects.
   *
   * Every connection has one and only one logger object for all targets and
   * logging keys.
   *
   * array(
   *   '$db_key' => DatabaseLog object.
   * );
   *
   * @var array
   */
  static protected $logs = [];

  /**
   * Starts logging a given logging key on the specified connection.
   *
   * @param string $logging_key
   *   The logging key to log.
   * @param string $key
   *   The database connection key for which we want to log.
   *
   * @return \Drupal\Core\Database\Log
   *   The query log object. Note that the log object does support richer
   *   methods than the few exposed through the Database class, so in some
   *   cases it may be desirable to access it directly.
   *
   * @see \Drupal\Core\Database\Log
   */
  final public static function startLog($logging_key, $key = 'default') {
    if (empty(self::$logs[$key])) {
      self::$logs[$key] = new Log($key);

      // Every target already active for this connection key needs to have the
      // logging object associated with it.
      if (!empty(self::$connections[$key])) {
        foreach (self::$connections[$key] as $connection) {
          $connection->setLogger(self::$logs[$key]);
        }
      }
    }

    self::$logs[$key]->start($logging_key);
    return self::$logs[$key];
  }

  /**
   * Retrieves the queries logged on for given logging key.
   *
   * This method also ends logging for the specified key. To get the query log
   * to date without ending the logger request the logging object by starting
   * it again (which does nothing to an open log key) and call methods on it as
   * desired.
   *
   * @param string $logging_key
   *   The logging key to log.
   * @param string $key
   *   The database connection key for which we want to log.
   *
   * @return array
   *   The query log for the specified logging key and connection.
   *
   * @see \Drupal\Core\Database\Log
   */
  final public static function getLog($logging_key, $key = 'default') {
    if (empty(self::$logs[$key])) {
      return [];
    }
    $queries = self::$logs[$key]->get($logging_key);
    self::$logs[$key]->end($logging_key);
    return $queries;
  }

  /**
   * Gets the connection object for the specified database key and target.
   *
   * @param string $target
   *   The database target name.
   * @param string $key
   *   The database connection key. Defaults to NULL which means the active key.
   *
   * @return \Drupal\Core\Database\Connection
   *   The corresponding connection object.
   */
  final public static function getConnection($target = 'default', $key = NULL) {
    if (!isset($key)) {
      // By default, we want the active connection, set in setActiveConnection.
      $key = self::$activeKey;
    }
    // If the requested target does not exist, or if it is ignored, we fall back
    // to the default target. The target is typically either "default" or
    // "replica", indicating to use a replica SQL server if one is available. If
    // it's not available, then the default/primary server is the correct server
    // to use.
    if (!empty(self::$ignoreTargets[$key][$target]) || !isset(self::$databaseInfo[$key][$target])) {
      $target = 'default';
    }

    if (!isset(self::$connections[$key][$target])) {
      // If necessary, a new connection is opened.
      self::$connections[$key][$target] = self::openConnection($key, $target);
    }
    return self::$connections[$key][$target];
  }

  /**
   * Determines if there is an active connection.
   *
   * Note that this method will return FALSE if no connection has been
   * established yet, even if one could be.
   *
   * @return bool
   *   TRUE if there is at least one database connection established, FALSE
   *   otherwise.
   */
  final public static function isActiveConnection() {
    return !empty(self::$activeKey) && !empty(self::$connections) && !empty(self::$connections[self::$activeKey]);
  }

  /**
   * Sets the active connection to the specified key.
   *
   * @return string|null
   *   The previous database connection key.
   */
  final public static function setActiveConnection($key = 'default') {
    if (!empty(self::$databaseInfo[$key])) {
      $old_key = self::$activeKey;
      self::$activeKey = $key;
      return $old_key;
    }
  }

  /**
   * Process the configuration file for database information.
   *
   * @param array $info
   *   The database connection information, as defined in settings.php. The
   *   structure of this array depends on the database driver it is connecting
   *   to.
   */
  final public static function parseConnectionInfo(array $info) {
    // If there is no "driver" property, then we assume it's an array of
    // possible connections for this target. Pick one at random. That allows
    // us to have, for example, multiple replica servers.
    if (empty($info['driver'])) {
      $info = $info[mt_rand(0, count($info) - 1)];
    }
    // Parse the prefix information.
    if (!isset($info['prefix'])) {
      // Default to an empty prefix.
      $info['prefix'] = [
        'default' => '',
      ];
    }
    elseif (!is_array($info['prefix'])) {
      // Transform the flat form into an array form.
      $info['prefix'] = [
        'default' => $info['prefix'],
      ];
    }
    return $info;
  }

  /**
   * Adds database connection information for a given key/target.
   *
   * This method allows to add new connections at runtime.
   *
   * Under normal circumstances the preferred way to specify database
   * credentials is via settings.php. However, this method allows them to be
   * added at arbitrary times, such as during unit tests, when connecting to
   * admin-defined third party databases, etc.
   *
   * If the given key/target pair already exists, this method will be ignored.
   *
   * @param string $key
   *   The database key.
   * @param string $target
   *   The database target name.
   * @param array $info
   *   The database connection information, as defined in settings.php. The
   *   structure of this array depends on the database driver it is connecting
   *   to.
   */
  final public static function addConnectionInfo($key, $target, array $info) {
    if (empty(self::$databaseInfo[$key][$target])) {
      self::$databaseInfo[$key][$target] = self::parseConnectionInfo($info);
    }
  }

  /**
   * Gets information on the specified database connection.
   *
   * @param string $key
   *   (optional) The connection key for which to return information.
   *
   * @return array|null
   */
  final public static function getConnectionInfo($key = 'default') {
    if (!empty(self::$databaseInfo[$key])) {
      return self::$databaseInfo[$key];
    }
  }

  /**
   * Gets connection information for all available databases.
   *
   * @return array
   */
  final public static function getAllConnectionInfo() {
    return self::$databaseInfo;
  }

  /**
   * Sets connection information for multiple databases.
   *
   * @param array $databases
   *   A multi-dimensional array specifying database connection parameters, as
   *   defined in settings.php.
   */
  final public static function setMultipleConnectionInfo(array $databases) {
    foreach ($databases as $key => $targets) {
      foreach ($targets as $target => $info) {
        self::addConnectionInfo($key, $target, $info);
      }
    }
  }

  /**
   * Rename a connection and its corresponding connection information.
   *
   * @param string $old_key
   *   The old connection key.
   * @param string $new_key
   *   The new connection key.
   *
   * @return bool
   *   TRUE in case of success, FALSE otherwise.
   */
  final public static function renameConnection($old_key, $new_key) {
    if (!empty(self::$databaseInfo[$old_key]) && empty(self::$databaseInfo[$new_key])) {
      // Migrate the database connection information.
      self::$databaseInfo[$new_key] = self::$databaseInfo[$old_key];
      unset(self::$databaseInfo[$old_key]);

      // Migrate over the DatabaseConnection object if it exists.
      if (isset(self::$connections[$old_key])) {
        self::$connections[$new_key] = self::$connections[$old_key];
        unset(self::$connections[$old_key]);
      }

      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Remove a connection and its corresponding connection information.
   *
   * @param string $key
   *   The connection key.
   *
   * @return bool
   *   TRUE in case of success, FALSE otherwise.
   */
  final public static function removeConnection($key) {
    if (isset(self::$databaseInfo[$key])) {
      self::closeConnection(NULL, $key);
      unset(self::$databaseInfo[$key]);
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Opens a connection to the server specified by the given key and target.
   *
   * @param string $key
   *   The database connection key, as specified in settings.php. The default is
   *   "default".
   * @param string $target
   *   The database target to open.
   *
   * @throws \Drupal\Core\Database\ConnectionNotDefinedException
   * @throws \Drupal\Core\Database\DriverNotSpecifiedException
   */
  final protected static function openConnection($key, $target) {
    // If the requested database does not exist then it is an unrecoverable
    // error.
    if (!isset(self::$databaseInfo[$key])) {
      throw new ConnectionNotDefinedException('The specified database connection is not defined: ' . $key);
    }

    if (!$driver = self::$databaseInfo[$key][$target]['driver']) {
      throw new DriverNotSpecifiedException('Driver not specified for this database connection: ' . $key);
    }

    $namespace = static::getDatabaseDriverNamespace(self::$databaseInfo[$key][$target]);
    $driver_class = $namespace . '\\Connection';

    $pdo_connection = $driver_class::open(self::$databaseInfo[$key][$target]);
    $new_connection = new $driver_class($pdo_connection, self::$databaseInfo[$key][$target]);
    $new_connection->setTarget($target);
    $new_connection->setKey($key);

    // If we have any active logging objects for this connection key, we need
    // to associate them with the connection we just opened.
    if (!empty(self::$logs[$key])) {
      $new_connection->setLogger(self::$logs[$key]);
    }

    return $new_connection;
  }

  /**
   * Closes a connection to the server specified by the given key and target.
   *
   * @param string $target
   *   The database target name.  Defaults to NULL meaning that all target
   *   connections will be closed.
   * @param string $key
   *   The database connection key. Defaults to NULL which means the active key.
   */
  public static function closeConnection($target = NULL, $key = NULL) {
    // Gets the active connection by default.
    if (!isset($key)) {
      $key = self::$activeKey;
    }
    // To close a connection, it needs to be set to NULL and removed from the
    // static variable. In all cases, closeConnection() might be called for a
    // connection that was not opened yet, in which case the key is not defined
    // yet and we just ensure that the connection key is undefined.
    if (isset($target)) {
      if (isset(self::$connections[$key][$target])) {
        self::$connections[$key][$target]->destroy();
        self::$connections[$key][$target] = NULL;
      }
      unset(self::$connections[$key][$target]);
    }
    else {
      if (isset(self::$connections[$key])) {
        foreach (self::$connections[$key] as $target => $connection) {
          self::$connections[$key][$target]->destroy();
          self::$connections[$key][$target] = NULL;
        }
      }
      unset(self::$connections[$key]);
    }
  }

  /**
   * Instructs the system to temporarily ignore a given key/target.
   *
   * At times we need to temporarily disable replica queries. To do so, call this
   * method with the database key and the target to disable. That database key
   * will then always fall back to 'default' for that key, even if it's defined.
   *
   * @param string $key
   *   The database connection key.
   * @param string $target
   *   The target of the specified key to ignore.
   */
  public static function ignoreTarget($key, $target) {
    self::$ignoreTargets[$key][$target] = TRUE;
  }

  /**
   * Converts a URL to a database connection info array.
   *
   * @param string $url
   *   The URL.
   * @param string $root
   *   The root directory of the Drupal installation.
   *
   * @return array
   *   The database connection info.
   *
   * @throws \InvalidArgumentException
   *   Exception thrown when the provided URL does not meet the minimum
   *   requirements.
   */
  public static function convertDbUrlToConnectionInfo($url, $root) {
    // Check that the URL is well formed, starting with 'scheme://', where
    // 'scheme' is a database driver name.
    if (preg_match('/^(.*):\/\//', $url, $matches) !== 1) {
      throw new \InvalidArgumentException("Missing scheme in URL '$url'");
    }
    $driver = $matches[1];

    // Discover if the URL has a valid driver scheme. Try with core drivers
    // first.
    $connection_class = "Drupal\\Core\\Database\\Driver\\{$driver}\\Connection";
    if (!class_exists($connection_class)) {
      // If the URL is not relative to a core driver, try with custom ones.
      $connection_class = "Drupal\\Driver\\Database\\{$driver}\\Connection";
      if (!class_exists($connection_class)) {
        throw new \InvalidArgumentException("Can not convert '$url' to a database connection, class '$connection_class' does not exist");
      }
    }

    return $connection_class::createConnectionOptionsFromUrl($url, $root);
  }

  /**
   * Gets database connection info as a URL.
   *
   * @param string $key
   *   (Optional) The database connection key.
   *
   * @return string
   *   The connection info as a URL.
   *
   * @throws \RuntimeException
   *   When the database connection is not defined.
   */
  public static function getConnectionInfoAsUrl($key = 'default') {
    $db_info = static::getConnectionInfo($key);
    if (empty($db_info) || empty($db_info['default'])) {
      throw new \RuntimeException("Database connection $key not defined or missing the 'default' settings");
    }
    $connection_class = static::getDatabaseDriverNamespace($db_info['default']) . '\\Connection';
    return $connection_class::createUrlFromConnectionOptions($db_info['default']);
  }

  /**
   * Gets the PHP namespace of a database driver from the connection info.
   *
   * @param array $connection_info
   *   The database connection information, as defined in settings.php. The
   *   structure of this array depends on the database driver it is connecting
   *   to.
   *
   * @return string
   *   The PHP namespace of the driver's database.
   */
  protected static function getDatabaseDriverNamespace(array $connection_info) {
    if (isset($connection_info['namespace'])) {
      return $connection_info['namespace'];
    }
    // Fallback for Drupal 7 settings.php.
    return 'Drupal\\Core\\Database\\Driver\\' . $connection_info['driver'];
  }

}
