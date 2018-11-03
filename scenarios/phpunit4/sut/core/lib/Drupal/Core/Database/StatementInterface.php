<?php

namespace Drupal\Core\Database;

/**
 * Represents a prepared statement.
 *
 * Child implementations should either extend PDOStatement:
 * @code
 * class Drupal\Core\Database\Driver\oracle\Statement extends PDOStatement implements Drupal\Core\Database\StatementInterface {}
 * @endcode
 * or define their own class. If defining their own class, they will also have
 * to implement either the Iterator or IteratorAggregate interface before
 * Drupal\Core\Database\StatementInterface:
 * @code
 * class Drupal\Core\Database\Driver\oracle\Statement implements Iterator, Drupal\Core\Database\StatementInterface {}
 * @endcode
 *
 * @ingroup database
 */
interface StatementInterface extends \Traversable {

  /**
   * Constructs a new PDOStatement object.
   *
   * The PDO manual does not document this constructor, but when overriding the
   * PDOStatement class with a custom without this constructor, PDO will throw
   * the internal exception/warning:
   *
   * "PDO::query(): SQLSTATE[HY000]: General error: user-supplied statement does
   *  not accept constructor arguments"
   *
   * PDO enforces that the access type of this constructor must be protected,
   * and lastly, it also enforces that a custom PDOStatement interface (like
   * this) omits the constructor (declaring it results in fatal errors
   * complaining about "the access type must not be public" if it is public, and
   * "the access type must be omitted" if it is protected; i.e., conflicting
   * statements). The access type has to be protected.
   */
  // protected function __construct(Connection $dbh);

  /**
   * Executes a prepared statement
   *
   * @param $args
   *   An array of values with as many elements as there are bound parameters in
   *   the SQL statement being executed. This can be NULL.
   * @param $options
   *   An array of options for this query.
   *
   * @return
   *   TRUE on success, or FALSE on failure.
   */
  public function execute($args = [], $options = []);

  /**
   * Gets the query string of this statement.
   *
   * @return
   *   The query string, in its form with placeholders.
   */
  public function getQueryString();

  /**
   * Returns the number of rows affected by the last SQL statement.
   *
   * @return
   *   The number of rows affected by the last DELETE, INSERT, or UPDATE
   *   statement executed or throws \Drupal\Core\Database\RowCountException
   *   if the last executed statement was SELECT.
   *
   * @throws \Drupal\Core\Database\RowCountException
   */
  public function rowCount();

  /**
   * Sets the default fetch mode for this statement.
   *
   * See http://php.net/manual/pdo.constants.php for the definition of the
   * constants used.
   *
   * @param $mode
   *   One of the PDO::FETCH_* constants.
   * @param $a1
   *   An option depending of the fetch mode specified by $mode:
   *   - for PDO::FETCH_COLUMN, the index of the column to fetch
   *   - for PDO::FETCH_CLASS, the name of the class to create
   *   - for PDO::FETCH_INTO, the object to add the data to
   * @param $a2
   *   If $mode is PDO::FETCH_CLASS, the optional arguments to pass to the
   *   constructor.
   */
  public function setFetchMode($mode, $a1 = NULL, $a2 = []);

  /**
   * Fetches the next row from a result set.
   *
   * See http://php.net/manual/pdo.constants.php for the definition of the
   * constants used.
   *
   * @param $mode
   *   One of the PDO::FETCH_* constants.
   *   Default to what was specified by setFetchMode().
   * @param $cursor_orientation
   *   Not implemented in all database drivers, don't use.
   * @param $cursor_offset
   *   Not implemented in all database drivers, don't use.
   *
   * @return
   *   A result, formatted according to $mode.
   */
  public function fetch($mode = NULL, $cursor_orientation = NULL, $cursor_offset = NULL);

  /**
   * Returns a single field from the next record of a result set.
   *
   * @param $index
   *   The numeric index of the field to return. Defaults to the first field.
   *
   * @return
   *   A single field from the next record, or FALSE if there is no next record.
   */
  public function fetchField($index = 0);

  /**
   * Fetches the next row and returns it as an object.
   *
   * The object will be of the class specified by StatementInterface::setFetchMode()
   * or stdClass if not specified.
   */
  public function fetchObject();

  /**
   * Fetches the next row and returns it as an associative array.
   *
   * This method corresponds to PDOStatement::fetchObject(), but for associative
   * arrays. For some reason PDOStatement does not have a corresponding array
   * helper method, so one is added.
   *
   * @return
   *   An associative array, or FALSE if there is no next row.
   */
  public function fetchAssoc();

  /**
   * Returns an array containing all of the result set rows.
   *
   * @param $mode
   *   One of the PDO::FETCH_* constants.
   * @param $column_index
   *   If $mode is PDO::FETCH_COLUMN, the index of the column to fetch.
   * @param $constructor_arguments
   *   If $mode is PDO::FETCH_CLASS, the arguments to pass to the constructor.
   *
   * @return
   *   An array of results.
   */
  public function fetchAll($mode = NULL, $column_index = NULL, $constructor_arguments = NULL);

  /**
   * Returns an entire single column of a result set as an indexed array.
   *
   * Note that this method will run the result set to the end.
   *
   * @param $index
   *   The index of the column number to fetch.
   *
   * @return
   *   An indexed array, or an empty array if there is no result set.
   */
  public function fetchCol($index = 0);

  /**
   * Returns the entire result set as a single associative array.
   *
   * This method is only useful for two-column result sets. It will return an
   * associative array where the key is one column from the result set and the
   * value is another field. In most cases, the default of the first two columns
   * is appropriate.
   *
   * Note that this method will run the result set to the end.
   *
   * @param $key_index
   *   The numeric index of the field to use as the array key.
   * @param $value_index
   *   The numeric index of the field to use as the array value.
   *
   * @return
   *   An associative array, or an empty array if there is no result set.
   */
  public function fetchAllKeyed($key_index = 0, $value_index = 1);

  /**
   * Returns the result set as an associative array keyed by the given field.
   *
   * If the given key appears multiple times, later records will overwrite
   * earlier ones.
   *
   * @param $key
   *   The name of the field on which to index the array.
   * @param $fetch
   *   The fetchmode to use. If set to PDO::FETCH_ASSOC, PDO::FETCH_NUM, or
   *   PDO::FETCH_BOTH the returned value with be an array of arrays. For any
   *   other value it will be an array of objects. By default, the fetch mode
   *   set for the query will be used.
   *
   * @return
   *   An associative array, or an empty array if there is no result set.
   */
  public function fetchAllAssoc($key, $fetch = NULL);

}
