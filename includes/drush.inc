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
 * Get the available global options. Used by list/help commands. All other users
 * should pull options from $application.
 *
 * @param boolean $brief
 *   Return a reduced set of important options. Used by help command.
 *
 * @return
 *   An associative array containing the option definition as the key,
 *   and a descriptive array for each of the available options.  The array
 *   elements for each item are:
 *
 *     - short-form: The shortcut form for specifying the key on the commandline.
 *     - propagate: backend invoke will use drush_get_option to propagate this
 *       option, when set, to any other Drush command that is called.
 *     - context: The drush context where the value of this item is cached.  Used
 *       by backend invoke to propagate values set in code.
 *     - never-post: If TRUE, backend invoke will never POST this item's value
 *       on STDIN; it will always be sent as a commandline option.
 *     - never-propagate: If TRUE, backend invoke will never pass this item on
 *       to the subcommand being executed.
 *     - local-context-only: Backend invoke will only pass this value on for local calls.
 *     - merge: For options such as $options['shell-aliases'] that consist of an array
 *       of items, make a merged array that contains all of the values specified for
 *       all of the contexts (config files) where the option is defined.  The value is stored in
 *       the specified 'context', or in a context named after the option itself if the
 *       context flag is not specified.
 *       IMPORTANT: When the merge flag is used, the option value must be obtained via
 *       drush_get_context('option') rather than drush_get_option('option').
 *     - merge-pathlist: For options such as --include and --config, make a merged list
 *       of options from all contexts; works like the 'merge' flag, but also handles string
 *       values separated by the PATH_SEPARATOR.
 *     - merge-associative: Like 'merge-pathlist', but key values are preserved.
 *     - propagate-cli-value: Used to tell backend invoke to include the value for
 *       this item as specified on the cli.  This can either override 'context'
 *       (e.g., propagate --include from cli value instead of DRUSH_INCLUDE context),
 *       or for an independent global setting (e.g. --user)
 *     - description: The help text for this item. displayed by `drush help`.
 */
