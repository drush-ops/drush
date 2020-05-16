<?php

/**
 * @file
 * Wrappers to abstract database operations from Drupal version.
 */

use Drupal\Core\Database\Database;

/**
 * @defgroup dbfunctions Database convenience functions.
 * @{
 */

/**
 * Replace named placeholders in a WHERE snippet.
 *
 * Helper function to allow the usage of Drupal 7+ WHERE snippets
 * with named placeholders in code for Drupal 6.
 *
 * @param $where
 *   String with a WHERE snippet using named placeholders.
 * @param $args
 *   Array of placeholder values.
 * @return
 *   String. $where filled with literals from $args.
 */
function _drush_replace_query_placeholders($where, $args) {
  foreach ($args as $key => $data) {
    if (is_array($data)) {
      $new_keys = array();
      // $data can't have keys that are a prefix of other keys to
      // prevent a corrupted result in the below calls to str_replace().
      // To avoid this we will use a zero padded indexed array of the values of $data.
      $pad_length = strlen((string)count(array_values($data)));
      foreach (array_values($data) as $i => $value) {
        if (!is_numeric($value)) {
          $value = "'".$value."'";
        }
        $new_keys[$key . '_' . str_pad($i, $pad_length, '0', STR_PAD_LEFT)] = $value;
      }
      $where = preg_replace('#' . $key . '\b#', implode(', ', array_keys($new_keys)), $where);
      unset($args[$key]);
      $args += $new_keys;
    }
    else if (!is_numeric($data)) {
      $args[$key] = "'".$data."'";
    }
  }

  foreach ($args as $key => $data) {
    $where = str_replace($key, $data, $where);
  }

  return $where;
}

/**
 * A db_select() that works for any version of Drupal.
 *
 * @param $table
 *   String. The table to operate on.
 * @param $fields
 *   Array or string. Fields affected in this operation. Valid string values are '*' or a single column name.
 * @param $where
 *   String. WHERE snippet for the operation. It uses named placeholders. see @_drush_replace_query_placeholders()
 * @param $args
 *   Array. Arguments for the WHERE snippet.
 * @param $start
 *   Int. Value for OFFSET.
 * @param $length
 *   Int. Value for LIMIT.
 * @param $order_by_field
 *   String. Database column to order by.
 * @param $order_by_direction
 *   ('ASC', 'DESC'). Ordering direction.
 * @return
 *   A database resource.
 */
function drush_db_select($table, $fields = '*', $where = NULL, $args = NULL, $start = NULL, $length = NULL, $order_by_field = NULL, $order_by_direction = 'ASC') {
  if (drush_drupal_major_version() >= 7) {
    if (!is_array($fields)) {
      if ($fields == '*') {
        $fields = array();
      }
      else {
        $fields = array($fields);
      }
    }
    $query = (drush_drupal_major_version() >= 9) ?
      Database::getConnection()->select($table, 't') :
      db_select($table, 't');
    $query->fields('t', $fields);
    if (!empty($where)) {
      $query = $query->where($where, $args);
    }
    if (isset($order_by_field)) {
      $query = $query->orderBy($order_by_field, $order_by_direction);
    }
    if (isset($length)) {
      $query = $query->range($start, $length);
    }
    return $query->execute();
  }
  else {
    if (is_array($fields)) {
      $fields = implode(', ', $fields);
    }
    $query = "SELECT $fields FROM {{$table}}";
    if (!empty($where)) {
      $where = _drush_replace_query_placeholders($where, $args);
      $query .= " WHERE ".$where;
    }
    if (isset($order_by_field)) {
      $query .= " ORDER BY $order_by_field $order_by_direction";
    }
    if (isset($length)) {
      $sql = drush_sql_get_class();
      $db_scheme = $sql->scheme();
      if ($db_scheme == 'oracle')
        return db_query_range($query, $start, $length);
      else {
	      $limit = " LIMIT $length";
	      if (isset($start)) {
          $limit .= " OFFSET $start";
	      }
	      $query .= $limit;
      }
    }

    return db_query($query, $args);
  }
}

/**
 * A db_delete() that works for any version of Drupal.
 *
 * @param $table
 *   String. The table to operate on.
 * @param $where
 *   String. WHERE snippet for the operation. It uses named placeholders. see @_drush_replace_query_placeholders()
 * @param $args
 *   Array. Arguments for the WHERE snippet.
 * @return
 *   Affected rows (except on D7+mysql without a WHERE clause - returns TRUE) or FALSE.
 */
function drush_db_delete($table, $where = NULL, $args = NULL) {
  if (drush_drupal_major_version() >= 9) {
    if (!empty($where)) {
      $query = Database::getConnection()->delete($table)->where($where, $args);
      return $query->execute();
    }
    return Database::getConnection()->truncate($table)->execute();
  }
  elseif (drush_drupal_major_version() >= 7) {
    if (!empty($where)) {
      $query = db_delete($table)->where($where, $args);
      return $query->execute();
    }
    return db_truncate($table)->execute();
  }
  else {
    $query = "DELETE FROM {{$table}}";
    if (!empty($where)) {
      $where = _drush_replace_query_placeholders($where, $args);
      $query .= ' WHERE '.$where;
    }
    if (!db_query($query, $args)) {
      return FALSE;
    }
    return db_affected_rows();
  }
}

/**
 * A db_result() that works consistently for any version of Drupal.
 *
 * @param
 *   A Database result object.
 */
function drush_db_result($result) {
  switch (drush_drupal_major_version()) {
    case 6:
      return db_result($result);
    case 7:
    default:
      return $result->fetchField();
  }
}

/**
 * A db_fetch_object() that works for any version of Drupal.
 *
 * @param
 *   A Database result object.
 */
function drush_db_fetch_object($result) {
  return drush_drupal_major_version() >= 7 ? $result->fetchObject() : db_fetch_object($result);
}

/**
 * @} End of "defgroup dbfunctions".
 */
