<?php
// $Id$

/**
 * @file
 * The drush API implementation and helpers.
 */

//////////////////////////////////////////////////////////////////////////////

define('DRUSH_PATH',           dirname(__FILE__));
define('DRUSH_VERSION',        drush_version());
define('DRUSH_HANDLER_PREFIX', 'drush_');

define('DRUSH_CVS_CMD',        DRUSH_CVS_PATH . ' -d:pserver:anonymous:anonymous@cvs.drupal.org:/cvs/drupal');

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
function drush_commands($like = NULL) {
  $registry = _drush_array_merge_values(drush_register());
  if (!empty($like)) {
    foreach ($registry as $command => $description) {
      if (!preg_match('/^' . preg_quote($like, '/') . '/', $command)) {
        unset($registry[$command]);
      }
    }
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
function drush_dispatch($cmd) {
  if (_drush_dispatch(explode(' ', $cmd), $result))
    return $result;

  trigger_error(t('Unknown command: `%cmd\'', array('%cmd' => $cmd)), E_USER_ERROR);
  return FALSE;
}

/**
 * Calls a given function, passing through all arguments unchanged.
 *
 * Allows operations that have mutative or destructive side effects to be
 * systematically suppressed or recorded.
 */
function drush_op($function) {
  $args = func_get_args();
  array_shift($args); // skip function name

  // TODO: support for recording operations.

  // Only actually perform the operation if not running in simulation mode.
  if (!DRUSH_SIMULATE)
    return call_user_func_array($function, $args);

  // If running in simulation mode, all operations return NULL. This is
  // acceptable since, most of the time, the functions are being called
  // purely for their side effects and the return value is discarded,
  // anyway.
  return NULL;
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
function drush_error($msg = '', $indent = 0) {
  // TODO: print to stderr if running in CLI mode.
  drush_print($msg, $indent);
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
function drush_print_table($rows, $indent = 0, $header = FALSE) {
  if (count($rows) == 0)
    return; // nothing to output

  $indent = str_repeat(' ', $indent);
  $format = _drush_get_table_row_format($rows);

  $counter = 0;
  foreach ($rows as $cols) {
    print $indent . trim(vsprintf($format, $cols)) . "\n";
    if ($counter == 0 && $header) {
      $headers = array();
      foreach ($cols as $col)
        $headers[] = str_repeat('-', strlen($col));
      print $indent . trim(vsprintf($format, $headers)) . "\n";
    }
    $counter++;
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
            $comment = NULL;
            $lines = array_slice(explode("\n", $text), 1); // skip first line with /**
            if (count($lines) > 1) {
              $comment = '';
              foreach ($lines as $line) {
                $line = trim(preg_replace('/^\*\s*/', '', trim($line)));
                if ($line == '@file')
                  continue;
                if ($line == '' || $line == '/')
                  break;
                $comment = trim($comment . ' ' . $line);
              }
            }
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
function _drush_dispatch($lookup, &$result) {
  $lookup = (is_array($lookup) ? $lookup : func_get_args());
  $aliases = drush_alias();
  if ($handler = _drush_dispatch_lookup($lookup, $aliases)) {
    list($handler, $args) = $handler;
    $function = DRUSH_HANDLER_PREFIX . $handler;
    if (!function_exists($function))
      return trigger_error("Call to undefined function $function()", E_USER_ERROR);
    $result = call_user_func_array($function, $args);
    return TRUE;
  }
  return FALSE;
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
// DRUSH CVS HELPERS

/**
 *
 */
function _drush_shell_exec($cmd, $indent = 0) {
  if (DRUSH_VERBOSE) drush_print('Executing: ' . $cmd, $indent);
  if (!DRUSH_SIMULATE) exec($cmd . ' 2>&1', $output, $result);

  if (DRUSH_VERBOSE) {
    foreach ($output as $line) {
      drush_print($line, $indent + 2);
    }
  }

  return ($result == 0); // exit code zero means success
}

/**
 *
 */
function _drush_cvs_is_available() {
  $cmd = DRUSH_CVS_PATH . ' --help';
  // TODO
  return TRUE;
}

/**
 *
 */
function _drush_cvs_checkout($path, $type, $name, $version = NULL) {
  $cmd = DRUSH_CVS_CMD . ' checkout';
  $cmd .= ' -d ' . $path . (empty($version) ? '' : ' -r ' . $version);
  $cmd .= ' contributions/' . $type . 's/' . $name;
  return _drush_shell_exec($cmd, 2);
}

/**
 *
 */
function _drush_cvs_update($path) {
  // FIXME: this is untested as of yet.
  $cmd = DRUSH_CVS_CMD . ' update';
  $cmd .= ' ' . $path;
  return _drush_shell_exec($cmd, 2);
}

//////////////////////////////////////////////////////////////////////////////
// DRUSH OUTPUT HELPERS

/**
 *
 */
function _drush_get_table_row_format($table) {
  $widths = _drush_get_table_column_widths($table);
  $format = implode("\t", array_map(create_function('$width', 'return "%-{$width}s";'), $widths));
  return $format;
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
// DRUSH INPUT HELPERS

/**
 *
 */
function _drush_confirm($msg) {
  if (DRUSH_AFFIRMATIVE) 
    return TRUE;
  return FALSE;
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
