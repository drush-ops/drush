<?php

/**
 * @defgroup outputfunctions Process output text.
 * @{

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
 * @param $handle
 *    File handle to write to.  NULL will write
 *    to standard output, STDERR will write to the standard
 *    error.  See http://php.net/manual/en/features.commandline.io-streams.php
 * @param $newline
 *    Add a "\n" to the end of the output.  Defaults to TRUE.
 */
function drush_print($message = '', $indent = 0, $handle = NULL, $newline = TRUE) {
  $msg = str_repeat(' ', $indent) . (string)$message;
  if ($newline) {
    $msg .= "\n";
  }
  if (($charset = drush_get_option('output_charset')) && function_exists('iconv')) {
    $msg = iconv('UTF-8', $charset, $msg);
  }
  if (isset($handle)) {
    fwrite($handle, $msg);
  }
  else {
    print $msg;
  }
}

/**
 * Print a prompt -- that is, a message with no trailing newline.
 */
function drush_print_prompt($message, $indent = 0, $handle = NULL) {
  drush_print($message, $indent, $handle, FALSE);
}

/**
 * Stores a message which is printed during drush_shutdown() if in compact mode.
 * @param $message
 *   The message to print.  If $message is an array,
 *   then each element of the array is printed on a
 *   separate line.
 */
function drush_print_pipe($message = '') {
  $buffer = &drush_get_context('DRUSH_PIPE_BUFFER' , '');
  if (is_array($message)) {
    $message = implode("\n", $message) . "\n";
  }
  $buffer .= $message;
}

/**
 * Prints an array or string.
 * @param $array
 *   The array to print.
 * @param $newline
 *    Add a "\n" to the end of the output.  Defaults to TRUE.
 */
function drush_print_r($array, $handle = NULL, $newline = TRUE) {
  drush_print(print_r($array, TRUE), 0, $handle, $newline);
}

/**
 * Prepares a variable for printing.
 * @param mixed $input
 *   A variable.
 * @param string $label
 *   Optional prefix. Not used by JSON format.
 * @return string
 *   The variable formatted according to specified format. Ready for drush_print().
 */
