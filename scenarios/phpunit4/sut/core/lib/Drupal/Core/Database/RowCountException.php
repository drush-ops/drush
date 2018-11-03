<?php

namespace Drupal\Core\Database;

/**
 * Exception thrown if a SELECT query trying to execute rowCount() on result.
 */
class RowCountException extends \RuntimeException implements DatabaseException {

  public function __construct($message = NULL, $code = 0, \Exception $previous = NULL) {
    if (!isset($message)) {
      $message = "rowCount() is supported for DELETE, INSERT, or UPDATE statements performed with structured query builders only, since they would not be portable across database engines otherwise. If the query builders are not sufficient, set the 'return' option to Database::RETURN_AFFECTED to get the number of affected rows.";
    }
    parent::__construct($message, $code, $previous);
  }

}
