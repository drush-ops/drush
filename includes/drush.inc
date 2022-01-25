<?php

use Drush\Drush;
use Drush\Log\LogLevel;
use Drush\Utils\StringUtils;
use Psr\Log\LoggerInterface;

/**
 * @name Error status definitions
 * @{
 * Error code definitions for interpreting the current error status.
 */

/** The command completed successfully. */
define('DRUSH_SUCCESS', 0);
/** The command could not be completed because the framework has specified errors that have occured. */
define('DRUSH_FRAMEWORK_ERROR', 1);
/** The command was aborted because the user chose to cancel it at some prompt.
This exit code is arbitrarily the same as EX_TEMPFAIL in sysexits.h, although
note that shell error codes are distinct from C exit codes, so this alignment
not meaningful. */
define('DRUSH_EXITCODE_USER_ABORT', 75);
/** The command that was executed resulted in an application error,
The most commom causes for this is invalid PHP or a broken SSH
pipe when using drush_backend_invoke in a distributed manner. */
define('DRUSH_APPLICATION_ERROR', 255);

/**
 * @} End of "name Error status definitions".
 */

/**
 * The number of bytes in a kilobyte. Copied from Drupal.
 */
define('DRUSH_KILOBYTE', 1024);


/**
 * Convert a csv string, or an array of items which
 * may contain csv strings, into an array of items.
 *
 * @param $args
 *   A simple csv string; e.g. 'a,b,c'
 *   or a simple list of items; e.g. array('a','b','c')
 *   or some combination; e.g. array('a,b','c') or array('a,','b,','c,')
 *
 * @returns array
 *   A simple list of items (e.g. array('a','b','c')
 *
 * @deprecated Use \Drush\StringUtils::csvToArray
 */
function _convert_csv_to_array($args) {
  return StringUtils::csvToArray($args);
}

/**
 * Log a message.
 *
 * @param $message
 * @param string $type
 *   A constant from \Drush\Log\LogLevel.
 *
 * @param array $error
 *
 * @deprecated
 *   Use this->logger()->warning('message') (for example) from an Annotated command method.
 */
function drush_log($message, $type = LogLevel::INFO, $error = []) {
    Drush::logger()->log($type, $message, $error);
}

/**
 * Calls a given function, passing through all arguments unchanged.
 *
 * This should be used when calling possibly mutative or destructive functions
 * (e.g. unlink() and other file system functions) so that can be suppressed
 * if the simulation mode is enabled.
 *
 * @param $callable
 *   The name of the function. Any additional arguments are passed along.
 * @return
 *   The return value of the function, or TRUE if simulation mode is enabled.
 *
 */
function drush_op($callable) {
  $args_printed = [];
  $args = func_get_args();
  array_shift($args); // Skip function name
  foreach ($args as $arg) {
    $args_printed[] = is_scalar($arg) ? $arg : (is_object($arg) ? get_class($arg) : gettype($arg));
  }

  if (!is_array($callable)) {
    $callable_string = $callable;
  }
  else {
    if (is_object($callable[0])) {
      $callable_string = get_class($callable[0]) . '::' . $callable[1];
    }
    else {
      $callable_string = implode('::', $callable);
    }
  }

  if (\Drush\Drush::verbose() || \Drush\Drush::simulate()) {
    Drush::logger()->debug('Calling {method}({args})', ['method' => $callable_string, 'args' => implode(", ", $args_printed)]);
  }

  if (\Drush\Drush::simulate()) {
    return TRUE;
  }

  return drush_call_user_func_array($callable, $args);
}

/**
 * Mimic cufa but still call function directly. See http://drupal.org/node/329012#comment-1260752
 */
function drush_call_user_func_array($function, $args = []) {
  if (is_array($function)) {
    // $callable is a method so always use CUFA.
    return call_user_func_array($function, $args);
  }

  switch (count($args)) {
    case 0: return $function(); break;
    case 1: return $function($args[0]); break;
    case 2: return $function($args[0], $args[1]); break;
    case 3: return $function($args[0], $args[1], $args[2]); break;
    case 4: return $function($args[0], $args[1], $args[2], $args[3]); break;
    case 5: return $function($args[0], $args[1], $args[2], $args[3], $args[4]); break;
    case 6: return $function($args[0], $args[1], $args[2], $args[3], $args[4], $args[5]); break;
    case 7: return $function($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6]); break;
    case 8: return $function($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7]); break;
    case 9: return $function($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7], $args[8]); break;
    default: return call_user_func_array($function,$args);
  }
}

/**
 * Get the PHP memory_limit value in bytes.
 */
function drush_memory_limit() {
  $value = trim(ini_get('memory_limit'));
  $last = strtolower($value[strlen($value)-1]);
  $size = (int) rtrim($value, 'GgMmKk');
  switch ($last) {
    case 'g':
      $size *= DRUSH_KILOBYTE;
    case 'm':
      $size *= DRUSH_KILOBYTE;
    case 'k':
      $size *= DRUSH_KILOBYTE;
  }

  return $size;
}


/**
 * Form an associative array from a linear array.
 *
 * This function walks through the provided array and constructs an associative
 * array out of it. The keys of the resulting array will be the values of the
 * input array. The values will be the same as the keys unless a function is
 * specified, in which case the output of the function is used for the values
 * instead.
 *
 * @param $array
 *   A linear array.
 * @param $function
 *   A name of a function to apply to all values before output.
 *
 * @return
 *   An associative array.
 */
function drush_map_assoc($array, $function = NULL) {
  // array_combine() fails with empty arrays:
  // http://bugs.php.net/bug.php?id=34857.
  $array = !empty($array) ? array_combine($array, $array) : [];
  if (is_callable($function)) {
    $array = array_map($function, $array);
  }
  return $array;
}