function drush_format($input, $label = NULL, $format = NULL) {
  if (is_null(($format))) {
    $format = drush_get_option('format', 'print_r');
  }
  if ($format != 'export' && $format != 'config') {
    if ($input === TRUE) {
      $input = 'TRUE';
    }
    elseif ($input === FALSE) {
      $input = 'FALSE';
    }
  }

  switch ($format) {
    case 'config':
      if ($label) {
        $output = "\$config['$label'] = " . var_export($input, TRUE) . ';';
      }
      else {
        $output = var_export($input, TRUE);
      }
      break;
    case 'export':
      if ($label) {
        $output = "\$variables['$label'] = " . var_export($input, TRUE) . ';';
      }
      else {
        $output = var_export($input, TRUE);
      }
      break;
    case 'json':
      $output = drush_json_encode($input);
      break;
    case 'print_r':
    default:
      if (is_string($input)) {
        $input = '"' . $input . '"';
      }
      elseif (is_array($input) || is_object($input)) {
        $input = print_r($input, TRUE);
      }

      if ($label) {
        $input = $label . ': ' . $input;
      }
      $output = $input;
      break;
  }
  return $output;
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
  if (function_exists('t') && (drush_drupal_version() == 7 || function_exists('theme'))) {
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
 * Convert html to readable text.  Compatible API to
 * drupal_html_to_text, but less functional.  Caller
 * might prefer to call drupal_html_to_text if there
 * is a bootstrapped Drupal site available.
 *
 * @param string $html
 *   The html text to convert.
 *
 * @return string
 *   The plain-text representation of the input.
 */
function drush_html_to_text($html, $allowed_tags = NULL) {
  $replacements = array(
    '<hr>' => '------------------------------------------------------------------------------',
    '<li>' => '  * ',
    '<h1>' => '===== ',
    '</h1>' => ' =====',
    '<h2>' => '---- ',
    '</h2>' => ' ----',
    '<h3>' => '::: ',
    '</h3>' => ' :::',
    '<br/>' => "\n",
  );
  $text = str_replace(array_keys($replacements), array_values($replacements), $html);
  return html_entity_decode(preg_replace('/ *<[^>]*> */', ' ', $text));
}


/**
 * Print a formatted table.
 *
 * @param $rows
 *   The rows to print.
 * @param $header
 *   If TRUE, the first line will be treated as table header.
 * @param $widths
 *   The widths of each column (in characters) to use - if not specified this
 *   will be determined automatically, based on a "best fit" algorithm.
 * @param $handle
 *    File handle to write to.  NULL will write
 *    to standard output, STDERR will write to the standard
 *    error.  See http://php.net/manual/en/features.commandline.io-streams.php
 * @return $tbl
 *   Use $tbl->getTable() to get the output from the return value.
 */
function drush_print_table($rows, $header = FALSE, $widths = array(), $handle = NULL) {
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
  $output = $tbl->getTable();
  if (!stristr(PHP_OS, 'WIN')) {
    $output = str_replace("\r\n", PHP_EOL, $output);
  }

  drush_print($output, 0, $handle);
  return $tbl;
}

/**
 * Convert an associative array of key : value pairs into
 * a table suitable for processing by drush_print_table.
 *
 * @param $keyvalue_table
 *    An associative array of key : value pairs.
 * @return
 *    An array of arrays, where the keys from the input
 *    array are stored in the first column, and the values
 *    are stored in the third.  A second colum is created
 *    specifically to hold the ':' separator.
 */
function drush_key_value_to_array_table($keyvalue_table) {
  $table = array();
  foreach ($keyvalue_table as $key => $value) {
    if (isset($value)) {
      $table[] = array($key, ' :', $value);
    }
    else {
      $table[] = array($key . ':', '', '');
    }
  }
  return $table;
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
 * Print the contents of a file.
 *
 * @param string $file
 *   Full path to a file.
 */
function drush_print_file($file) {
  // Don't even bother to print the file in --no mode
  if (drush_get_context('DRUSH_NEGATIVE')) {
    return;
  }
  if ((substr($file,-4) == ".htm") || (substr($file,-5) == ".html")) {
    $tmp_file = drush_tempnam(basename($file));
    file_put_contents($tmp_file, drush_html_to_text(file_get_contents($file)));
    $file = $tmp_file;
  }
  // Do not wait for user input in --yes or --pipe modes
  if (drush_get_context('DRUSH_PIPE')) {
    drush_print_pipe(file_get_contents($file));
  }
  elseif (drush_get_context('DRUSH_AFFIRMATIVE')) {
    drush_print(file_get_contents($file));
  }
  elseif (drush_shell_exec_interactive("less %s", $file)) {
    return;
  }
  elseif (drush_shell_exec_interactive("more %s", $file)) {
    return;
  }
  else {
    drush_print(file_get_contents($file));
  }
}


/**
 * Converts a PHP variable into its Javascript equivalent.
 *
 * We provide a copy of D7's drupal_json_encode since this function is
 * unavailable on earlier versions of Drupal.
 *
 * @see drupal_json_decode()
 * @ingroup php_wrappers
 */
function drush_json_encode($var) {
  // json_encode() does not escape <, > and &, so we do it with str_replace().
  return str_replace(array('<', '>', '&'), array('\u003c', '\u003e', '\u0026'), json_encode($var));
}

/**
 * Converts an HTML-safe JSON string into its PHP equivalent.
 *
 * We provide a copy of D7's drupal_json_decode since this function is
 * unavailable on earlier versions of Drupal.
 *
 * @see drupal_json_encode()
 * @ingroup php_wrappers
 */
function drush_json_decode($var) {
  return json_decode($var, TRUE);
}

/**
 * @} End of "defgroup outputfunctions".
 */
