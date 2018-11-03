<?php

namespace Drupal\Core\Database\Query;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\Connection;

/**
 * General class for an abstracted DELETE operation.
 *
 * @ingroup database
 */
class Delete extends Query implements ConditionInterface {

  use QueryConditionTrait;

  /**
   * The table from which to delete.
   *
   * @var string
   */
  protected $table;

  /**
   * Constructs a Delete object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   A Connection object.
   * @param string $table
   *   Name of the table to associate with this query.
   * @param array $options
   *   Array of database options.
   */
  public function __construct(Connection $connection, $table, array $options = []) {
    $options['return'] = Database::RETURN_AFFECTED;
    parent::__construct($connection, $options);
    $this->table = $table;

    $this->condition = new Condition('AND');
  }

  /**
   * Executes the DELETE query.
   *
   * @return int
   *   The number of rows affected by the delete query.
   */
  public function execute() {
    $values = [];
    if (count($this->condition)) {
      $this->condition->compile($this->connection, $this);
      $values = $this->condition->arguments();
    }

    return $this->connection->query((string) $this, $values, $this->queryOptions);
  }

  /**
   * Implements PHP magic __toString method to convert the query to a string.
   *
   * @return string
   *   The prepared statement.
   */
  public function __toString() {
    // Create a sanitized comment string to prepend to the query.
    $comments = $this->connection->makeComment($this->comments);

    $query = $comments . 'DELETE FROM {' . $this->connection->escapeTable($this->table) . '} ';

    if (count($this->condition)) {

      $this->condition->compile($this->connection, $this);
      $query .= "\nWHERE " . $this->condition;
    }

    return $query;
  }

}
