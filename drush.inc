<?php
// $Id$

/**
 * @file
 * The drush API implementation and helpers.
 */

//////////////////////////////////////////////////////////////////////////////

define('DRUSH_PATH', dirname(__FILE__));
define('DRUSH_VERSION', drush_version());
define('DRUSH_HANDLER_PREFIX', 'drush_');

//////////////////////////////////////////////////////////////////////////////

/**
 * Returns the drush module version number.
 */
function drush_version() {
  $ini = parse_ini_file(DRUSH_PATH . '/drush.info', FALSE);
  return $ini['version'];
}

/**
 *
 */
function drush_load_builtins() {
  foreach (glob(DRUSH_PATH . '/services/*.drush') as $file) {
    drush_register($file);
    require_once $file;
  }
  return count(drush_register()) > 0;
}

/**
 *
 */
function drush_load_services($path) {
  // TODO
  return TRUE;
}

/**
 *
 */
function drush_register($file = NULL) {
  static $registry = array();
  if (!empty($file)) {
    if (!isset($registry[$file]))
      $registry[$file] = _drush_parse_metadata($file);
  }
  return $registry;
}

/**
 *
 */
function drush_alias($alias = NULL, $target = NULL) {
  static $aliases = array();
  if (!empty($alias)) {
    if (is_array($alias)) // normalize alias
      $alias = trim(implode(' ', $alias));
    if (empty($target)) {
      unset($aliases[$alias]);
    }
    else {
      if (is_array($target)) // normalize target
        $target = trim(implode(' ', $target));
      $aliases[$alias] = $target;
    }
  }
  return $aliases;
}

/**
 *
 */
function drush_unalias($alias) {
  return drush_alias($alias);
}

/**
 *
 */
function drush_resolve_alias($alias) {
  if ($aliases = drush_alias()) {
    if (isset($aliases[$alias]))
      return $aliases[$alias];
  }
  return $alias;
}

/**
 *
 */
function drush_lookup_alias($command) {
  if ($aliases = drush_alias()) {
    if (in_array($command, $aliases))
      return array_search($command, $aliases);
  }
  return $command;
}

/**
 *
 */
function drush_exec($cmd) {
  return _drush_dispatch(explode(' ', $cmd));
}

/**
 *
 */
function drush_die($msg = NULL, $status = NULL) {
  die($msg ? "drush: $msg\n" : '');
}

/**
 *
 */
function drush_print($msg = '', $indent = 0) {
  if ($indent > 0) print str_repeat(' ', $indent);
  print (string)$msg . "\n";
}

/**
 *
 */
function drush_print_table($rows, $indent = 0) {
  if (count($rows) == 0)
    return; // nothing to output
  $format = str_repeat(' ', $indent);
  $format .= _drush_get_table_row_format($rows);
  foreach ($rows as $cols) {
    vprintf($format, $cols);
  }
}

//////////////////////////////////////////////////////////////////////////////
// DRUSH REGISTRY HELPERS

/**
 *
 */
function _drush_parse_metadata($file) {
  // Resolve PHP version differences
  if (!defined('T_ML_COMMENT'))
    define('T_ML_COMMENT', T_COMMENT);
  if (!defined('T_DOC_COMMENT'))
    define('T_DOC_COMMENT', T_ML_COMMENT);

  $functions = array();
  $tokens = token_get_all(file_get_contents($file));
  $last_token = NULL;
  $comment = NULL;
  foreach ($tokens as $token) {
    if (!is_string($token)) {
      list($id, $text) = $token;
      switch ($id) {
        case T_COMMENT:
        case T_ML_COMMENT:
        case T_DOC_COMMENT:
          if (strpos(trim($text), '/**') === 0) {
            $comments = explode("\n", $text);
            $comment = NULL;
            if (count($comments) > 1 && ereg('\s*\*\s*(.*)', $comments[1], $matches))
              $comment = trim($matches[1]);
          }
          break;
        case T_FUNCTION:
          break;
        case T_STRING:
          if ($last_token == T_FUNCTION && strpos($text, 'drush_') === 0) {
            $handler = substr($text, strlen('drush_'));
            $handler = str_replace('_', ' ', $handler);
            $functions[$handler] = $comment;
            $comment = NULL;
          }
          break;
      }
      if ($id != T_WHITESPACE)
        $last_token = $id;
    }
  }
  return $functions;
}

/**
 *
 */
function _drush_dispatch($lookup) {
  $lookup = (is_array($lookup) ? $lookup : func_get_args());
  $aliases = drush_alias();
  if ($handler = _drush_dispatch_lookup($lookup, $aliases)) {
    list($handler, $args) = $handler;
    $function = DRUSH_HANDLER_PREFIX . $handler;
    if (!function_exists($function))
      return trigger_error("Call to undefined function $function()", E_USER_ERROR);
    return call_user_func_array($function, $args);
  }
  return trigger_error('Unknown command: `' . implode(' ', $lookup) . '\'', E_USER_ERROR);
}

/**
 *
 */
function _drush_dispatch_lookup($lookup, $aliases, $args = array()) {
  if (count($lookup) > 0) {

    $handler = implode(' ', $lookup);
    if (isset($aliases[$handler])) {
      $handler = $aliases[$handler];
      if (is_array($handler))
        $handler = implode(' ', $handler);
      $handler = str_replace(' ', '_', $handler);
      return array($handler, $args);
    }

    if (count($lookup) > 1) {
      $handler = str_replace('-', '_', implode('_', $lookup));
      if (function_exists(DRUSH_HANDLER_PREFIX . $handler))
        return array($handler, $args);
    }

    array_unshift($args, array_pop($lookup));
    return _drush_dispatch_lookup($lookup, $aliases, $args);
  }
  return NULL;
}

//////////////////////////////////////////////////////////////////////////////
// DRUSH OUTPUT HELPERS

/**
 *
 */
function _drush_get_table_row_format($table) {
  $widths = _drush_get_table_column_widths($table);
  $format = implode("\t", array_map(create_function('$width', 'return "%-{$width}s";'), $widths));
  return $format . "\n";
}

/**
 *
 */
function _drush_get_table_column_widths($table) {
  $widths = array();
  foreach ($table as $row => $cols) {
    foreach ($cols as $col => $value) {
      $widths[$col] = max($widths[$col], strlen((string)$value));
    }
  }
  return $widths;
}

//////////////////////////////////////////////////////////////////////////////
// GENERAL PHP HELPERS

/**
 * Merges the values of a multidimensional array.
 *
 *   array(0 => array($key1 => $val1, $key2 => $val2, ...),
 *         1 => array($key3 => $val3, $key4 => $val4, ...),
 *         ...)
 *   => array($key1 => $val1, $key2 => $val2,
 *            $key3 => $val3, $key4 => $val4, ...)
 */
function _drush_array_merge_values($array) {
  return call_user_func_array('array_merge', array_values($array));
}

/**
 * Returns an associative array as an array of tuples, where the array key
 * is the first element of the tuple and the value the second.
 *
 *   array($key1 => $val1, $key2 => $val2, ...)
 *   => array(array($key1, $val1), array($key2, $val2), ...)
 */
function _drush_array_tuples($array) {
  static $tuple_func = NULL;
  if (!$tuple_func) $tuple_func = create_function('$k, $v', 'return array($k, $v);');
  return array_map($tuple_func, array_keys($array), array_values($array));
}

//////////////////////////////////////////////////////////////////////////////
