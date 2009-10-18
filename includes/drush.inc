<?php
// $Id$

/**
 * @file
 * The drush API implementation and helpers.
 */


/**
 * Dispatch a given set of commands.
 * Modules can add commands by implementing hook_drush_command().
 *
 * @param
 *   Command whose callback you want to call, defaults to current command.
 */
function drush_dispatch($command = NULL) {
  $command = ($command) ? $command : drush_get_command();
  $return = FALSE;

  if ($command) {
    // Call the callback function of the active command.
    $return = call_user_func_array($command['callback'], $command['arguments']);
  }
  
  // prevent a '1' at the end of the output
  if ($return === TRUE) {
    $return = '';
  }
  
  // Add a final log entry, just so a timestamp appears.
  drush_log(dt('Command dispatch complete'), 'notice');

  return $return;
}

/**
 * Include a file, selecting a version specific file if available.
 * 
 * For example, if you pass the path "/var/drush" and the name
 * "update" when bootstrapped on a Drupal 6 site it will first check for
 * the presence of "/var/drush/update_6.inc" in include it if exists. If this
 * file does NOT exist it will proceed and check for "/var/drush/update.inc".
 * If neither file exists, it will return FALSE.
 * 
 * @param $path
 *   The path you want to search.
 * @param $name
 *   The file base name you want to include (not including a version suffix
 *   or extension). 
 * @param $version
 *   The version suffix you want to include (could be specific to the software
 *   or platform your are connecting to) - defaults to the current Drupal core
 *   major version.
 * @param $extension
 *   The extension - defaults to ".inc".
 *   
 * @return
 *   TRUE if the file was found and included.
 */
function drush_include($path, $name, $version = NULL, $extension = 'inc') {
  $version = ($version) ? $version : drush_drupal_major_version();
  $file = sprintf("%s/%s_%s.%s", $path, $name, $version, $extension);
  if (file_exists($file)) {
    drush_log(dt('Including version specific file : @file', array('@file' => $file)));
    include_once($file);
    return TRUE;
  }
  $file = sprintf("%s/%s.%s", $path, $name, $extension);
  if (file_exists($file)) {
    drush_log(dt('Including non-version specific file : @file', array('@file' => $file)));
    include_once($file);
    return TRUE;
  }
}

/**
 * Return a structured array of engines of a specific type from commandfiles
 * implementing hook_drush_engine_$type.
 * 
 * Engines are pluggable subsystems. Each engine of a specific type will
 * implement the same set of API functions and perform the same high-level
 * task using a different backend or approach. 
 * 
 * This function/hook is useful when you have a selection of several mutually
 * exclusive options to present to a user to select from.
 * 
 * Other commands are able to extend this list and provide their own engines.
 * The hook can return useful information to help users decide which engine
 * they need, such as description or list of available engine options.
 * 
 * The engine path element will automatically default to a subdirectory (within
 * the directory of the commandfile that implemented the hook) with the name of
 * the type of engine - e.g. an engine "wget" of type "handler" provided by
 * the "pm" commandfile would automatically be found if the file
 * "pm/handler/wget.inc" exists and a specific path is not provided.
 * 
 * @param $type
 *   The type of engine.
 *  
 * @return
 *   A structured array of engines.
 */
function drush_get_engines($type) {
  $engines = array();
  $list = drush_commandfile_list();
  foreach ($list as $commandfile => $path) {
    if (drush_command_hook($commandfile, 'drush_engine_' . $type)) {
      $function = $commandfile . '_drush_engine_' . $type;
      $result = $function();
      foreach ((array)$result as $key => $engine) {
        // Add some defaults
        $engine += array(
          'commandfile' => $commandfile,
          // Engines by default live in a subdirectory of the commandfile that
          // declared them, named as per the type of engine they are.
          'path' => sprintf("%s/%s", dirname($path), $type),
        );
        $engines[$key] = $engine;
      }
    }
  }
  return $engines;
}

