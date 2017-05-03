<?php

use Drupal\Core\Render\Markup;
use Drush\Log\LogLevel;

/**
 * @defgroup outputfunctions Process output text.
 * @{
 */

/**
 * Prints a message with optional indentation. In general,
 * drush_log($message, LogLevel::OK) is often a better choice than this function.
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
    //$output = \Drush::service('output');
    //$output->write($msg);
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
  $output = NULL;
  if (function_exists('t') && drush_drupal_major_version() == 7) {
    $output = t($string, $args);
  }
  // The language system requires a working container which has the string
  // translation service.
  else if (drush_drupal_major_version() >= 8 && \Drupal::hasService('string_translation')) {
    // Drupal 8 removes !var replacements, creating a user-level error when
    // these are used, so we'll pre-replace these before calling translate().
    list($string, $args) = replace_legacy_dt_args($string, $args);
    $output = (string) \Drupal::translation()->translate($string, $args);
  }
  else if (function_exists('t') && drush_drupal_major_version() <= 7 && function_exists('theme')) {
    $output = t($string, $args);
  }

  // If Drupal's t() function unavailable.
  if (!isset($output)) {
    if (!empty($args)) {
      $output = strtr($string, $args);
    }
    else {
      $output = $string;
    }
  }
  return $output;
}

/**
 * Replace placeholders that begin with a '!' with '@'.
 */
function replace_legacy_dt_args(&$string, &$legacy_args) {
  $args = array();
  $replace = array();
  foreach ($legacy_args as $name => $argument) {
    if ($name[0] == '!') {
      $new_arg = '@' . substr($name, 1);
      $replace[$name] = $new_arg;
      $args[$new_arg] = Markup::create($argument);
    }
    else {
      $args[$name] = $argument;
    }
  }
  return [
    strtr($string, $replace),
    $args
  ];
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
 *   An associative array whose keys are column IDs and values are widths of each column (in characters).
 *   If not specified this will be determined automatically, based on a "best fit" algorithm.
 * @param $handle
 *    File handle to write to.  NULL will write
 *    to standard output, STDERR will write to the standard
 *    error.  See http://php.net/manual/en/features.commandline.io-streams.php
 * @return $tbl
 *   Use $tbl->getTable() to get the output from the return value.
 */
function drush_print_table($rows, $header = FALSE, $widths = array(), $handle = NULL) {
  $tbl = _drush_format_table($rows, $header, $widths);
  $output = $tbl->getTable();
  if (!stristr(PHP_OS, 'WIN')) {
    $output = str_replace("\r\n", PHP_EOL, $output);
  }

  drush_print(rtrim($output), 0, $handle);
  return $tbl;
}

/**
 * Format a table of data.
 *
 * @param $rows
 *   The rows to print.
 * @param $header
 *   If TRUE, the first line will be treated as table header.
 * @param $widths
 *   An associative array whose keys are column IDs and values are widths of each column (in characters).
 *   If not specified this will be determined automatically, based on a "best fit" algorithm.
 * @param array $console_table_options
 *   An array that is passed along when constructing a Console_Table instance.
 * @return $output
 *   The formatted output.
 */
function drush_format_table($rows, $header = FALSE, $widths = array(), $console_table_options = array()) {
  $tbl = _drush_format_table($rows, $header, $widths, $console_table_options);
  $output = $tbl->getTable();
  if (!drush_is_windows()) {
    $output = str_replace("\r\n", PHP_EOL, $output);
  }
  return $output;
}

function _drush_format_table($rows, $header = FALSE, $widths = array(), $console_table_options = array()) {
  // Add defaults.
  $tbl = new ReflectionClass('Console_Table');
  $console_table_options += array(CONSOLE_TABLE_ALIGN_LEFT , '');
  $tbl = $tbl->newInstanceArgs($console_table_options);

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
  return $tbl;
}

/**
 * Select the fields that should be used.
 */
function drush_select_fields($all_field_labels, $fields, $strict = TRUE) {
  $field_labels = array();
  foreach ($fields as $field) {
    if (array_key_exists($field, $all_field_labels)) {
      $field_labels[$field] = $all_field_labels[$field];
    }
    else {
      // Allow the user to select fields via their human-readable names.
      // This is less convenient than the field name (since the human-readable
      // names may contain spaces, and must therefore be quoted), but these are
      // the values that the user sees in the command output. n.b. the help
      // text lists fields by their more convenient machine names.
      $key = array_search(strtolower($field), array_map('strtolower', $all_field_labels));
      if ($key !== FALSE) {
        $field_labels[$key] = $all_field_labels[$key];
      }
      elseif (!$strict) {
        $field_labels[$field] = $field;
      }
    }
  }
  return $field_labels;
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
    foreach ($row as $col_id => $cell) {
      if (empty($widths[$col_id])) {
        $length = strlen($cell);
        if ($length == 0) {
          $col_dist[$col_id][0] = 0;
        }
        while ($length > 0) {
          if (!isset($col_dist[$col_id][$length])) {
            $col_dist[$col_id][$length] = 0;
          }
          $col_dist[$col_id][$length]++;
          $length--;
        }
      }
    }
  }
  foreach ($col_dist as $col_id => $count) {
    // Sort the distribution in decending key order.
    krsort($col_dist[$col_id]);
    // Initially we set all columns to their "ideal" longest width
    // - i.e. the width of their longest column.
    $auto_widths[$col_id] = max(array_keys($col_dist[$col_id]));
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
    foreach ($col_dist as $col_id => $counts) {
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
        $column = $col_id;
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
 * Converts a PHP variable into its Javascript equivalent.
 *
 * We provide a copy of D7's drupal_json_encode since this function is
 * unavailable on earlier versions of Drupal.
 *
 * @see drupal_json_decode()
 * @ingroup php_wrappers
 */
function drush_json_encode($var) {
  $json = json_encode($var, JSON_PRETTY_PRINT);
  // json_encode() does not escape <, > and &, so we do it with str_replace().
  return str_replace(array('<', '>', '&'), array('\u003c', '\u003e', '\u0026'), $json);
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