function drush_get_global_options($brief = FALSE) {
  $options['root']               = ['short-form' => 'r', 'short-has-arg' => TRUE, 'never-post' => TRUE, 'description' => "Drupal root directory to use.", 'example-value' => 'path'];
  $options['uri']                = ['short-form' => 'l', 'short-has-arg' => TRUE, 'never-post' => TRUE, 'description' => 'URI of the drupal site to use.', 'example-value' => 'http://example.com:8888'];
  $options['verbose']            = ['short-form' => 'v', 'context' => 'DRUSH_VERBOSE', 'description' => 'Display extra information about the command.', 'symfony-conflict' => TRUE];
  $options['debug']              = ['short-form' => 'd', 'context' => 'DRUSH_DEBUG', 'description' => 'Display even more information.'];
  $options['yes']                = ['short-form' => 'y', 'context' => 'DRUSH_AFFIRMATIVE', 'description' => "Assume 'yes' as answer to all prompts."];
  $options['no']                 = ['short-form' => 'n', 'context' => 'DRUSH_NEGATIVE', 'description' => "Assume 'no' as answer to all prompts."];
  $options['help']               = ['short-form' => 'h', 'description' => "This help system."];

  if (!$brief) {
    $options['simulate']           = ['short-form' => 's', 'context' => 'DRUSH_SIMULATE', 'never-propagate' => TRUE, 'description' => "Simulate all relevant actions (don't actually change the system).", 'symfony-conflict' => TRUE];
    $options['pipe']               = ['short-form' => 'p', 'hidden' => TRUE, 'description' => "Emit a compact representation of the command for scripting."];
    $options['php']                = ['description' => "The absolute path to your PHP interpreter, if not 'php' in the path.", 'example-value' => '/path/to/file', 'never-propagate' => TRUE];
    $options['interactive']        = ['short-form' => 'ia', 'description' => "Force interactive mode for commands run on multiple targets (e.g. `drush @site1,@site2 cc --ia`).", 'never-propagate' => TRUE];
    $options['tty']                = ['hidden' => TRUE, 'description' => "Force allocation of tty for remote commands", 'never-propagate' => TRUE];
    $options['quiet']               = ['short-form' => 'q', 'description' => 'Suppress non-error messages.'];
    $options['include']             = ['short-form' => 'i', 'short-has-arg' => TRUE, 'context' => 'DRUSH_INCLUDE', 'never-post' => TRUE, 'propagate-cli-value' => TRUE, 'merge-pathlist' => TRUE, 'description' => "A list of additional directory paths to search for Drush commands. Commandfiles should be placed in a subfolder called 'Commands'.", 'example-value' => '/path/dir'];
    $options['exclude']             = ['propagate-cli-value' => TRUE, 'never-post' => TRUE, 'merge-pathlist' => TRUE, 'description' => "A list of files and directory paths to exclude from consideration when searching for drush commandfiles.", 'example-value' => '/path/dir'];
    $options['config']              = ['short-form' => 'c', 'short-has-arg' => TRUE, 'context' => 'DRUSH_CONFIG', 'never-post' => TRUE, 'propagate-cli-value' => TRUE, 'merge-pathlist' => TRUE, 'description' => "Specify an additional config file to load. See example.drush.yml", 'example-value' => '/path/file'];
    $options['backend']             = ['hidden' => TRUE, 'short-form' => 'b', 'never-propagate' => TRUE, 'description' => "Hide all output and return structured data."];
    $options['choice']              = ['description' => "Provide an answer to a multiple-choice prompt.", 'example-value' => 'number'];
    $options['search-depth']        = ['description' => "Control the depth that drush will search for alias files.", 'example-value' => 'number'];
    $options['ignored-modules']     = ['description' => "Exclude some modules from consideration when searching for drush command files.", 'example-value' => 'token,views'];
    $options['no-label']            = ['description' => "Remove the site label that drush includes in multi-site command output (e.g. `drush @site1,@site2 status`)."];
    $options['label-separator']     = ['description' => "Specify the separator to use in multi-site command output (e.g. `drush @sites pm-list --label-separator=',' --format=csv`).", 'example-value' => ','];
    $options['show-invoke']         = ['description' => "Show all function names which could have been called for the current command. See drush_invoke()."];
    $options['cache-default-class'] = ['description' => "A cache backend class that implements CacheInterface. Defaults to JSONCache.", 'example-value' => 'JSONCache'];
    $options['cache-class-<bin>']   = ['description' => "A cache backend class that implements CacheInterface to use for a specific cache bin.", 'example-value' => 'className'];
    $options['early']               = ['description' => "Include a file (with relative or full path) and call the drush_early_hook() function (where 'hook' is the filename)"];
    $options['alias-path']          = ['context' => 'ALIAS_PATH', 'local-context-only' => TRUE, 'merge-pathlist' => TRUE, 'propagate-cli-value' => TRUE, 'description' => "Specifies the list of paths where drush will search for alias files.", 'example-value' => '/path/alias1:/path/alias2'];
    $options['confirm-rollback']    = ['description' => 'Wait for confirmation before doing a rollback when something goes wrong.'];
    $options['php-options']         = ['hidden' => TRUE, 'description' => "Options to pass to `php` when running drush.  Only effective when specified in a site alias definition.", 'never-propagate' => TRUE, 'example-value' => '-d error_reporting="E_ALL"'];
    $options['halt-on-error']       = ['propagate-cli-value' => TRUE, 'description' => "Manage recoverable errors. Values: 1=Execution halted. 0=Execution continues."];
    $options['remote-host']         = ['hidden' => TRUE, 'description' => 'Remote site to execute drush command on. Managed by site alias.', 'example-value' => 'http://example.com'];
    $options['remote-user']         = ['hidden' => TRUE, 'description' => 'User account to use with a remote drush command. Managed by site alias.', 'example-value' => 'www-data'];
    $options['remote-os']           = ['hidden' => TRUE, 'description' => 'The operating system used on the remote host. Managed by site alias.', 'example-value' => 'linux'];
    $options['strict']              = ['propagate' => TRUE, 'description' => 'Return an error on unrecognized options. --strict=0: Allow unrecognized options.'];
    $options['command-specific']    = ['hidden' => TRUE, 'merge-associative' => TRUE, 'description' => 'Command-specific options.'];
    $options['path-aliases']        = ['hidden' => TRUE, 'never-propagate' => TRUE, 'description' => 'Path aliases from site alias.'];
    $options['ssh-options']         = ['never-propagate' => TRUE, 'description' => 'A string of extra options that will be passed to the ssh command', 'example-value' => '-p 100'];
    $options['local']               = ['propagate' => TRUE, 'description' => 'Don\'t look in global locations for commandfiles, config, and site aliases'];
  }
  return $options;
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