/**
 * Include the engine code for a specific named engine of a certain type.
 *
 * If the engine type has implemented hook_drush_engine_$type the path to the
 * engine specified in the array will be used.
 * 
 * If you don't need to present any user options for selecting the engine
 * (which is common if the selection is implied by the running environment)
 * and you don't need to allow other modules to define their own engines you can
 * simply pass the $path to the directory where the engines are, and the
 * appropriate one will be included.
 * 
 * Unlike drush_include this function will set errors if the requested engine
 * cannot be found.
 *
 * @param $type
 *   The type of engine.
 * @param $engine
 *   The key for the engine to be included.
 * @param $version
 *   The version of the engine to be included - defaults to the current Drupal core
 *   major version.
 * @param $path
 *   A path to include from, if the engine has no corresponding
 *   hook_drush_engine_$type item path.
 * @return unknown_type
 */
function drush_include_engine($type, $engine, $version = NULL, $path = NULL) {
  $engines = drush_get_engines($type);
  if (!$path && isset($engines[$engine])) {
    $path = $engines[$engine]['path'];
  }
  if (!$path) {
    return drush_set_error('DRUSH_ENGINE INCLUDE_NO_PATH', dt('No !path was set for including the !type engine !engine.', array('!path' => $path, '!type' => $type, '!engine' => $engine)));
  }
  if (drush_include($path, $engine, $version)) {
    return TRUE;
  }
  return drush_set_error('DRUSH_ENGINE INCLUDE_FAILED', dt('Unable to include the !type engine !engine from !path.' , array('!path' => $path, '!type' => $type, '!engine' => $engine)));
}

/**
 * Detects the version number of the current Drupal installation,
 * if any. Returns false if there is no current Drupal installation,
 * or it is somehow broken.
 *
 * This function relies on the presence of DRUPAL_ROOT/modules/system/system.module
 *
 * @return
 *   A string containing the version number of the current
 *   Drupal installation, if any. Otherwise, return false.
 */
function drush_drupal_version() {
  static $version = FALSE;

  if (!$version) {
    if ($drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT')) {
      if (file_exists($drupal_root . '/modules/system/system.module')) {
        // We can safely include system.module as it contains defines and functions only.
        require_once($drupal_root . '/modules/system/system.module');
        // We just might be dealing with an early Drupal version (pre 4.7)
        if (defined('VERSION')) {
          $version = VERSION;
        }
      }
    }
  }
  return $version;
}

/**
 * Returns the Drupal major version number (5, 6, 7 ...)
 */
function drush_drupal_major_version() {
  $major_version = FALSE;
  if ($version = drush_drupal_version()) {
    $version_parts = explode('.', $version);
    if (is_numeric($version_parts[0])) {
      $major_version = (integer)$version_parts[0];
    }
  }
  return $major_version;
}

/**
 * A db_result() that works for any version of Drupal.
 *
 * @param
 *   A Database result object.
 */ 
