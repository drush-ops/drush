<?php

namespace Drupal\Core\Database;

/**
 * Base Database API class.
 *
 * This class provides a Drupal-specific extension of the PDO database
 * abstraction class in PHP. Every database driver implementation must provide a
 * concrete implementation of it to support special handling required by that
 * database.
 *
 * @see http://php.net/manual/book.pdo.php
 */
abstract class Connection {

  /**
   * The database target this connection is for.
   *
   * We need this information for later auditing and logging.
   *
   * @var string|null
   */
  protected $target = NULL;

  /**
   * The key representing this connection.
   *
   * The key is a unique string which identifies a database connection. A
   * connection can be a single server or a cluster of primary and replicas
   * (use target to pick between primary and replica).
   *
   * @var string|null
   */
  protected $key = NULL;

  /**
   * The current database logging object for this connection.
   *
   * @var \Drupal\Core\Database\Log|null
   */
  protected $logger = NULL;

  /**
   * Tracks the number of "layers" of transactions currently active.
   *
   * On many databases transactions cannot nest.  Instead, we track
   * nested calls to transactions and collapse them into a single
   * transaction.
   *
   * @var array
   */
  protected $transactionLayers = [];

  /**
   * Index of what driver-specific class to use for various operations.
   *
   * @var array
   */
  protected $driverClasses = [];

  /**
   * The name of the Statement class for this connection.
   *
   * @var string
   */
  protected $statementClass = 'Drupal\Core\Database\Statement';

  /**
   * Whether this database connection supports transactions.
   *
   * @var bool
   */
  protected $transactionSupport = TRUE;

  /**
   * Whether this database connection supports transactional DDL.
   *
   * Set to FALSE by default because few databases support this feature.
   *
   * @var bool
   */
  protected $transactionalDDLSupport = FALSE;

  /**
   * An index used to generate unique temporary table names.
   *
   * @var int
   */
  protected $temporaryNameIndex = 0;

  /**
   * The actual PDO connection.
   *
   * @var \PDO
   */
  protected $connection;

  /**
   * The connection information for this connection object.
   *
   * @var array
   */
  protected $connectionOptions = [];

  /**
   * The schema object for this connection.
   *
   * Set to NULL when the schema is destroyed.
   *
   * @var \Drupal\Core\Database\Schema|null
   */
  protected $schema = NULL;

  /**
   * The prefixes used by this database connection.
   *
   * @var array
   */
  protected $prefixes = [];

  /**
   * List of search values for use in prefixTables().
   *
   * @var array
   */
  protected $prefixSearch = [];

  /**
   * List of replacement values for use in prefixTables().
   *
   * @var array
   */
  protected $prefixReplace = [];

  /**
   * List of un-prefixed table names, keyed by prefixed table names.
   *
   * @var array
   */
  protected $unprefixedTablesMap = [];

  /**
   * List of escaped database, table, and field names, keyed by unescaped names.
   *
   * @var array
   */
  protected $escapedNames = [];

  /**
   * List of escaped aliases names, keyed by unescaped aliases.
   *
   * @var array
   */
  protected $escapedAliases = [];

  /**
   * Constructs a Connection object.
   *
   * @param \PDO $connection
   *   An object of the PDO class representing a database connection.
   * @param array $connection_options
   *   An array of options for the connection. May include the following:
   *   - prefix
   *   - namespace
   *   - Other driver-specific options.
   */
  public function __construct(\PDO $connection, array $connection_options) {
    // Initialize and prepare the connection prefix.
    $this->setPrefix(isset($connection_options['prefix']) ? $connection_options['prefix'] : '');

    // Set a Statement class, unless the driver opted out.
    if (!empty($this->statementClass)) {
      $connection->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [$this->statementClass, [$this]]);
    }

