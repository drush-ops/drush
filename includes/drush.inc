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
 *
 */
function drush_dispatch($commands) {

  // Special case for help command
  $show_help = FALSE;
  if (isset($commands[0]) && $commands[0] == 'help') {
    $show_help = TRUE;
    array_shift($commands);
    (empty($commands)) ? drush_usage() : drush_show_help($commands);
    return TRUE;
  }

  list($command, $arguments) = drush_parse_command($commands);

  if ($command) {
    // Call the callback function of the active command.
    return call_user_func_array($command['callback'], $arguments);
  }
  else {
    // Special case: If no command is found, display usage info.
    return call_user_func_array('drush_usage', $arguments);
  }

  return FALSE;
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
    if (defined('DRUSH_DRUPAL_ROOT')) {
      if (file_exists(DRUSH_DRUPAL_ROOT . '/modules/system/system.module')) {
        // We can safely include system.module as it contains defines and functions only.
        require_once(DRUSH_DRUPAL_ROOT . '/modules/system/system.module');
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

  if (DRUSH_VERBOSE || DRUSH_SIMULATE) {
     drush_print("Calling $function(". implode(", ", $args) .')');
  }

  if (DRUSH_SIMULATE) {
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
function dt($string, $args = NULL) {
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
 * This is called if no command or an unknown command is entered.
 */
function drush_usage() {
  $commands = func_get_args();

  if (drush_get_option('help') || empty($commands)) {
    drush_print(dt('Usage: drush.php [options] <command> <command> ...'));
    drush_print();
    drush_print('Run "drush help [command]" to view command-specific help.');
    drush_print(dt('Options: '));
    foreach (drush_get_options() as $option => $description) {
      $rows[] = array($option, $description);
    }

    drush_print_table($rows, 2);
    drush_print();
    drush_print('Commands: ');

    $commands = drush_get_commands();
    $rows = array();
    foreach($commands as $key => $command) {
      $rows[] = array($key, $commands[$key]['description']);
    }
    drush_print_table($rows, 2);
    return;
  }

  drush_log(dt('Invalid command !command.', array('!command' => implode(" ", $commands))), 'error');
}

/**
 * Get the available options for Drush for use by help page.
 *
 * @return
 *   An associative array containing the option definition as the key, and the description as the value,
 *   for each of the available options.
 */
function drush_get_options() {
  // TODO: Add a hook for this, to allow other modules to add their options
  $options['-r <path>, --root=<path>'] = dt("Drupal root directory to use (default: current directory)");
  $options['-l <uri> , --uri=<uri>']   = dt('URI of the drupal site to use (only needed in multisite environments)');
  $options['-v, --verbose']            = dt('Display all available output');
  $options['-y, --yes']                = dt("Assume 'yes' as answer to all prompts");
  $options['-s, --simulate']           = dt("Simulate all relevant actions (don't actually change the system)");
  $options['-i, --include']            = dt("A list of paths to search for drush commands");
  $options['-c, --config']             = dt("Specify a config file to use. See example.drushrc.php");
  $options['-u, --user']               = dt("Specify a user to login with. May be a name or a number.");
  return $options;
}

/**
 * Prints out the default drush help page.
 */
function drush_show_help($commands) {
  $commandstring = implode(" ", $commands);

  if (!drush_is_command($commandstring)) {
    return drush_log(dt('Invalid command !command.', array('!command' => $commandstring)), 'error');
  }
  else {
    // Get the full command array
    $all = drush_get_commands();
    $command = $all[$commandstring];
  }

  $help = drush_command_invoke_all('drush_help', 'drush:'. $commandstring);
  if (!empty($help)) {
    drush_print(wordwrap(implode("\n", $help), 80));
    drush_print();
    
// TODO: Just let commands define their own sections? Standardization need not be mandated?
    $sections = array(
      'examples' => 'Examples',
      'arguments' => 'Arguments',
      'options' => 'Options',
    );
    
    foreach ($sections as $key => $value) {
      if ($command[$key]) {
        drush_print(dt($value) . ':');
        foreach ($command[$key] as $name => $description) {
          $rows[] = array($name, dt($description));
        }
        drush_print_table($rows, 2);
        unset($rows);
        drush_print();
      }
    }

  }
  else {
    drush_print("No help available for command 'drush $commandstring'.");
  }
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
 */
function drush_shell_exec($cmd, $indent = 0) {
  if (DRUSH_VERBOSE || DRUSH_SIMULATE) {
    drush_print('Executing: ' . $cmd, $indent);
  }

  if (DRUSH_SIMULATE) {
    return true;
  }

  exec($cmd . ' 2>&1', $output, $result);
  
  _drush_shell_exec_output_set($output);

  if (DRUSH_VERBOSE) {
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
 * Exits with a message.
 * TODO: Exit with a correct status code.
 */
function drush_die($msg = NULL, $status = NULL) {
  die($msg ? "drush: $msg\n" : '');
}

/**
 * Prints an error message.
 * Always returns FALSE. This allows to do e.g.
 * if ($error) { return drush_error('A error occured); }
 * to make your function return FALSE in case of an error.
 */
function drush_error($msg = '') {
  // TODO: print to stderr if running in CLI mode.
  drush_print("E: " . $msg);
  return FALSE;
}

/**
 * Prints a message.
 * @param $message
 *   The message to print.
 * @param $indent
 *    The indentation (space chars)
 */
function drush_print($message = '', $indent = 0) {
  print str_repeat(' ', $indent) . (string)$message . "\n";
}

/**
 * Prints a message, but only if verbose mode is activated.
 * Returns TRUE if in verbose mode, otherwise FALSE.
 */
function drush_verbose($msg = FALSE, $indent = 0) {
  if (!DRUSH_VERBOSE) {
    return FALSE;
  }
  if (DRUSH_VERBOSE && $msg === FALSE) {
    return TRUE;
  }

  print str_repeat(' ', $indent) . (string)$msg . "\n";
  return TRUE;
}

/**
 * Ask the user a basic yes/no question.
 *
 * @param $msg The question to ask
 * @return TRUE if the user entered 'y', FALSE if he entered 'n'
 */
function drush_confirm($msg, $indent = 0) {
  print str_repeat(' ', $indent) . (string)$msg . " (y/n): ";

  if (DRUSH_AFFIRMATIVE) {
    print "y\n";
    return TRUE;
  }
  while ($line = trim(fgets(STDIN))) {
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
 * @param $rows
 *   The rows to print
 * @param $indent
 *   Indentation for the whole table
 * @param $header
 *   If TRUE, the first line will be treated as table
 *   header and therefore be underlined.
 */
function drush_print_table($rows, $indent = 0, $header = FALSE) {
  if (count($rows) == 0) {
    // Nothing to output.
    return;
  }

  $indent = str_repeat(' ', $indent);
  $format = _drush_get_table_row_format($rows);

  $header_printed = FALSE;
  foreach ($rows as $cols) {
    // Print the current line.
    print $indent . vsprintf($format, $cols) . "\n";
    // Underline the first row if $header is set to true.
    if (!$header_printed && $header) {
      $headers = array();
      foreach ($cols as $col) {
        $headers[] = str_repeat('-', strlen($col));
      }
      print $indent . trim(vsprintf($format, $headers)) . "\n";
      $header_printed = TRUE;
    }
  }
}

/**
 * Create the table row format string to be used in vsprintf().
 */
function _drush_get_table_row_format($table) {
  $widths = _drush_get_table_column_widths($table);
  foreach ($widths as $col_width) {
    $col_formats[] = "%-{$col_width}s";
  }
  $format = implode("\t", $col_formats);
  return $format;
}

/**
 * Calculate table column widths.
 */
function _drush_get_table_column_widths($table) {
  $widths = array();
  foreach ($table as $row => $cols) {
    foreach ($cols as $col => $value) {
      $old_width = isset($widths[$col]) ? $widths[$col] : 0;
      $widths[$col] = max($old_width, strlen((string)$value));
    }
  }
  return $widths;
}


/**
 * @defgroup logging Logging information to be provided as output.
 * @{
 *
 * These functions are primarily for diagnostic purposes, but also provide an overview of tasks that were taken
 * by drush.
 */

/**
 * Maintain a static array containing all the log messages
 *
 * @param entry
 *   Associative array containing the log message.
 * @return
 *   Entire log history, only if $entry is NULL. If entry is not null, it will return the return of _drush_print_log.
 */
function _drush_set_log($entry = NULL) {
  static $log = array();
  if ($entry == NULL) {
    return $log;
  }
  else {
    $log[] = $entry;
    return _drush_print_log($entry);
  }
}

/**
 * Add a log message to the log history.
 *
 * @param message
 *   String containing the message to be logged.
 * @param type
 *   The type of message to be logged. Common types are 'warning', 'error', 'success' and 'notice'.
 *   A type of 'failed' can also be supplied to flag as an 'error'.
 *   A type of 'ok' or 'completed' can also be supplied to flag as a 'success'
 *   All other types of messages will be assumed to be notices.
 */
function drush_log($message, $type = 'notice' ) {
  return _drush_set_log(array(
     'type' => $type, 
     'message' => $message, 
     'timestamp' => time()
    ));
}

/**
 * Retrieve the log messages from the log history
 *
 * @return
 *   Entire log history
 */
function drush_get_log() {
  return _drush_set_log();
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
  $red = "[\033[31m%s\033[0m]";
  $yellow = "[\033[1;33m%s\033[0m]";
  $green = "[\033[0;33m%s\033[0m]";

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
    default : 
      if (!DRUSH_VERBOSE) {
        // print nothing. exit cleanly.
        return TRUE;
      }
      $type_msg = sprintf("[%s]", $entry['type']);
      break;
 }
 $message = $type_msg . ' ' . $entry['message'];

 drush_print($message);
 return $return;
}

/**
 * @} End of "defgroup logging".
 */

