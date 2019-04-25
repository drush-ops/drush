<?php

/**
 * @file
 * The drush API implementation and helpers.
 */

use Drush\Drush;
use Drush\Log\LogLevel;
use Drush\Utils\StringUtils;
use Psr\Log\LoggerInterface;

/**
 * @name Error status definitions
 * @{
 * Error code definitions for interpreting the current error status.
 * @see drush_set_error(), drush_get_error(), drush_get_error_log(), drush_cmp_error()
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
 * @} End of "name Error status defintions".
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
 * Convert a nested array into a flat array.  Thows away
 * the array keys, returning only the values.
 *
 * @param $args
 *   An array that may potentially be nested.
 *   e.g. array('a', array('b', 'c'))
 *
 * @returns array
 *   A simple list of items (e.g. array('a','b','c')
 */
function drush_flatten_array($a) {
  $result = [];
  if (!is_array($a)) {
    return [$a];
  }
  foreach ($a as $value) {
    $result = array_merge($result, drush_flatten_array($value));
  }
  return $result;
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
    $options['backend']             = ['short-form' => 'b', 'never-propagate' => TRUE, 'description' => "Hide all output and return structured data."];
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
    $options['site-list']           = ['hidden' => TRUE, 'description' => 'List of sites to run commands on. Managed by site alias.', 'example-value' => '@site1,@site2'];
    $options['reserve-margin']      = ['hidden' => TRUE, 'description' => 'Remove columns from formatted opions. Managed by multi-site command handling.', 'example-value' => 'number'];
    $options['strict']              = ['propagate' => TRUE, 'description' => 'Return an error on unrecognized options. --strict=0: Allow unrecognized options.'];
    $options['command-specific']    = ['hidden' => TRUE, 'merge-associative' => TRUE, 'description' => 'Command-specific options.'];
    $options['site-aliases']        = ['hidden' => TRUE, 'merge-associative' => TRUE, 'description' => 'List of site aliases.'];
    $options['shell-aliases']       = ['hidden' => TRUE, 'merge' => TRUE, 'never-propagate' => TRUE, 'description' => 'List of shell aliases.'];
    $options['path-aliases']        = ['hidden' => TRUE, 'never-propagate' => TRUE, 'description' => 'Path aliases from site alias.'];
    $options['ssh-options']         = ['never-propagate' => TRUE, 'description' => 'A string of extra options that will be passed to the ssh command', 'example-value' => '-p 100'];
    $options['drush-coverage']      = ['hidden' => TRUE, 'never-post' => TRUE, 'propagate-cli-value' => TRUE, 'description' => 'File to save code coverage data into.'];
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
 * Determines the MIME content type of the specified file.
 *
 * The power of this function depends on whether the PHP installation
 * has either mime_content_type() or finfo installed -- if not, only tar,
 * gz, zip and bzip2 types can be detected.
 *
 * If mime type can't be obtained, an error will be set.
 *
 * @return string|bool
 *   The MIME content type of the file or FALSE.
 */
function drush_mime_content_type($filename) {
  $content_type = drush_attempt_mime_content_type($filename);
  if ($content_type) {
    Drush::logger()->info(dt('Mime type for !file is !mt', ['!file' => $filename, '!mt' => $content_type]));
    return $content_type;
  }
  Drush::logger()->error(dt('Unable to determine mime type for !file.', ['!file' => $filename]));
  return false;
}

/**
 * Works like drush_mime_content_type, but does not set an error
 * if the type is unknown.
 */
function drush_attempt_mime_content_type($filename) {
  $content_type = FALSE;
  if (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $content_type = $finfo->file($filename);
    if ($content_type == 'application/octet-stream') {
      Drush::logger()->debug(dt('Mime type for !file is application/octet-stream.', ['!file' => $filename]));
      $content_type = FALSE;
    }
  }
  // If apache is configured in such a way that all files are considered
  // octet-stream (e.g with mod_mime_magic and an http conf that's serving all
  // archives as octet-stream for other reasons) we'll detect mime types on our
  //  own by examing the file's magic header bytes.
  if (!$content_type) {
    Drush::logger()->debug(dt('Examining !file headers.', ['!file' => $filename]));
    if ($file = fopen($filename, 'rb')) {
      $first = fread($file, 2);
      fclose($file);

      if ($first !== FALSE) {
        // Interpret the two bytes as a little endian 16-bit unsigned int.
        $data = unpack('v', $first);
        switch ($data[1]) {
          case 0x8b1f:
            // First two bytes of gzip files are 0x1f, 0x8b (little-endian).
            // See http://www.gzip.org/zlib/rfc-gzip.html#header-trailer
            $content_type = 'application/x-gzip';
            break;

          case 0x4b50:
            // First two bytes of zip files are 0x50, 0x4b ('PK') (little-endian).
            // See http://en.wikipedia.org/wiki/Zip_(file_format)#File_headers
            $content_type = 'application/zip';
            break;

          case 0x5a42:
            // First two bytes of bzip2 files are 0x5a, 0x42 ('BZ') (big-endian).
            // See http://en.wikipedia.org/wiki/Bzip2#File_format
            $content_type = 'application/x-bzip2';
            break;

          default:
            Drush::logger()->debug(dt('Unable to determine mime type from header bytes 0x!hex of !file.', ['!hex' => dechex($data[1]), '!file' => $filename,]));
        }
      }
      else {
        Drush::logger()->warning(dt('Unable to read !file.', ['!file' => $filename]));
      }
    }
    else {
      Drush::logger()->warning(dt('Unable to open !file.', ['!file' => $filename]));
    }
  }

  // 3. Lastly if above methods didn't work, try to guess the mime type from
  // the file extension. This is useful if the file has no identifiable magic
  // header bytes (for example tarballs).
  if (!$content_type) {
    Drush::logger()->debug(dt('Examining !file extension.', ['!file' => $filename]));

    // Remove querystring from the filename, if present.
    $filename = basename(current(explode('?', $filename, 2)));
    $extension_mimetype = [
      '.tar'     => 'application/x-tar',
      '.sql'     => 'application/octet-stream',
    ];
    foreach ($extension_mimetype as $extension => $ct) {
      if (substr($filename, -strlen($extension)) === $extension) {
        $content_type = $ct;
        break;
      }
    }
  }
  return $content_type;
}

/**
 * Check whether a file is a supported tarball.
 *
 * @return mixed
 *   The file content type if it's a tarball. FALSE otherwise.
 */
function drush_file_is_tarball($path) {
  $content_type = drush_attempt_mime_content_type($path);
  $supported = [
    'application/x-bzip2',
    'application/x-gzip',
    'application/x-tar',
    'application/x-zip',
    'application/zip',
  ];
  if (in_array($content_type, $supported)) {
    return $content_type;
  }
  return FALSE;
}

/**
 * @defgroup logging Logging information to be provided as output.
 * @{
 *
 * These functions are primarily for diagnostic purposes, but also provide an overview of tasks that were taken
 * by drush.
 */

/**
 * Add a log message to the log history.
 *
 * This function calls the callback stored in the 'DRUSH_LOG_CALLBACK' context with
 * the resulting entry at the end of execution.
 *
 * This allows you to replace it with custom logging implementations if needed,
 * such as logging to a file or logging to a database (drupal or otherwise).
 *
 * The default callback is the Drush\Log\Logger class with prints the messages
 * to the shell.
 *
 * @param message
 *   String containing the message to be logged.
 * @param type
 *   The type of message to be logged. Common types are 'warning', 'error', 'success' and 'notice'.
 *   A type of 'ok' or 'success' can also be supplied to flag something that worked.
 *   If you want your log messages to print to screen without the user entering
 *   a -v or --verbose flag, use type 'ok' or 'notice', this prints log messages out to
 *   STDERR, which prints to screen (unless you have redirected it). All other
 *   types of messages will be assumed to be info.
 *
 * @deprecated
 *   Use this->logger()->warning (for example) from an Annotated command method.
 */
function drush_log($message, $type = LogLevel::INFO, $error = null) {
  $entry = [
    'type' => $type,
    'message' => $message,
    'timestamp' => microtime(TRUE),
    'memory' => memory_get_usage(),
  ];
  $entry['error'] = $error;

  return _drush_log($entry);
}

/**
 * Call the default logger, or the user's log callback, as
 * appropriate.
 */
function _drush_log($entry) {
  $callback = drush_get_context('DRUSH_LOG_CALLBACK');
  if (!$callback) {
    $callback = Drush::logger();
  }
  if ($callback instanceof LoggerInterface) {
    _drush_log_to_logger($callback, $entry);
  }
  elseif ($callback) {
    $log =& drush_get_context('DRUSH_LOG', []);
    $log[] = $entry;
    drush_backend_packet('log', $entry);
    return $callback($entry);
  }
}

// Maintain compatibility with extensions that hook into
// DRUSH_LOG_CALLBACK (e.g. drush_ctex_bonus)
function _drush_print_log($entry) {
  $drush_logger = Drush::logger();
  if ($drush_logger) {
    _drush_log_to_logger($drush_logger, $entry);
  }
}

function _drush_log_to_logger($logger, $entry) {
    $context = $entry;
    $log_level = $entry['type'];
    $message = $entry['message'];
    unset($entry['type']);
    unset($entry['message']);

    $logger->log($log_level, $message, $context);
}

function drush_log_has_errors($types = [LogLevel::WARNING, LogLevel::ERROR, LogLevel::FAILED]) {
  $log =& drush_get_context('DRUSH_LOG', []);
  foreach ($log as $entry) {
    if (in_array($entry['type'], $types)) {
      return TRUE;
    }
  }
  return FALSE;
}

/**
 * Backend command callback. Add a log message to the log history.
 *
 * @param entry
 *   The log entry.
 */
function drush_backend_packet_log($entry, $backend_options) {
  if (!$backend_options['log']) {
    return;
  }
  if (!is_string($entry['message'])) {
    $entry['message'] = implode("\n", (array)$entry['message']);
  }
  $entry['message'] = $entry['message'];
  if (array_key_exists('#output-label', $backend_options)) {
    $entry['message'] = $backend_options['#output-label'] . $entry['message'];
  }

  // If integrate is FALSE, then log messages are stored in DRUSH_LOG,
  // but are -not- printed to the console.
  if ($backend_options['integrate']) {
    _drush_log($entry);
  }
  else {
    $log =& drush_get_context('DRUSH_LOG', []);
    $log[] = $entry;
    // Yes, this looks odd, but we might in fact be a backend command
    // that ran another backend command.
    drush_backend_packet('log', $entry);
  }
}

/**
 * Retrieve the log messages from the log history
 *
 * @return
 *   Entire log history
 *
 * @deprecated
 */
function drush_get_log() {
  return drush_get_context('DRUSH_LOG', []);
}

/**
 * Run print_r on a variable and log the output.
 */
function dlm($object) {
  Drush::logger()->notice(print_r($object, TRUE));
}

// Copy of format_size() in Drupal.
function drush_format_size($size) {
  if ($size < DRUSH_KILOBYTE) {
    // format_plural() not always available.
    return dt('@count bytes', ['@count' => $size]);
  }
  else {
    $size = $size / DRUSH_KILOBYTE; // Convert bytes to kilobytes.
    $units = [
      dt('@size KB', []),
      dt('@size MB', []),
      dt('@size GB', []),
      dt('@size TB', []),
      dt('@size PB', []),
      dt('@size EB', []),
      dt('@size ZB', []),
      dt('@size YB', []),
    ];
    foreach ($units as $unit) {
      if (round($size, 2) >= DRUSH_KILOBYTE) {
        $size = $size / DRUSH_KILOBYTE;
      }
      else {
        break;
      }
    }
    return str_replace('@size', round($size, 2), $unit);
  }
}

/**
 * @} End of "defgroup logging".
 */

/**
 * @defgroup errorhandling Managing errors that occur in the Drush framework.
 * @{
 * Functions that manage the current error status of the Drush framework.
 *
 * These functions operate by maintaining a static variable that is a equal to the constant DRUSH_FRAMEWORK_ERROR if an
 * error has occurred.
 * This error code is returned at the end of program execution, and provide the shell or calling application with
 * more information on how to diagnose any problems that may have occurred.
 */

/**
 * Set an error code for the error handling system.
 *
 * @param \Drupal\Component\Render\MarkupInterface|string $error
 *   A text string identifying the type of error.
 * @param null|string $message
 *   Optional. Error message to be logged. If no message is specified,
 *   hook_drush_help will be consulted, using a key of 'error:MY_ERROR_STRING'.
 * @param null|string $output_label
 *   Optional. Label to prepend to the error message.
 *
 * @return bool
 *   Always returns FALSE, to allow returning false in the calling functions,
 *   such as <code>return drush_set_error('DRUSH_FRAMEWORK_ERROR')</code>.
 */
function drush_set_error($error, $message = null, $output_label = "") {
  $error_code =& drush_get_context('DRUSH_ERROR_CODE', DRUSH_SUCCESS);
  $error_code = DRUSH_FRAMEWORK_ERROR;

  $error_log =& drush_get_context('DRUSH_ERROR_LOG', []);

  if (is_numeric($error)) {
    $error = 'DRUSH_FRAMEWORK_ERROR';
  }
  elseif (!is_string($error)) {
    // Typical case: D8 TranslatableMarkup, implementing MarkupInterface.
    $error = "$error";
  }

  $message = ($message) ? $message : ''; // drush_command_invoke_all('drush_help', 'error:' . $error);

  if (is_array($message)) {
    $message = implode("\n", $message);
  }

  $error_log[$error][] = $message;
  if (!drush_backend_packet('set_error', ['error' => $error, 'message' => $message])) {
    drush_log(($message) ? $output_label . $message : $output_label . $error, LogLevel::ERROR, $error);
  }

  return FALSE;
}

/**
 * Return the current error handling status
 *
 * @return
 *   The current aggregate error status
 *
 * @deprecated
 */
function drush_get_error() {
  return drush_get_context('DRUSH_ERROR_CODE', DRUSH_SUCCESS);
}

/**
 * Return the current list of errors that have occurred.
 *
 * @return
 *   An associative array of error messages indexed by the type of message.
 *
 * @deprecated
 */
function drush_get_error_log() {
  return drush_get_context('DRUSH_ERROR_LOG', []);
}

/**
 * Check if a specific error status has been set.
 *
 * @param error
 *   A text string identifying the error that has occurred.
 * @return
 *   TRUE if the specified error has been set, FALSE if not
 */
function drush_cmp_error($error) {
  $error_log = drush_get_error_log();

  if (is_numeric($error)) {
    $error = 'DRUSH_FRAMEWORK_ERROR';
  }

  return array_key_exists($error, $error_log);
}

/**
 * Clear error context.
 */
function drush_clear_error() {
  drush_set_context('DRUSH_ERROR_CODE', DRUSH_SUCCESS);
}

/**
 * Turn PHP error handling off.
 *
 * This is commonly used while bootstrapping Drupal for install
 * or updates.
 *
 * This also records the previous error_reporting setting, in
 * case it wasn't recorded previously.
 *
 * @see drush_errors_off()
 */
function drush_errors_off() {
  drush_get_context('DRUSH_ERROR_REPORTING', error_reporting(0));
  ini_set('display_errors', FALSE);
}

/**
 * Turn PHP error handling on.
 *
 * We default to error_reporting() here just in
 * case drush_errors_on() is called before drush_errors_off() and
 * the context is not yet set.
 *
 * @arg $errors string
 *   The default error level to set in drush. This error level will be
 *   carried through further drush_errors_on()/off() calls even if not
 *   provided in later calls.
 *
 * @see error_reporting()
 * @see drush_errors_off()
 */
function drush_errors_on($errors = null) {
  if (!isset($errors)) {
    $errors = error_reporting();
  }
  else {
    drush_set_context('DRUSH_ERROR_REPORTING', $errors);
  }
  error_reporting(drush_get_context('DRUSH_ERROR_REPORTING', $errors));
  ini_set('display_errors', TRUE);
}

/**
 * @} End of "defgroup errorhandling".
 */

/**
 * Get the PHP memory_limit value in bytes.
 */
function drush_memory_limit() {
  $value = trim(ini_get('memory_limit'));
  $last = strtolower($value[strlen($value)-1]);
  $size = (int) substr($value, 0, -1);
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