    $this->connection = $connection;
    $this->connectionOptions = $connection_options;
  }

  /**
   * Opens a PDO connection.
   *
   * @param array $connection_options
   *   The database connection settings array.
   *
   * @return \PDO
   *   A \PDO object.
   */
  public static function open(array &$connection_options = []) {}

  /**
   * Destroys this Connection object.
   *
   * PHP does not destruct an object if it is still referenced in other
   * variables. In case of PDO database connection objects, PHP only closes the
   * connection when the PDO object is destructed, so any references to this
   * object may cause the number of maximum allowed connections to be exceeded.
   */
  public function destroy() {
    // Destroy all references to this connection by setting them to NULL.
    // The Statement class attribute only accepts a new value that presents a
    // proper callable, so we reset it to PDOStatement.
    if (!empty($this->statementClass)) {
      $this->connection->setAttribute(\PDO::ATTR_STATEMENT_CLASS, ['PDOStatement', []]);
    }
    $this->schema = NULL;
  }

  /**
   * Returns the default query options for any given query.
   *
   * A given query can be customized with a number of option flags in an
   * associative array:
   * - target: The database "target" against which to execute a query. Valid
   *   values are "default" or "replica". The system will first try to open a
   *   connection to a database specified with the user-supplied key. If one
   *   is not available, it will silently fall back to the "default" target.
   *   If multiple databases connections are specified with the same target,
   *   one will be selected at random for the duration of the request.
   * - fetch: This element controls how rows from a result set will be
   *   returned. Legal values include PDO::FETCH_ASSOC, PDO::FETCH_BOTH,
   *   PDO::FETCH_OBJ, PDO::FETCH_NUM, or a string representing the name of a
   *   class. If a string is specified, each record will be fetched into a new
   *   object of that class. The behavior of all other values is defined by PDO.
   *   See http://php.net/manual/pdostatement.fetch.php
   * - return: Depending on the type of query, different return values may be
   *   meaningful. This directive instructs the system which type of return
   *   value is desired. The system will generally set the correct value
   *   automatically, so it is extremely rare that a module developer will ever
   *   need to specify this value. Setting it incorrectly will likely lead to
   *   unpredictable results or fatal errors. Legal values include:
   *   - Database::RETURN_STATEMENT: Return the prepared statement object for
   *     the query. This is usually only meaningful for SELECT queries, where
   *     the statement object is how one accesses the result set returned by the
   *     query.
   *   - Database::RETURN_AFFECTED: Return the number of rows affected by an
   *     UPDATE or DELETE query. Be aware that means the number of rows actually
   *     changed, not the number of rows matched by the WHERE clause.
   *   - Database::RETURN_INSERT_ID: Return the sequence ID (primary key)
   *     created by an INSERT statement on a table that contains a serial
   *     column.
   *   - Database::RETURN_NULL: Do not return anything, as there is no
   *     meaningful value to return. That is the case for INSERT queries on
   *     tables that do not contain a serial column.
   * - throw_exception: By default, the database system will catch any errors
   *   on a query as an Exception, log it, and then rethrow it so that code
   *   further up the call chain can take an appropriate action. To suppress
   *   that behavior and simply return NULL on failure, set this option to
   *   FALSE.
   * - allow_delimiter_in_query: By default, queries which have the ; delimiter
   *   any place in them will cause an exception. This reduces the chance of SQL
   *   injection attacks that terminate the original query and add one or more
   *   additional queries (such as inserting new user accounts). In rare cases,
   *   such as creating an SQL function, a ; is needed and can be allowed by
   *   changing this option to TRUE.
   *
   * @return array
   *   An array of default query options.
   */
  protected function defaultOptions() {
    return [
      'target' => 'default',
      'fetch' => \PDO::FETCH_OBJ,
      'return' => Database::RETURN_STATEMENT,
      'throw_exception' => TRUE,
      'allow_delimiter_in_query' => FALSE,
    ];
  }

  /**
   * Returns the connection information for this connection object.
   *
   * Note that Database::getConnectionInfo() is for requesting information
   * about an arbitrary database connection that is defined. This method
   * is for requesting the connection information of this specific
   * open connection object.
   *
   * @return array
   *   An array of the connection information. The exact list of
   *   properties is driver-dependent.
   */
  public function getConnectionOptions() {
    return $this->connectionOptions;
  }

  /**
   * Set the list of prefixes used by this database connection.
   *
   * @param array|string $prefix
   *   Either a single prefix, or an array of prefixes, in any of the multiple
   *   forms documented in default.settings.php.
   */
  protected function setPrefix($prefix) {
    if (is_array($prefix)) {
      $this->prefixes = $prefix + ['default' => ''];
    }
    else {
      $this->prefixes = ['default' => $prefix];
    }

    // Set up variables for use in prefixTables(). Replace table-specific
    // prefixes first.
    $this->prefixSearch = [];
    $this->prefixReplace = [];
    foreach ($this->prefixes as $key => $val) {
      if ($key != 'default') {
        $this->prefixSearch[] = '{' . $key . '}';
        $this->prefixReplace[] = $val . $key;
      }
    }
    // Then replace remaining tables with the default prefix.
    $this->prefixSearch[] = '{';
    $this->prefixReplace[] = $this->prefixes['default'];
    $this->prefixSearch[] = '}';
    $this->prefixReplace[] = '';

    // Set up a map of prefixed => un-prefixed tables.
    foreach ($this->prefixes as $table_name => $prefix) {
      if ($table_name !== 'default') {
        $this->unprefixedTablesMap[$prefix . $table_name] = $table_name;
      }
    }
  }

  /**
   * Appends a database prefix to all tables in a query.
   *
   * Queries sent to Drupal should wrap all table names in curly brackets. This
   * function searches for this syntax and adds Drupal's table prefix to all
   * tables, allowing Drupal to coexist with other systems in the same database
   * and/or schema if necessary.
   *
   * @param string $sql
   *   A string containing a partial or entire SQL query.
   *
   * @return string
   *   The properly-prefixed string.
   */
  public function prefixTables($sql) {
    return str_replace($this->prefixSearch, $this->prefixReplace, $sql);
  }

  /**
   * Find the prefix for a table.
   *
   * This function is for when you want to know the prefix of a table. This
   * is not used in prefixTables due to performance reasons.
   *
   * @param string $table
   *   (optional) The table to find the prefix for.
   */
  public function tablePrefix($table = 'default') {
    if (isset($this->prefixes[$table])) {
      return $this->prefixes[$table];
    }
    else {
      return $this->prefixes['default'];
    }
  }

  /**
   * Gets a list of individually prefixed table names.
   *
   * @return array
   *   An array of un-prefixed table names, keyed by their fully qualified table
   *   names (i.e. prefix + table_name).
   */
  public function getUnprefixedTablesMap() {
    return $this->unprefixedTablesMap;
  }

  /**
   * Get a fully qualified table name.
   *
   * @param string $table
   *   The name of the table in question.
   *
   * @return string
   */
  public function getFullQualifiedTableName($table) {
    $options = $this->getConnectionOptions();
    $prefix = $this->tablePrefix($table);
    return $options['database'] . '.' . $prefix . $table;
  }

  /**
   * Prepares a query string and returns the prepared statement.
   *
   * This method caches prepared statements, reusing them when
   * possible. It also prefixes tables names enclosed in curly-braces.
   *
   * @param $query
   *   The query string as SQL, with curly-braces surrounding the
   *   table names.
   *
   * @return \Drupal\Core\Database\StatementInterface
   *   A PDO prepared statement ready for its execute() method.
   */
  public function prepareQuery($query) {
    $query = $this->prefixTables($query);

    return $this->connection->prepare($query);
  }

  /**
   * Tells this connection object what its target value is.
   *
   * This is needed for logging and auditing. It's sloppy to do in the
   * constructor because the constructor for child classes has a different
   * signature. We therefore also ensure that this function is only ever
   * called once.
   *
   * @param string $target
   *   (optional) The target this connection is for.
   */
  public function setTarget($target = NULL) {
    if (!isset($this->target)) {
      $this->target = $target;
    }
  }

  /**
   * Returns the target this connection is associated with.
   *
   * @return string|null
   *   The target string of this connection, or NULL if no target is set.
   */
  public function getTarget() {
    return $this->target;
  }

  /**
   * Tells this connection object what its key is.
   *
   * @param string $key
   *   The key this connection is for.
   */
  public function setKey($key) {
    if (!isset($this->key)) {
      $this->key = $key;
    }
  }

  /**
   * Returns the key this connection is associated with.
   *
   * @return string|null
   *   The key of this connection, or NULL if no key is set.
   */
  public function getKey() {
    return $this->key;
  }

  /**
   * Associates a logging object with this connection.
   *
   * @param \Drupal\Core\Database\Log $logger
   *   The logging object we want to use.
   */
  public function setLogger(Log $logger) {
    $this->logger = $logger;
  }

  /**
   * Gets the current logging object for this connection.
   *
   * @return \Drupal\Core\Database\Log|null
   *   The current logging object for this connection. If there isn't one,
   *   NULL is returned.
   */
  public function getLogger() {
    return $this->logger;
  }

  /**
   * Creates the appropriate sequence name for a given table and serial field.
   *
   * This information is exposed to all database drivers, although it is only
   * useful on some of them. This method is table prefix-aware.
   *
   * @param string $table
   *   The table name to use for the sequence.
   * @param string $field
   *   The field name to use for the sequence.
   *
   * @return string
   *   A table prefix-parsed string for the sequence name.
   */
  public function makeSequenceName($table, $field) {
    return $this->prefixTables('{' . $table . '}_' . $field . '_seq');
  }

  /**
   * Flatten an array of query comments into a single comment string.
   *
   * The comment string will be sanitized to avoid SQL injection attacks.
   *
   * @param string[] $comments
   *   An array of query comment strings.
   *
   * @return string
   *   A sanitized comment string.
   */
  public function makeComment($comments) {
    if (empty($comments)) {
      return '';
    }

    // Flatten the array of comments.
    $comment = implode('. ', $comments);

    // Sanitize the comment string so as to avoid SQL injection attacks.
    return '/* ' . $this->filterComment($comment) . ' */ ';
  }

  /**
   * Sanitize a query comment string.
   *
   * Ensure a query comment does not include strings such as "* /" that might
   * terminate the comment early. This avoids SQL injection attacks via the
   * query comment. The comment strings in this example are separated by a
   * space to avoid PHP parse errors.
   *
   * For example, the comment:
   * @code
   * db_update('example')
   *  ->condition('id', $id)
   *  ->fields(array('field2' => 10))
   *  ->comment('Exploit * / DROP TABLE node; --')
   *  ->execute()
   * @endcode
   *
   * Would result in the following SQL statement being generated:
   * @code
   * "/ * Exploit * / DROP TABLE node. -- * / UPDATE example SET field2=..."
   * @endcode
   *
   * Unless the comment is sanitised first, the SQL server would drop the
   * node table and ignore the rest of the SQL statement.
   *
   * @param string $comment
   *   A query comment string.
   *
   * @return string
   *   A sanitized version of the query comment string.
   */
  protected function filterComment($comment = '') {
    // Change semicolons to period to avoid triggering multi-statement check.
    return strtr($comment, ['*' => ' * ', ';' => '.']);
  }

  /**
   * Executes a query string against the database.
   *
   * This method provides a central handler for the actual execution of every
   * query. All queries executed by Drupal are executed as PDO prepared
   * statements.
   *
   * @param string|\Drupal\Core\Database\StatementInterface $query
   *   The query to execute. In most cases this will be a string containing
   *   an SQL query with placeholders. An already-prepared instance of
   *   StatementInterface may also be passed in order to allow calling
   *   code to manually bind variables to a query. If a
   *   StatementInterface is passed, the $args array will be ignored.
   *   It is extremely rare that module code will need to pass a statement
   *   object to this method. It is used primarily for database drivers for
   *   databases that require special LOB field handling.
   * @param array $args
   *   An array of arguments for the prepared statement. If the prepared
   *   statement uses ? placeholders, this array must be an indexed array.
   *   If it contains named placeholders, it must be an associative array.
   * @param array $options
   *   An associative array of options to control how the query is run. The
   *   given options will be merged with self::defaultOptions(). See the
   *   documentation for self::defaultOptions() for details.
   *   Typically, $options['return'] will be set by a default or by a query
   *   builder, and should not be set by a user.
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   *   This method will return one of the following:
   *   - If either $options['return'] === self::RETURN_STATEMENT, or
   *     $options['return'] is not set (due to self::defaultOptions()),
   *     returns the executed statement.
   *   - If $options['return'] === self::RETURN_AFFECTED,
   *     returns the number of rows affected by the query
   *     (not the number matched).
   *   - If $options['return'] === self::RETURN_INSERT_ID,
   *     returns the generated insert ID of the last query.
   *   - If either $options['return'] === self::RETURN_NULL, or
   *     an exception occurs and $options['throw_exception'] evaluates to FALSE,
   *     returns NULL.
   *
   * @throws \Drupal\Core\Database\DatabaseExceptionWrapper
   * @throws \Drupal\Core\Database\IntegrityConstraintViolationException
   * @throws \InvalidArgumentException
   *
   * @see \Drupal\Core\Database\Connection::defaultOptions()
   */
  public function query($query, array $args = [], $options = []) {
    // Use default values if not already set.
    $options += $this->defaultOptions();

    try {
      // We allow either a pre-bound statement object or a literal string.
      // In either case, we want to end up with an executed statement object,
      // which we pass to PDOStatement::execute.
      if ($query instanceof StatementInterface) {
        $stmt = $query;
        $stmt->execute(NULL, $options);
      }
      else {
        $this->expandArguments($query, $args);
        // To protect against SQL injection, Drupal only supports executing one
        // statement at a time.  Thus, the presence of a SQL delimiter (the
        // semicolon) is not allowed unless the option is set.  Allowing
        // semicolons should only be needed for special cases like defining a
        // function or stored procedure in SQL. Trim any trailing delimiter to
        // minimize false positives.
        $query = rtrim($query, ";  \t\n\r\0\x0B");
        if (strpos($query, ';') !== FALSE && empty($options['allow_delimiter_in_query'])) {
          throw new \InvalidArgumentException('; is not supported in SQL strings. Use only one statement at a time.');
        }
        $stmt = $this->prepareQuery($query);
        $stmt->execute($args, $options);
      }

      // Depending on the type of query we may need to return a different value.
      // See DatabaseConnection::defaultOptions() for a description of each
      // value.
      switch ($options['return']) {
        case Database::RETURN_STATEMENT:
          return $stmt;
        case Database::RETURN_AFFECTED:
          $stmt->allowRowCount = TRUE;
          return $stmt->rowCount();
        case Database::RETURN_INSERT_ID:
          $sequence_name = isset($options['sequence_name']) ? $options['sequence_name'] : NULL;
          return $this->connection->lastInsertId($sequence_name);
        case Database::RETURN_NULL:
          return NULL;
        default:
          throw new \PDOException('Invalid return directive: ' . $options['return']);
      }
    }
    catch (\PDOException $e) {
      // Most database drivers will return NULL here, but some of them
      // (e.g. the SQLite driver) may need to re-run the query, so the return
      // value will be the same as for static::query().
      return $this->handleQueryException($e, $query, $args, $options);
    }
  }

  /**
   * Wraps and re-throws any PDO exception thrown by static::query().
   *
   * @param \PDOException $e
   *   The exception thrown by static::query().
   * @param $query
   *   The query executed by static::query().
   * @param array $args
   *   An array of arguments for the prepared statement.
   * @param array $options
   *   An associative array of options to control how the query is run.
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   *   Most database drivers will return NULL when a PDO exception is thrown for
   *   a query, but some of them may need to re-run the query, so they can also
   *   return a \Drupal\Core\Database\StatementInterface object or an integer.
   *
   * @throws \Drupal\Core\Database\DatabaseExceptionWrapper
   * @throws \Drupal\Core\Database\IntegrityConstraintViolationException
   */
  protected function handleQueryException(\PDOException $e, $query, array $args = [], $options = []) {
    if ($options['throw_exception']) {
      // Wrap the exception in another exception, because PHP does not allow
      // overriding Exception::getMessage(). Its message is the extra database
      // debug information.
      $query_string = ($query instanceof StatementInterface) ? $query->getQueryString() : $query;
      $message = $e->getMessage() . ": " . $query_string . "; " . print_r($args, TRUE);
      // Match all SQLSTATE 23xxx errors.
      if (substr($e->getCode(), -6, -3) == '23') {
        $exception = new IntegrityConstraintViolationException($message, $e->getCode(), $e);
      }
      else {
        $exception = new DatabaseExceptionWrapper($message, 0, $e);
      }

      throw $exception;
    }

    return NULL;
  }

  /**
   * Expands out shorthand placeholders.
   *
   * Drupal supports an alternate syntax for doing arrays of values. We
   * therefore need to expand them out into a full, executable query string.
   *
   * @param string $query
   *   The query string to modify.
   * @param array $args
   *   The arguments for the query.
   *
   * @return bool
   *   TRUE if the query was modified, FALSE otherwise.
   *
   * @throws \InvalidArgumentException
   *   This exception is thrown when:
   *   - A placeholder that ends in [] is supplied, and the supplied value is
   *     not an array.
   *   - A placeholder that does not end in [] is supplied, and the supplied
   *     value is an array.
   */
  protected function expandArguments(&$query, &$args) {
    $modified = FALSE;

    // If the placeholder indicated the value to use is an array,  we need to
    // expand it out into a comma-delimited set of placeholders.
    foreach ($args as $key => $data) {
      $is_bracket_placeholder = substr($key, -2) === '[]';
      $is_array_data = is_array($data);
      if ($is_bracket_placeholder && !$is_array_data) {
        throw new \InvalidArgumentException('Placeholders with a trailing [] can only be expanded with an array of values.');
      }
      elseif (!$is_bracket_placeholder) {
        if ($is_array_data) {
          throw new \InvalidArgumentException('Placeholders must have a trailing [] if they are to be expanded with an array of values.');
        }
        // Scalar placeholder - does not need to be expanded.
        continue;
      }
      // Handle expansion of arrays.
      $key_name = str_replace('[]', '__', $key);
      $new_keys = [];
      // We require placeholders to have trailing brackets if the developer
      // intends them to be expanded to an array to make the intent explicit.
      foreach (array_values($data) as $i => $value) {
        // This assumes that there are no other placeholders that use the same
        // name.  For example, if the array placeholder is defined as :example[]
        // and there is already an :example_2 placeholder, this will generate
        // a duplicate key.  We do not account for that as the calling code
        // is already broken if that happens.
        $new_keys[$key_name . $i] = $value;
      }

      // Update the query with the new placeholders.
      $query = str_replace($key, implode(', ', array_keys($new_keys)), $query);

      // Update the args array with the new placeholders.
      unset($args[$key]);
      $args += $new_keys;

      $modified = TRUE;
    }

    return $modified;
  }

  /**
   * Gets the driver-specific override class if any for the specified class.
   *
   * @param string $class
   *   The class for which we want the potentially driver-specific class.
   * @return string
   *   The name of the class that should be used for this driver.
   */
  public function getDriverClass($class) {
    if (empty($this->driverClasses[$class])) {
      if (empty($this->connectionOptions['namespace'])) {
        // Fallback for Drupal 7 settings.php and the test runner script.
        $this->connectionOptions['namespace'] = (new \ReflectionObject($this))->getNamespaceName();
      }
      $driver_class = $this->connectionOptions['namespace'] . '\\' . $class;
      $this->driverClasses[$class] = class_exists($driver_class) ? $driver_class : $class;
    }
    return $this->driverClasses[$class];
  }

  /**
   * Prepares and returns a SELECT query object.
   *
   * @param string $table
   *   The base table for this query, that is, the first table in the FROM
   *   clause. This table will also be used as the "base" table for query_alter
   *   hook implementations.
   * @param string $alias
   *   (optional) The alias of the base table of this query.
   * @param $options
   *   An array of options on the query.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface
   *   An appropriate SelectQuery object for this database connection. Note that
   *   it may be a driver-specific subclass of SelectQuery, depending on the
   *   driver.
   *
   * @see \Drupal\Core\Database\Query\Select
   */
  public function select($table, $alias = NULL, array $options = []) {
    $class = $this->getDriverClass('Select');
    return new $class($table, $alias, $this, $options);
  }

  /**
   * Prepares and returns an INSERT query object.
   *
   * @param string $table
   *   The table to use for the insert statement.
   * @param array $options
   *   (optional) An associative array of options to control how the query is
   *   run. The given options will be merged with
   *   \Drupal\Core\Database\Connection::defaultOptions().
   *
   * @return \Drupal\Core\Database\Query\Insert
   *   A new Insert query object.
   *
   * @see \Drupal\Core\Database\Query\Insert
   * @see \Drupal\Core\Database\Connection::defaultOptions()
   */
  public function insert($table, array $options = []) {
    $class = $this->getDriverClass('Insert');
    return new $class($this, $table, $options);
  }

  /**
   * Prepares and returns a MERGE query object.
   *
   * @param string $table
   *   The table to use for the merge statement.
   * @param array $options
   *   (optional) An array of options on the query.
   *
   * @return \Drupal\Core\Database\Query\Merge
   *   A new Merge query object.
   *
   * @see \Drupal\Core\Database\Query\Merge
   */
  public function merge($table, array $options = []) {
    $class = $this->getDriverClass('Merge');
    return new $class($this, $table, $options);
  }

  /**
   * Prepares and returns an UPSERT query object.
   *
   * @param string $table
   *   The table to use for the upsert query.
   * @param array $options
   *   (optional) An array of options on the query.
   *
   * @return \Drupal\Core\Database\Query\Upsert
   *   A new Upsert query object.
   *
   * @see \Drupal\Core\Database\Query\Upsert
   */
  public function upsert($table, array $options = []) {
    $class = $this->getDriverClass('Upsert');
    return new $class($this, $table, $options);
  }

  /**
   * Prepares and returns an UPDATE query object.
   *
   * @param string $table
   *   The table to use for the update statement.
   * @param array $options
   *   (optional) An associative array of options to control how the query is
   *   run. The given options will be merged with
   *   \Drupal\Core\Database\Connection::defaultOptions().
   *
   * @return \Drupal\Core\Database\Query\Update
   *   A new Update query object.
   *
   * @see \Drupal\Core\Database\Query\Update
   * @see \Drupal\Core\Database\Connection::defaultOptions()
   */
  public function update($table, array $options = []) {
    $class = $this->getDriverClass('Update');
    return new $class($this, $table, $options);
  }

  /**
   * Prepares and returns a DELETE query object.
   *
   * @param string $table
   *   The table to use for the delete statement.
   * @param array $options
   *   (optional) An associative array of options to control how the query is
   *   run. The given options will be merged with
   *   \Drupal\Core\Database\Connection::defaultOptions().
   *
   * @return \Drupal\Core\Database\Query\Delete
   *   A new Delete query object.
   *
   * @see \Drupal\Core\Database\Query\Delete
   * @see \Drupal\Core\Database\Connection::defaultOptions()
   */
  public function delete($table, array $options = []) {
    $class = $this->getDriverClass('Delete');
    return new $class($this, $table, $options);
  }

  /**
   * Prepares and returns a TRUNCATE query object.
   *
   * @param string $table
   *   The table to use for the truncate statement.
   * @param array $options
   *   (optional) An array of options on the query.
   *
   * @return \Drupal\Core\Database\Query\Truncate
   *   A new Truncate query object.
   *
   * @see \Drupal\Core\Database\Query\Truncate
   */
  public function truncate($table, array $options = []) {
    $class = $this->getDriverClass('Truncate');
    return new $class($this, $table, $options);
  }

  /**
   * Returns a DatabaseSchema object for manipulating the schema.
   *
   * This method will lazy-load the appropriate schema library file.
   *
   * @return \Drupal\Core\Database\Schema
   *   The database Schema object for this connection.
   */
  public function schema() {
    if (empty($this->schema)) {
      $class = $this->getDriverClass('Schema');
      $this->schema = new $class($this);
    }
    return $this->schema;
  }

  /**
   * Escapes a database name string.
   *
   * Force all database names to be strictly alphanumeric-plus-underscore.
   * For some database drivers, it may also wrap the database name in
   * database-specific escape characters.
   *
   * @param string $database
   *   An unsanitized database name.
   *
   * @return string
   *   The sanitized database name.
   */
  public function escapeDatabase($database) {
    if (!isset($this->escapedNames[$database])) {
      $this->escapedNames[$database] = preg_replace('/[^A-Za-z0-9_.]+/', '', $database);
    }
    return $this->escapedNames[$database];
  }

  /**
   * Escapes a table name string.
   *
   * Force all table names to be strictly alphanumeric-plus-underscore.
   * For some database drivers, it may also wrap the table name in
   * database-specific escape characters.
   *
   * @param string $table
   *   An unsanitized table name.
   *
   * @return string
   *   The sanitized table name.
   */
  public function escapeTable($table) {
    if (!isset($this->escapedNames[$table])) {
      $this->escapedNames[$table] = preg_replace('/[^A-Za-z0-9_.]+/', '', $table);
    }
    return $this->escapedNames[$table];
  }

  /**
   * Escapes a field name string.
   *
   * Force all field names to be strictly alphanumeric-plus-underscore.
   * For some database drivers, it may also wrap the field name in
   * database-specific escape characters.
   *
   * @param string $field
   *   An unsanitized field name.
   *
   * @return string
   *   The sanitized field name.
   */
  public function escapeField($field) {
    if (!isset($this->escapedNames[$field])) {
      $this->escapedNames[$field] = preg_replace('/[^A-Za-z0-9_.]+/', '', $field);
    }
    return $this->escapedNames[$field];
  }

  /**
   * Escapes an alias name string.
   *
   * Force all alias names to be strictly alphanumeric-plus-underscore. In
   * contrast to DatabaseConnection::escapeField() /
   * DatabaseConnection::escapeTable(), this doesn't allow the period (".")
   * because that is not allowed in aliases.
   *
   * @param string $field
   *   An unsanitized alias name.
   *
   * @return string
   *   The sanitized alias name.
   */
  public function escapeAlias($field) {
    if (!isset($this->escapedAliases[$field])) {
      $this->escapedAliases[$field] = preg_replace('/[^A-Za-z0-9_]+/', '', $field);
    }
    return $this->escapedAliases[$field];
  }

  /**
   * Escapes characters that work as wildcard characters in a LIKE pattern.
   *
   * The wildcard characters "%" and "_" as well as backslash are prefixed with
   * a backslash. Use this to do a search for a verbatim string without any
   * wildcard behavior.
   *
   * For example, the following does a case-insensitive query for all rows whose
   * name starts with $prefix:
   * @code
   * $result = db_query(
   *   'SELECT * FROM person WHERE name LIKE :pattern',
   *   array(':pattern' => db_like($prefix) . '%')
   * );
   * @endcode
   *
   * Backslash is defined as escape character for LIKE patterns in
   * Drupal\Core\Database\Query\Condition::mapConditionOperator().
   *
   * @param string $string
   *   The string to escape.
   *
   * @return string
   *   The escaped string.
   */
  public function escapeLike($string) {
    return addcslashes($string, '\%_');
  }

  /**
   * Determines if there is an active transaction open.
   *
   * @return bool
   *   TRUE if we're currently in a transaction, FALSE otherwise.
   */
  public function inTransaction() {
    return ($this->transactionDepth() > 0);
  }

  /**
   * Determines the current transaction depth.
   *
   * @return int
   *   The current transaction depth.
   */
  public function transactionDepth() {
    return count($this->transactionLayers);
  }

  /**
   * Returns a new DatabaseTransaction object on this connection.
   *
   * @param string $name
   *   (optional) The name of the savepoint.
   *
   * @return \Drupal\Core\Database\Transaction
   *   A Transaction object.
   *
   * @see \Drupal\Core\Database\Transaction
   */
  public function startTransaction($name = '') {
    $class = $this->getDriverClass('Transaction');
    return new $class($this, $name);
  }

  /**
   * Rolls back the transaction entirely or to a named savepoint.
   *
   * This method throws an exception if no transaction is active.
   *
   * @param string $savepoint_name
   *   (optional) The name of the savepoint. The default, 'drupal_transaction',
   *    will roll the entire transaction back.
   *
   * @throws \Drupal\Core\Database\TransactionOutOfOrderException
   * @throws \Drupal\Core\Database\TransactionNoActiveException
   *
   * @see \Drupal\Core\Database\Transaction::rollBack()
   */
  public function rollBack($savepoint_name = 'drupal_transaction') {
    if (!$this->supportsTransactions()) {
      return;
    }
    if (!$this->inTransaction()) {
      throw new TransactionNoActiveException();
    }
    // A previous rollback to an earlier savepoint may mean that the savepoint
    // in question has already been accidentally committed.
    if (!isset($this->transactionLayers[$savepoint_name])) {
      throw new TransactionNoActiveException();
    }

    // We need to find the point we're rolling back to, all other savepoints
    // before are no longer needed. If we rolled back other active savepoints,
    // we need to throw an exception.
    $rolled_back_other_active_savepoints = FALSE;
    while ($savepoint = array_pop($this->transactionLayers)) {
      if ($savepoint == $savepoint_name) {
        // If it is the last the transaction in the stack, then it is not a
        // savepoint, it is the transaction itself so we will need to roll back
        // the transaction rather than a savepoint.
        if (empty($this->transactionLayers)) {
          break;
        }
        $this->query('ROLLBACK TO SAVEPOINT ' . $savepoint);
        $this->popCommittableTransactions();
        if ($rolled_back_other_active_savepoints) {
          throw new TransactionOutOfOrderException();
        }
        return;
      }
      else {
        $rolled_back_other_active_savepoints = TRUE;
      }
    }
    $this->connection->rollBack();
    if ($rolled_back_other_active_savepoints) {
      throw new TransactionOutOfOrderException();
    }
  }

  /**
   * Increases the depth of transaction nesting.
   *
   * If no transaction is already active, we begin a new transaction.
   *
   * @param string $name
   *   The name of the transaction.
   *
   * @throws \Drupal\Core\Database\TransactionNameNonUniqueException
   *
   * @see \Drupal\Core\Database\Transaction
   */
  public function pushTransaction($name) {
    if (!$this->supportsTransactions()) {
      return;
    }
    if (isset($this->transactionLayers[$name])) {
      throw new TransactionNameNonUniqueException($name . " is already in use.");
    }
    // If we're already in a transaction then we want to create a savepoint
    // rather than try to create another transaction.
    if ($this->inTransaction()) {
      $this->query('SAVEPOINT ' . $name);
    }
    else {
      $this->connection->beginTransaction();
    }
    $this->transactionLayers[$name] = $name;
  }

  /**
   * Decreases the depth of transaction nesting.
   *
   * If we pop off the last transaction layer, then we either commit or roll
   * back the transaction as necessary. If no transaction is active, we return
   * because the transaction may have manually been rolled back.
   *
   * @param string $name
   *   The name of the savepoint.
   *
   * @throws \Drupal\Core\Database\TransactionNoActiveException
   * @throws \Drupal\Core\Database\TransactionCommitFailedException
   *
   * @see \Drupal\Core\Database\Transaction
   */
  public function popTransaction($name) {
    if (!$this->supportsTransactions()) {
      return;
    }
    // The transaction has already been committed earlier. There is nothing we
    // need to do. If this transaction was part of an earlier out-of-order
    // rollback, an exception would already have been thrown by
    // Database::rollBack().
    if (!isset($this->transactionLayers[$name])) {
      return;
    }

    // Mark this layer as committable.
    $this->transactionLayers[$name] = FALSE;
    $this->popCommittableTransactions();
  }

  /**
   * Internal function: commit all the transaction layers that can commit.
   */
  protected function popCommittableTransactions() {
    // Commit all the committable layers.
    foreach (array_reverse($this->transactionLayers) as $name => $active) {
      // Stop once we found an active transaction.
      if ($active) {
        break;
      }

      // If there are no more layers left then we should commit.
      unset($this->transactionLayers[$name]);
      if (empty($this->transactionLayers)) {
        if (!$this->connection->commit()) {
          throw new TransactionCommitFailedException();
        }
      }
      else {
        $this->query('RELEASE SAVEPOINT ' . $name);
      }
    }
  }

  /**
   * Runs a limited-range query on this database object.
   *
   * Use this as a substitute for ->query() when a subset of the query is to be
   * returned. User-supplied arguments to the query should be passed in as
   * separate parameters so that they can be properly escaped to avoid SQL
   * injection attacks.
   *
   * @param string $query
   *   A string containing an SQL query.
   * @param int $from
   *   The first result row to return.
   * @param int $count
   *   The maximum number of result rows to return.
   * @param array $args
   *   (optional) An array of values to substitute into the query at placeholder
   *    markers.
   * @param array $options
   *   (optional) An array of options on the query.
   *
   * @return \Drupal\Core\Database\StatementInterface
   *   A database query result resource, or NULL if the query was not executed
   *   correctly.
   */
  abstract public function queryRange($query, $from, $count, array $args = [], array $options = []);

  /**
   * Generates a temporary table name.
   *
   * @return string
   *   A table name.
   */
  protected function generateTemporaryTableName() {
    return "db_temporary_" . $this->temporaryNameIndex++;
  }

  /**
   * Runs a SELECT query and stores its results in a temporary table.
   *
   * Use this as a substitute for ->query() when the results need to stored
   * in a temporary table. Temporary tables exist for the duration of the page
   * request. User-supplied arguments to the query should be passed in as
   * separate parameters so that they can be properly escaped to avoid SQL
   * injection attacks.
   *
   * Note that if you need to know how many results were returned, you should do
   * a SELECT COUNT(*) on the temporary table afterwards.
   *
   * @param string $query
   *   A string containing a normal SELECT SQL query.
   * @param array $args
   *   (optional) An array of values to substitute into the query at placeholder
   *   markers.
   * @param array $options
   *   (optional) An associative array of options to control how the query is
   *   run. See the documentation for DatabaseConnection::defaultOptions() for
   *   details.
   *
   * @return string
   *   The name of the temporary table.
   */
  abstract public function queryTemporary($query, array $args = [], array $options = []);

  /**
   * Returns the type of database driver.
   *
   * This is not necessarily the same as the type of the database itself. For
   * instance, there could be two MySQL drivers, mysql and mysql_mock. This
   * function would return different values for each, but both would return
   * "mysql" for databaseType().
   *
   * @return string
   *   The type of database driver.
   */
  abstract public function driver();

  /**
   * Returns the version of the database server.
   */
  public function version() {
    return $this->connection->getAttribute(\PDO::ATTR_SERVER_VERSION);
  }

  /**
   * Returns the version of the database client.
   */
  public function clientVersion() {
    return $this->connection->getAttribute(\PDO::ATTR_CLIENT_VERSION);
  }

  /**
   * Determines if this driver supports transactions.
   *
   * @return bool
   *   TRUE if this connection supports transactions, FALSE otherwise.
   */
  public function supportsTransactions() {
    return $this->transactionSupport;
  }

  /**
   * Determines if this driver supports transactional DDL.
   *
   * DDL queries are those that change the schema, such as ALTER queries.
   *
   * @return bool
   *   TRUE if this connection supports transactions for DDL queries, FALSE
   *   otherwise.
   */
  public function supportsTransactionalDDL() {
    return $this->transactionalDDLSupport;
  }

  /**
   * Returns the name of the PDO driver for this connection.
   */
  abstract public function databaseType();

  /**
   * Creates a database.
   *
   * In order to use this method, you must be connected without a database
   * specified.
   *
   * @param string $database
   *   The name of the database to create.
   */
  abstract public function createDatabase($database);

  /**
   * Gets any special processing requirements for the condition operator.
   *
   * Some condition types require special processing, such as IN, because
   * the value data they pass in is not a simple value. This is a simple
   * overridable lookup function. Database connections should define only
   * those operators they wish to be handled differently than the default.
   *
   * @param string $operator
   *   The condition operator, such as "IN", "BETWEEN", etc. Case-sensitive.
   *
   * @return
   *   The extra handling directives for the specified operator, or NULL.
   *
   * @see \Drupal\Core\Database\Query\Condition::compile()
   */
  abstract public function mapConditionOperator($operator);

  /**
   * Throws an exception to deny direct access to transaction commits.
   *
   * We do not want to allow users to commit transactions at any time, only
   * by destroying the transaction object or allowing it to go out of scope.
   * A direct commit bypasses all of the safety checks we've built on top of
   * PDO's transaction routines.
   *
   * @throws \Drupal\Core\Database\TransactionExplicitCommitNotAllowedException
   *
   * @see \Drupal\Core\Database\Transaction
   */
  public function commit() {
    throw new TransactionExplicitCommitNotAllowedException();
  }

  /**
   * Retrieves an unique ID from a given sequence.
   *
   * Use this function if for some reason you can't use a serial field. For
   * example, MySQL has no ways of reading of the current value of a sequence
   * and PostgreSQL can not advance the sequence to be larger than a given
   * value. Or sometimes you just need a unique integer.
   *
   * @param $existing_id
   *   (optional) After a database import, it might be that the sequences table
   *   is behind, so by passing in the maximum existing ID, it can be assured
   *   that we never issue the same ID.
   *
   * @return
   *   An integer number larger than any number returned by earlier calls and
   *   also larger than the $existing_id if one was passed in.
   */
  abstract public function nextId($existing_id = 0);

  /**
   * Prepares a statement for execution and returns a statement object
   *
   * Emulated prepared statements does not communicate with the database server
   * so this method does not check the statement.
   *
   * @param string $statement
   *   This must be a valid SQL statement for the target database server.
   * @param array $driver_options
   *   (optional) This array holds one or more key=>value pairs to set
   *   attribute values for the PDOStatement object that this method returns.
   *   You would most commonly use this to set the \PDO::ATTR_CURSOR value to
   *   \PDO::CURSOR_SCROLL to request a scrollable cursor. Some drivers have
   *   driver specific options that may be set at prepare-time. Defaults to an
   *   empty array.
   *
   * @return \PDOStatement|false
   *   If the database server successfully prepares the statement, returns a
   *   \PDOStatement object.
   *   If the database server cannot successfully prepare the statement  returns
   *   FALSE or emits \PDOException (depending on error handling).
   *
   * @throws \PDOException
   *
   * @see \PDO::prepare()
   */
  public function prepare($statement, array $driver_options = []) {
    return $this->connection->prepare($statement, $driver_options);
  }

  /**
   * Quotes a string for use in a query.
   *
   * @param string $string
   *   The string to be quoted.
   * @param int $parameter_type
   *   (optional) Provides a data type hint for drivers that have alternate
   *   quoting styles. Defaults to \PDO::PARAM_STR.
   *
   * @return string|bool
   *   A quoted string that is theoretically safe to pass into an SQL statement.
   *   Returns FALSE if the driver does not support quoting in this way.
   *
   * @see \PDO::quote()
   */
  public function quote($string, $parameter_type = \PDO::PARAM_STR) {
    return $this->connection->quote($string, $parameter_type);
  }

  /**
   * Extracts the SQLSTATE error from the PDOException.
   *
   * @param \Exception $e
   *   The exception
   *
   * @return string
   *   The five character error code.
   */
  protected static function getSQLState(\Exception $e) {
    // The PDOException code is not always reliable, try to see whether the
    // message has something usable.
    if (preg_match('/^SQLSTATE\[(\w{5})\]/', $e->getMessage(), $matches)) {
      return $matches[1];
    }
    else {
      return $e->getCode();
    }
  }

  /**
   * Prevents the database connection from being serialized.
   */
  public function __sleep() {
    throw new \LogicException('The database connection is not serializable. This probably means you are serializing an object that has an indirect reference to the database connection. Adjust your code so that is not necessary. Alternatively, look at DependencySerializationTrait as a temporary solution.');
  }

  /**
   * Creates an array of database connection options from a URL.
   *
   * @internal
   *   This method should not be called. Use
   *   \Drupal\Core\Database\Database::convertDbUrlToConnectionInfo() instead.
   *
   * @param string $url
   *   The URL.
   * @param string $root
   *   The root directory of the Drupal installation. Some database drivers,
   *   like for example SQLite, need this information.
   *
   * @return array
   *   The connection options.
   *
   * @throws \InvalidArgumentException
   *   Exception thrown when the provided URL does not meet the minimum
   *   requirements.
   *
   * @see \Drupal\Core\Database\Database::convertDbUrlToConnectionInfo()
   */
  public static function createConnectionOptionsFromUrl($url, $root) {
    $url_components = parse_url($url);
    if (!isset($url_components['scheme'], $url_components['host'], $url_components['path'])) {
      throw new \InvalidArgumentException('Minimum requirement: driver://host/database');
    }

    $url_components += [
      'user' => '',
      'pass' => '',
      'fragment' => '',
    ];

    // Remove leading slash from the URL path.
    if ($url_components['path'][0] === '/') {
      $url_components['path'] = substr($url_components['path'], 1);
    }

    // Use reflection to get the namespace of the class being called.
    $reflector = new \ReflectionClass(get_called_class());

    $database = [
      'driver' => $url_components['scheme'],
      'username' => $url_components['user'],
      'password' => $url_components['pass'],
      'host' => $url_components['host'],
      'database' => $url_components['path'],
      'namespace' => $reflector->getNamespaceName(),
    ];

    if (isset($url_components['port'])) {
      $database['port'] = $url_components['port'];
    }

    if (!empty($url_components['fragment'])) {
      $database['prefix']['default'] = $url_components['fragment'];
    }

    return $database;
  }

  /**
   * Creates a URL from an array of database connection options.
   *
   * @internal
   *   This method should not be called. Use
   *   \Drupal\Core\Database\Database::getConnectionInfoAsUrl() instead.
   *
   * @param array $connection_options
   *   The array of connection options for a database connection.
   *
   * @return string
   *   The connection info as a URL.
   *
   * @throws \InvalidArgumentException
   *   Exception thrown when the provided array of connection options does not
   *   meet the minimum requirements.
   *
   * @see \Drupal\Core\Database\Database::getConnectionInfoAsUrl()
   */
  public static function createUrlFromConnectionOptions(array $connection_options) {
    if (!isset($connection_options['driver'], $connection_options['database'])) {
      throw new \InvalidArgumentException("As a minimum, the connection options array must contain at least the 'driver' and 'database' keys");
    }

    $user = '';
    if (isset($connection_options['username'])) {
      $user = $connection_options['username'];
      if (isset($connection_options['password'])) {
        $user .= ':' . $connection_options['password'];
      }
      $user .= '@';
    }

    $host = empty($connection_options['host']) ? 'localhost' : $connection_options['host'];

    $db_url = $connection_options['driver'] . '://' . $user . $host;

    if (isset($connection_options['port'])) {
      $db_url .= ':' . $connection_options['port'];
    }

    $db_url .= '/' . $connection_options['database'];

    if (isset($connection_options['prefix']['default']) && $connection_options['prefix']['default'] !== '') {
      $db_url .= '#' . $connection_options['prefix']['default'];
    }

    return $db_url;
  }

}
