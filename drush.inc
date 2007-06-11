<?php
// $Id$

/**
 * @file
 * The drush API implementation and helpers.
 */

// Basic constants.
define('DRUSH_PATH', dirname(__FILE__));

/**
 * Returns the drush version (parsed from drush.ini).
 */
function drush_version() {
  static $version;
  if (isset($version) && $version)
    return $ini;
    
  $ini = parse_ini_file(drupal_get_path('module','drush') . '/drush.info', FALSE);
  $version = $ini['version'];
  return $version;
}

/**
 * Dispatch a given set of commands.
 * Modules can add commands by implementing hook_drush_commands().
 */
function drush_dispatch($commands) {
  
  // Get a list of all implemented commands.
  $implemented = drush_get_commands();
  
  $go = TRUE;
  $result = FALSE;
  // try to determine the handler for the current command
  while(!$result && count($commands)) {
    $command = implode(" ", $commands);
    if(isset($implemented[$command])) {
      $result = $implemented[$command];
    }
    else {
      $funcargs[] = array_pop($commands);
    }
  }
  if($result) {
    // call the callback function of the active command
    return call_user_func_array($result['callback'], array_reverse($funcargs));
  }
  else {
    // Special case: If no command is found, display usage info
    return call_user_func_array('drush_usage', array_reverse($funcargs));
  }
  
  return FALSE;
}

/**
 * Get a list of all implemented commands.
 * This invokes hook_drush_command()
 */
function drush_get_commands($refresh = FALSE) {
  static $commands;
  
  if (!$refresh && !empty($commands))
    return $commands;
    
  $commands = module_invoke_all('drush_command', TRUE);
  
  return $commands; 
}

function drush_is_command($command) {
  $commands = drush_get_commands();
  if (isset($commands[$command]))
    return true;
  else
    return false;
}

/**
 * Calls a given function, passing through all arguments unchanged.
 *
 * TODO: Allow operations that have mutative or destructive side effects to be
 * systematically suppressed or recorded.
 */
function drush_op($function) {
  $args = func_get_args();
  array_shift($args); // skip function name

  // TODO: support for recording operations.
  // TODO: maybe re-add the simulation mode

  if (DRUSH_VERBOSE || DRUSH_SIMULATE)
    drush_print("Calling $function(" . implode(", ", $args) . ')');
  
  if (DRUSH_SIMULATE)
    return true;

  return call_user_func_array($function, $args);
}

/**
 * Executes a shell command.
 * Output is only printed if in verbose mode.
 * If in simulation mode, no action is taken.
 */
function drush_shell_exec($cmd, $indent = 0) {
  if (DRUSH_VERBOSE || DRUSH_SIMULATE) 
    drush_print('Executing: ' . $cmd, $indent);
  
  if (DRUSH_SIMULATE)
    return true;

  exec($cmd . ' 2>&1', $output, $result);

  if (DRUSH_VERBOSE) {
    foreach ($output as $line) {
      drush_print($line, $indent + 2);
    }
  }

  return ($result == 0); // exit code zero means success
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
 * if ($error) return drush_error('A error occured);
 */
function drush_error($msg = '') {
  // TODO: print to stderr if running in CLI mode.
  drush_print("E: " . $msg);
  return FALSE;
}

/**
 * Prints a message.
 * @param $msg The message to print.
 * @param $indent The indentation (space chars)
 */
function drush_print($msg = '', $indent = 0) {
  if ($indent > 0) 
    print str_repeat(' ', $indent);
  print (string)$msg . "\n";
}

/**
 * Prints a message, but only if verbose mode is activated.
 * Returns TRUE if in verbose mode, otherwise FALSE.
 */
function drush_verbose($msg = FALSE, $indent = 0) {
  if (!DRUSH_VERBOSE)
    return FALSE;
  if (DRUSH_VERBOSE && $msg === FALSE)
    return TRUE;
  
  if ($indent > 0) 
    print str_repeat(' ', $indent);
  
  print (string)$msg . "\n";
  
  return TRUE;
}

/**
 * Ask the user a basic yes/no question.
 * 
 * @param $msg The question to ask
 * @return TRUE if the user entered 'y', FALSE if he entered 'n'
 */
function drush_confirm($msg, $indent = 0) {
  print str_repeat(' ', $indent) . $msg . " (y/n): ";
  
  if (DRUSH_AFFIRMATIVE) {
    print "y\n";
    return TRUE;
  }
  while ($line = trim(fgets(STDIN))) {
    if ($line == 'y')
      return TRUE;
    if ($line == 'n')
      return FALSE;
    print $msg . " (y/n): ";
  }

}

/**
 * Print a formatted table.
 * @param $rows The rows to print
 * @param $indent Indent for the whole table
 * @param $header If TRUE, the first line will be treated as table
 *        header and be underlined.
 */
function drush_print_table($rows, $indent = 0, $header = FALSE) {
  if (count($rows) == 0)
    return; // nothing to output

  $indent = str_repeat(' ', $indent);
  $format = _drush_get_table_row_format($rows);

  $counter = 0;
  foreach ($rows as $cols) {
    print $indent . vsprintf($format, $cols) . "\n";
    if ($counter == 0 && $header) {
      $headers = array();
      foreach ($cols as $col)
        $headers[] = str_repeat('-', strlen($col));
      print $indent . trim(vsprintf($format, $headers)) . "\n";
    }
    $counter++;
  }
}

/**
 * Format a table
 */
function _drush_get_table_row_format($table) {
  $widths = _drush_get_table_column_widths($table);
  $format = implode("\t", array_map(create_function('$width', 'return "%-{$width}s";'), $widths));
  return $format;
}

function _drush_get_table_column_widths($table) {
  $widths = array();
  foreach ($table as $row => $cols) {
    foreach ($cols as $col => $value) {
      $widths[$col] = max($widths[$col], strlen((string)$value));
    }
  }
  return $widths;
}
