<?php
// $Id$

/**
 * @file
 * The drush API implementation and helpers.
 */

/**
 * Dispatch a given set of commands.
 * Modules can add commands by implementing hook_drush_command().
 */
function drush_dispatch($commands) {
  // Get a list of all implemented commands.
  $implemented = drush_get_commands();

  $go = TRUE;
  $result = FALSE;
  $funcargs = array();
  // Try to determine the handler for the current command.
  while (!$result && count($commands)) {
    $command = implode(" ", $commands);
    if (isset($implemented[$command])) {
      $result = $implemented[$command];
    }
    else {
      $funcargs[] = array_pop($commands);
    }
  }
  if ($result) {
    // Call the callback function of the active command.
    return call_user_func_array($result['callback'], array_reverse($funcargs));
  }
  else {
    // Special case: If no command is found, display usage info.
    return call_user_func_array('drush_usage', array_reverse($funcargs));
  }

  return FALSE;
}

/**
 * Get a list of all implemented commands.
 * This invokes hook_drush_command().
 */
function drush_get_commands($refresh = FALSE) {
  static $commands;

  if (!$refresh && !empty($commands)) {
    return $commands;
  }

  $commands = module_invoke_all('drush_command', TRUE);

  return $commands;
}

function drush_is_command($command) {
  $commands = drush_get_commands();
  return (isset($commands[$command])) ? TRUE : FALSE;
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