function drush_db_result($result) {
  return drush_drupal_major_version() >= 7 ? $result->fetchField() : db_result($result);
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
 * Save a string to a temporary file. Does not depend on Drupal's API.
 *
 * @param string $data 
 * @return string
 *   A path to the file.
 */
function drush_save_data_to_temp_file($data) {
  static $fp;

  $fp = tmpfile();
  fwrite($fp, $data);
  $meta_data = stream_get_meta_data($fp);
  return $meta_data['uri'];
}

/**
 * Calls a given function, passing through all arguments unchanged.
 *
 * This should be used when calling possibly mutative or destructive functions
 * (e.g. unlink() and other file system functions) so that can be suppressed
 * if the simulation mode is enabled.
 *
 * @param $function
 *   The name of the function.
 * @return
 *   The return value of the function, or TRUE if simulation mode is enabled.
 */
function drush_op($function) {
  $args = func_get_args();
  array_shift($args); // Skip function name

  if (drush_get_context('DRUSH_VERBOSE') || drush_get_context('DRUSH_SIMULATE')) {
     drush_print("Calling $function(". implode(", ", $args) .')');
  }

  if (drush_get_context('DRUSH_SIMULATE')) {
    return true;
  }

  return call_user_func_array($function, $args);
}

/**
 * Rudimentary replacement for Drupal API t() function.
 *
 * @param string
 *   String to process, possibly with replacement item.
 * @param array
 *  An associative array of replacement items.
 *
 * @return
 *   The processed string.
 *
 * @see t()
 */
function dt($string, $args = array()) {
  if (function_exists('t')) {
    return t($string, $args);
  }
  else {
    if (!empty($args)) {
      return strtr($string, $args);
    }
    else {
      return $string;
    }
  }
}

/**
 * Get the available options for Drush for use by help page.
 *
 * @return
 *   An associative array containing the option definition as the key, and the description as the value,
 *   for each of the available options.
 */
function drush_get_option_help() {
  // TODO: Add a hook for this, to allow other modules to add their options
  $options['-r <path>, --root=<path>'] = dt("Drupal root directory to use (default: current directory)");
  $options['-l <uri>, --uri=http://example.com']   = dt('URI of the drupal site to use (only needed in multisite environments)');
  $options['-v, --verbose']            = dt('Display extra information about the command.');
  $options['-d, --debug']              = dt('Display even more information, including internal messages.');
  $options['-q, --quiet']              = dt('Hide all output');
  $options['-y, --yes']                = dt("Assume 'yes' as answer to all prompts");
  $options['-s, --simulate']           = dt("Simulate all relevant actions (don't actually change the system)");
  $options['-i, --include']            = dt("A list of paths to search for drush commands");
  $options['-c, --config']             = dt("Specify a config file to use. See example.drushrc.php");
  $options['-u, --user']               = dt("Specify a user to login with. May be a name or a number.");
  $options['-b, --backend']            = dt("Hide all output and return structured data (internal use only).");
  $options['-p, --pipe']               = dt("Emit a compact representation of the command for scripting.");
  $options['-n, --nocolor']            = dt("Suppress color highlighting on log messages.");
  $options['-h, --help']               = dt("The absolute path to your PHP intepreter.");
  return $options;
}

/**
 * Prints out help for a given command.
 */
function drush_show_help($commands) {
  $phases = _drush_bootstrap_phases();

  $commandstring = implode(" ", $commands);

  foreach ($phases as $phase_index) {
    if ($phase_index > drush_get_context('DRUSH_BOOTSTRAP_PHASE')) {
      drush_bootstrap($phase_index);
    }
    if (!drush_get_error()) {
      $commands = drush_get_commands();
      if (array_key_exists($commandstring, $commands)) {
        $command = $commands[$commandstring];
        
        // Merge in engine specific help.
        foreach ($command['engines'] as $type => $description) {
          $all_engines = drush_get_engines($type);
          foreach ($all_engines as $name => $engine) {
            $command = array_merge_recursive($command, $engine);
          }
        }
        
        $help = drush_command_invoke_all('drush_help', 'drush:'. $command['command']);
        if (!empty($help)) {
          drush_print(wordwrap(implode("\n", $help), drush_get_context('DRUSH_COLUMNS', 80)));
          drush_print();
          
          // TODO: Let commands define additional sections.
          $sections = array(
            'examples' => 'Examples',
            'arguments' => 'Arguments',
            'options' => 'Options',
          );
          
          foreach ($sections as $key => $value) {
            if (!empty($command[$key])) {
              drush_print(dt($value) . ':');
              foreach ($command[$key] as $name => $description) {
                // '[command] is a token representing the current command. @see pm_drush_engine_version_control().
                $rows[] = array(str_replace('[command]', $commandstring, $name), dt($description));
              }
              drush_print_table($rows, false, array(40));
              unset($rows);
              drush_print();
            }
          }

          // Append aliases if any.
          if ($command['aliases']) {
            drush_print(dt("Aliases: ") . implode(', ', $command['aliases']));
          }
          
          return TRUE;
        }
        else {
          drush_print("No help available for command '$commandstring'.");
          return TRUE;
        }
      }
    }
    else {
      break;
    }
  }
  return drush_set_error('DRUSH_COMMAND_NOT_FOUND', dt('Invalid command !command.', array('!command' => $commandstring)));
}

/**
 * Executes a shell command.
 * Output is only printed if in verbose mode.
 * Output is stored and can be retrieved using drush_shell_exec_output().
 * If in simulation mode, no action is taken.
 *
 * @param $cmd
 *   The command to execute.
 * @param $indent
 *   Indentation for the output (only for verbose mode).
 * @return
 *   0 if success.
 */
function drush_shell_exec($cmd, $indent = 0) {
  if (drush_get_context('DRUSH_VERBOSE') || drush_get_context('DRUSH_SIMULATE')) {
    drush_print('Executing: ' . $cmd, $indent);
  }

  if (drush_get_context('DRUSH_SIMULATE')) {
    return true;
  }

  exec($cmd . ' 2>&1', $output, $result);
  
  _drush_shell_exec_output_set($output);

  if (drush_get_context('DRUSH_VERBOSE')) {
    foreach ($output as $line) {
      drush_print($line, $indent + 2);
    }
  }

  // Exit code 0 means success.
  return ($result == 0);
}

/**
 * Stores output for the most recent shell command.
 * This should only be run from drush_shell_exec().
 *
 * @param $output
 *   The output of the most recent shell command.
 *   If this is not set the stored value will be returned.
 */
function _drush_shell_exec_output_set($output = FALSE) {
  static $stored_output;
  if (!$output) return $stored_output;
  $stored_output = $output;
}

/**
 * Returns the output of the most recent shell command as an array of lines.
 */
function drush_shell_exec_output() {
  return _drush_shell_exec_output_set();
}

/**
 * Exits with a message. In general, you should use drush_set_error() instead of 
 * this function. That lets drush proceed with other tasks.
 * TODO: Exit with a correct status code.
 */
function drush_die($msg = NULL, $status = NULL) {
  die($msg ? "drush: $msg\n" : '');
}

/**
 * Prints a message with optional indentation. In general, 
 * drush_log($message, 'ok') is often a better choice than this function. 
 * That gets your confirmation message (for example) into the logs for this 
 * drush request. Consider that drush requests may be executed remotely and 
 * non interactively.
 * 
 * @param $message
 *   The message to print.
 * @param $indent
 *    The indentation (space chars)
 */
function drush_print($message = '', $indent = 0) {
  print str_repeat(' ', $indent) . (string)$message . "\n";
}

/**
 * Stores a message which is printed during drush_shutdown() if in compact mode.
 * @param $message
 *   The message to print.
 */
function drush_print_pipe($message = '') {
  $buffer = &drush_get_context('DRUSH_PIPE_BUFFER' , '');
  $buffer .= $message;
}

/**
 * Prints an array or string.
 * @param $array
 *   The array to print.
 */
function drush_print_r($array) {
  print_r($array);
}

/**
 * Ask the user a basic yes/no question.
 *
 * @param $msg The question to ask
 * @return TRUE if the user entered 'y', FALSE if he entered 'n'
 */
function drush_confirm($msg, $indent = 0) {
  print str_repeat(' ', $indent) . (string)$msg . " (y/n): ";

  if (drush_get_context('DRUSH_AFFIRMATIVE')) {
    print "y\n";
    return TRUE;
  }
  // See http://drupal.org/node/499758 before changing this.
  $stdin = fopen("php://stdin","r");
  
  while ($line = fgets($stdin)) {
  $line = trim($line);
    if ($line == 'y') {
      return TRUE;
    }
    if ($line == 'n') {
      return FALSE;
    }
    print str_repeat(' ', $indent) . (string)$msg . " (y/n): ";
  }
}

/**
 * Print a formatted table.
 * 
 * @param $rows
 *   The rows to print.
 * @param $header
 *   If TRUE, the first line will be treated as table header and therefore be
 *   underlined.
 * @param $widths
 *   The widths of each column (in characters) to use - if not specified this
 *   will be determined automatically, based on a "best fit" algorithm.
 */
function drush_print_table($rows, $header = FALSE, $widths = array()) {
  $tbl = new Console_Table(CONSOLE_TABLE_ALIGN_LEFT , '');

  $auto_widths = drush_table_column_autowidth($rows, $widths);

  // Do wordwrap on all cells.
  $newrows = array();
  foreach ($rows as $rowkey => $row) {
    foreach ($row as $col_num => $cell) {
      $newrows[$rowkey][$col_num] = wordwrap($cell, $auto_widths[$col_num], "\n", TRUE);
      if (isset($widths[$col_num])) {
        $newrows[$rowkey][$col_num] = str_pad($newrows[$rowkey][$col_num], $widths[$col_num]);
      }
    }
  }
  if ($header) {
    $headers = array_shift($newrows);
    $tbl->setHeaders($headers);
  }

  $tbl->addData($newrows);
  print $tbl->getTable();
}

/**
 * Determine the best fit for column widths.
 * 
 * @param $rows
 *   The rows to use for calculations.
 * @param $widths
 *   Manually specified widths of each column (in characters) - these will be
 *   left as is.
 */
function drush_table_column_autowidth($rows, $widths) {
  $auto_widths = $widths;
  
  // First we determine the distribution of row lengths in each column.
  // This is an array of descending character length keys (i.e. starting at
  // the rightmost character column), with the value indicating the number
  // of rows where that character column is present.
  $col_dist = array();
  foreach ($rows as $rowkey => $row) {
    foreach ($row as $col_num => $cell) {
      if (empty($widths[$col_num])) {
        $length = strlen($cell);
        while ($length > 0) {
          if (!isset($col_dist[$col_num][$length])) {
            $col_dist[$col_num][$length] = 0;
          }
          $col_dist[$col_num][$length]++;
          $length--;
        }
      }
    }
  }
  foreach ($col_dist as $col_num => $count) {
    // Sort the distribution in decending key order.
    krsort($col_dist[$col_num]);
    // Initially we set all columns to their "ideal" longest width
    // - i.e. the width of their longest column.
    $auto_widths[$col_num] = max(array_keys($col_dist[$col_num]));
  }

  // We determine what width we have available to use, and what width the
  // above "ideal" columns take up.
  $available_width = drush_get_context('DRUSH_COLUMNS', 80) - (count($auto_widths) * 2);
  $auto_width_current = array_sum($auto_widths);
  
  // If we need to reduce a column so that we can fit the space we use this
  // loop to figure out which column will cause the "least wrapping",
  // (relative to the other columns) and reduce the width of that column.
  while ($auto_width_current > $available_width) {
    $count = 0;
    $width = 0;
    foreach ($col_dist as $col_num => $counts) {
      // If we are just starting out, select the first column.
      if ($count == 0 ||
         // OR: if this column would cause less wrapping than the currently
         // selected column, then select it.
         (current($counts) < $count) ||
         // OR: if this column would cause the same amount of wrapping, but is
         // longer, then we choose to wrap the longer column (proportionally
         // less wrapping, and helps avoid triple line wraps).
         (current($counts) == $count && key($counts) > $width)) {
        // Select the column number, and record the count and current width
        // for later comparisons.
        $column = $col_num;
        $count = current($counts);
        $width = key($counts);
      }
    }
    if ($width <= 1) {
      // If we have reached a width of 1 then give up, so wordwrap can still progress.
      break;
    }
    // Reduce the width of the selected column.
    $auto_widths[$column]--;
    // Reduce our overall table width counter.
    $auto_width_current--;
    // Remove the corresponding data from the disctribution, so next time
    // around we use the data for the row to the left.
    unset($col_dist[$column][$width]);
  }
  return $auto_widths;
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
 * The default callback is the _drush_print_log() function with prints the messages
 * to the shell.
 *
 * @param message
 *   String containing the message to be logged.
 * @param type
 *   The type of message to be logged. Common types are 'warning', 'error', 'success' and 'notice'.
 *   A type of 'failed' can also be supplied to flag as an 'error'.
 *   A type of 'ok' or 'completed' can also be supplied to flag as a 'success'
 *   All other types of messages will be assumed to be notices.
 */
function drush_log($message, $type = 'notice', $error = null) {
  $log =& drush_get_context('DRUSH_LOG', array());
  $callback = drush_get_context('DRUSH_LOG_CALLBACK', '_drush_print_log');
  $entry = array(
     'type' => $type, 
     'message' => $message, 
     'timestamp' => microtime(TRUE),
   );
  $entry['error'] = $error;
  $log[] = $entry;
  return $callback($entry);
}

/**
 * Retrieve the log messages from the log history
 *
 * @return
 *   Entire log history
 */
function drush_get_log() {
  return drush_get_context('DRUSH_LOG', array());
}

/**
 * Run print_r on a variable and log the output.
 */
function dlm($object) {
  ob_start();
  print_r($object);
  $contents = ob_get_contents();
  ob_end_clean();

  drush_log($contents);
}

/*
 * Display the pipe output for the current request.
 */ 
function drush_pipe_output() {
  $pipe = drush_get_context('DRUSH_PIPE_BUFFER');
  drush_print_r($pipe);
}

/**
 * Display the log message
 *
 * By default, only warnings and errors will be displayed, if 'verbose' is specified, it will also display notices.
 *
 * @param
 *   The associative array for the entry.
 *
 * @return
 *   False in case of an error or failed type, True in all other cases.
 */
function _drush_print_log($entry) {
  if (drush_get_context('DRUSH_NOCOLOR')) {
    $red = "[%s]";
    $yellow = "[%s]";
    $green = "[%s]";
  }
  else {
    $red = "\033[31;40m\033[1m[%s]\033[0m";
    $yellow = "\033[1;33;40m\033[1m[%s]\033[0m";
    $green = "\033[0;33;40m\033[1m[%s]\033[0m";
  }

  $verbose = drush_get_context('DRUSH_VERBOSE');
  $debug = drush_get_context('DRUSH_DEBUG');

  $return = TRUE;
  switch ($entry['type']) {
    case 'warning' : 
      $type_msg = sprintf($yellow, $entry['type']);
      break;
    case 'failed' : 
    case 'error' : 
      $type_msg = sprintf($red, $entry['type']);
      $return = FALSE;
      break;
    case 'ok' :
    case 'completed' :
    case 'success' :
      $type_msg = sprintf($green, $entry['type']);
      break;
    case 'notice' : 
    case 'message' : 
      if (!$verbose) {
        // print nothing. exit cleanly.
        return TRUE;
      }
      $type_msg = sprintf("[%s]", $entry['type']);
      break;
    default : 
      if (!$debug) {
        // print nothing. exit cleanly.
        return TRUE;
      }
      $type_msg = sprintf("[%s]", $entry['type']);
      break;
  }

  // When running in backend mode, log messages are not displayed, as they will
  // be returned in the JSON encoded associative array.
  if (drush_get_context('DRUSH_BACKEND')) {
    return $return;
  }

  $columns = drush_get_context('DRUSH_COLUMNS', 80);

  $width[1] = 11;
  // Append timer value.
  if ($debug) {
    $timer = sprintf('[%s sec]', round($entry['timestamp']-DRUSH_REQUEST_TIME, 3));
    $entry['message'] = $entry['message'] . ' ' . $timer;
  }

  $width[0] = ($columns - 11);

  $format = sprintf("%%-%ds%%%ds", $width[0], $width[1]); 

  // Place the status message right aligned with the top line of the error message.
  $message = wordwrap($entry['message'], $width[0]);
  $lines = explode("\n", $message);
  $lines[0] = sprintf($format, $lines[0], $type_msg);
  $message = implode("\n", $lines);
  drush_print($message);
  return $return;
}

// Log all timers for the request except standard page timer.
// Useful for migrate commands. Lets see if others find it useful.
// Called at end of request. @see drush.php
function drush_log_timers() {
  global $timers;
  foreach ((array)$timers as $name => $timerec) {
    drush_log("Timer '$name' is  "  . sprintf('%s sec.', round(timer_read($name)/1000, 3)), 'timer');
  }
}
/**
* Turn drupal_set_message errors into drush_log errors
*/
function _drush_log_drupal_messages() {
  if (function_exists('drupal_get_messages')) {

    $messages = drupal_get_messages();
    
    if (array_key_exists('error', $messages)) {
      //Drupal message errors.
      foreach ((array) $messages['error'] as $error) {
        $error = strip_tags($error);
        if (preg_match('/^warning: Cannot modify header information - headers already sent by /i', $error)) {
          //This is a special case for an unavoidable warning
          //that is generated by generating output before Drupal is bootstrapped.
          //Simply ignore it.
          continue;
        }
        elseif (preg_match('/^warning:/i', $error)) {
          drush_log(ereg_replace('/^warning: /i', '', $error), 'warning');
        }
        elseif (preg_match('/^notice:/i', $error)) {
          drush_log(ereg_replace('/^notice: /i', '', $error), 'notice');
        }
        elseif (preg_match('/^user warning:/i', $error)) {
          // This is a special case. PHP logs sql errors as 'User Warnings', not errors.
          drush_set_error('DRUSH_DRUPAL_ERROR_MESSAGE', ereg_replace('/^user warning: /i', '', $error));
        }
        else {
          drush_set_error('DRUSH_DRUPAL_ERROR_MESSAGE', $error);
        }
      }
    }
  }
}

/**
 * Log the return value of Drupal hook_update_n functions.
 *
 * This is used during install and update to log the output
 * of the update process to the logging system.
 */
function _drush_log_update_sql($ret) {
  if (sizeof($ret)) {
    foreach ($ret as $info) {
      if (is_array($info)) {
        if (!$info['success']) {
          drush_set_error('DRUPAL_UPDATE_FAILED', $info['query']);
        }
        else {
          drush_log($info['query'], ($info['success']) ? 'success' : 'error');
        }
      }
    }
  }
}

/**
 * @} End of "defgroup logging".
 */

/**
* @name Error status definitions
* @{
* Error code definitions for interpreting the current error status.
* @see drush_set_error(), drush_get_error(), drush_get_error_log(), drush_cmp_error()
*/

/** The command completed succesfully. */
define('DRUSH_SUCCESS', 0);
/** The command could not be completed because the framework has specified errors that have occured. */
define('DRUSH_FRAMEWORK_ERROR', 1);
/** The command that was executed resulted in an application error,
  The most commom causes for this is invalid PHP or a broken SSH
  pipe when using drush_backend_invoke in a distributed manner. */
define('DRUSH_APPLICATION_ERROR', 255);

/**
 * @} End of "name Error status defintions".
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
 * @param error
 *   A text string identifying the type of error.
 *
 * @param message
 *   Optional. Error message to be logged. If no message is specified, hook_drush_help will be consulted, 
 *   using a key of 'error:MY_ERROR_STRING'.
 *
 * @return
 *   Always returns FALSE, to allow you to return with false in the calling functions,
 *   such as <code>return drush_set_error('DRUSH_FRAMEWORK_ERROR')</code>
 */
function drush_set_error($error, $message = null) {
  $error_code =& drush_get_context('DRUSH_ERROR_CODE', DRUSH_SUCCESS);
  $error_code = DRUSH_FRAMEWORK_ERROR;

  $error_log =& drush_get_context('DRUSH_ERROR_LOG', array());

  if (is_numeric($error)) {
    $error = 'DRUSH_FRAMEWORK_ERROR';
  }

  $message = ($message) ? $message : drush_command_invoke_all('drush_help', 'error:' . $error);

  if (is_array($message)) {
    $message = implode("\n", $message);
  }

  $error_log[$error][] = $message;
  drush_log(($message) ? $message : $error, 'error', $error);

  return FALSE;
}

/**
 * Return the current error handling status
 *
 * @return
 *   The current aggregate error status
 */
function drush_get_error() {
  return drush_get_context('DRUSH_ERROR_CODE', DRUSH_SUCCESS);
}

/**
 * Return the current list of errors that have occurred.
 *
 * @return
 *   An associative array of error messages indexed by the type of message.
 */
function drush_get_error_log() {
  return drush_get_context('DRUSH_ERROR_LOG', array());
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
 * Turn PHP error handling off.
 *
 * This is commonly used while bootstrapping Drupal for install
 * or updates.
 */
function drush_errors_off() {
  $errors =& drush_get_context('DRUSH_ERROR_REPORTING', 0);
  $errors = error_reporting(0);
  ini_set('display_errors', FALSE);
}

/**
 * Turn PHP error handling on.
 */
function drush_errors_on() {
  $errors =& drush_get_context('DRUSH_ERROR_REPORTING', E_ALL ^ E_NOTICE);
  $errors = error_reporting($errors);
  ini_set('display_errors', TRUE);
}

/**
 * @} End of "defgroup errorhandling".
 */

