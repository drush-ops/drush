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
  if (!isset($handle)) {
    $handle = STDOUT;
    // In the past, Drush would use `print` here; now that we are using
    // fwrite (to avoid problems with php sending the http headers), we
    // must explicitly capture the output, because ob_start() / ob_end()
    // does not capture output written via fwrite to STDOUT.
    drush_backend_output_collect($msg);
  }
  fwrite($handle, $msg);
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
 * Format some data and print it out.  Respect --format option.
 */
function drush_print_format($input, $default_format, $metadata = NULL) {
  drush_print(drush_format($input, $metadata, drush_get_option('format', $default_format)));
}

/**
 * Prepares a variable for printing.  Loads the requested output
 * formatter and uses it to process the provided input.
 *
 * @param mixed $input
 *   A variable.
 * @param string $metadata
 *   Optional formatting metadata. Not used by all formats.
 *     'label' - text to label data with in some formats (e.g. export, config)
 * @param string $format
 *   Optional format; defaults to print_r.
 * @return string
 *   The variable formatted according to specified format.
 *   Ready for drush_print().
 */
function drush_format($input, $metadata = NULL, $format = NULL) {
  $output = '';
  // Look up the format and label, and fill in default values for metadata
  if (is_string($metadata)) {
    $metadata = array('label' => $metadata);
  }
  if (!is_array($metadata)) {
    $metadata = array();
  }
  $metadata += array(
    'metameta' => array(),
  );
  if (isset($metadata['format'])) {
    // If the format is set in metadata, then it will
    // override whatever is passed in via the $format parameter.
    $format = $metadata['format'];
  }
  if (!isset($format)) {
    // TODO: we shouldn't ever call drush_get_option here.
    // Get rid of this once we confirm that there are no
    // callers that still need it.
    $format = drush_get_option('format', 'print-r');
  }

  $formatter = drush_load_engine('outputformat', $format);
  if ($formatter) {
    if ($formatter === TRUE) {
      return drush_set_error(dt('No outputformat class defined for !format', array('!format' => $format)));
    }
    $output = $formatter->process($input, $metadata);
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
 * Convert an associative array of key : value pairs into
 * a table suitable for processing by drush_print_table.
 *
 * @param $keyvalue_table
 *    An associative array of key : value pairs.
 * @param $metadata
 *    'key-value-item':  If the value is an array, then
 *    the item key determines which item from the value
 *    will appear in the output.
 * @return
 *    An array of arrays, where the keys from the input
 *    array are stored in the first column, and the values
 *    are stored in the third.  A second colum is created
 *    specifically to hold the ':' separator.
 */
function drush_key_value_to_array_table($keyvalue_table, $metadata = array()) {
  if (!is_array($keyvalue_table)) {
    return drush_set_error('DRUSH_INVALID_FORMAT', dt("Data not compatible with selected key-value output format."));
  }
  if (!is_array($metadata)) {
    $metadata = array('key-value-item' => $metadata);
  }
  $item_key = array_key_exists('key-value-item', $metadata) ? $metadata['key-value-item'] : NULL;
  $metadata += array(
    'format' => 'list',
    'separator' => ' ',
  );
  $table = array();
  foreach ($keyvalue_table as $key => $value) {
    if (isset($value)) {
      if (is_array($value) && isset($item_key)) {
        $value = $value[$item_key];
      }
      // We should only have simple values here, but if
      // we don't, use drush_format() to flatten as a fallback.
      if (is_array($value)) {
        $value = drush_format($value, $metadata, 'list');
      }
    }
    if (isset($metadata['include-field-labels']) && !$metadata['include-field-labels']) {
      $table[] = array(isset($value) ? $value : '');
    }
    elseif (isset($value)) {
      $table[] = array($key, ' :', $value);
    }
    else {
      $table[] = array($key . ':', '', '');
    }
  }
  return $table;
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
 * Select the fields from the input array that should be output.
 *
 * @param $input
 *   An associative array of key:value pairs to be output
 * @param $fields
 *   An associative array that maps FROM a field in $input
 *   TO the corresponding field name in $output.
 * @param $mapping
 *   An associative array that maps FROM a field in $fields
 *   TO the actual field in $input to use in the preceeding
 *   translation described above.
 * @return
 *   The input array, re-ordered and re-keyed per $fields
 */
function drush_select_output_fields($input, $fields, $mapping = array(), $default_value = NULL) {
  $result = array();
  if (empty($fields)) {
    $result = $input;
  }
  else {
    foreach ($fields as $key => $label) {
      $value = drush_lookup_field_by_path($input, $key, $mapping, $default_value);
      if (isset($value)) {
        $result[$label] = $value;
      }
    }
  }
  return $result;
}

/**
 * Return a specific item inside an array, as identified
 * by the provided path.
 *
 * @param $input:
 *   An array of items, potentially multiple layers deep.
 * @param $path:
 *   A specifier of array keys, either in an array or separated by
 *   a '/', that list the elements of the array to access.  This
 *   works much like a very simple version of xpath for arrays, with
 *   all items being treated identically (like elements).
 * @param $mapping:
 *   (optional) An array whose keys may correspond to the $path parameter and
 *   whose values are the corresponding paths to be used in $input.
 *
 * Example 1:
 *
 *   $input = array('#name' => 'site.dev', '#id' => '222');
 *   $path = '#name';
 *   result: 'site.dev';
 *
 * Example 2:
 *
 *   $input = array('ca' => array('sf' => array('mission'=>array('1700'=>'woodward'))));
 *   $path = 'ca/sf/mission/1701';
 *   result: 'woodward'
 *
 * Example 3:
 *
 *   $input = array('#name' => 'site.dev', '#id' => '222');
 *   $path = 'name';
 *   $mapping = array('name' => '#name');
 *   result: 'site.dev';
 */
function drush_lookup_field_by_path($input, $path, $mapping = array(), $default_value = NULL) {
  $result = '';
  if (isset($mapping[$path])) {
    $path = $mapping[$path];
  }
  if (!is_array($path)) {
    $parts = explode('/', $path);
  }
  if (!empty($parts)) {
    $result = $input;
    foreach ($parts as $key) {
      if ((is_array($result)) && (isset($result[$key]))) {
        $result = $result[$key];
      }
      else {
        return $default_value;
      }
    }
  }
  return $result;
}

/**
 * Given a table array (an associative array of associative arrays),
 * return an array of all of the values with the specified field name.
 */
function drush_output_get_selected_field($input, $field_name, $default_value = '') {
  $result = array();
  foreach ($input as $key => $data) {
    if (is_array($data) && isset($data[$field_name])) {
      $result[] = $data[$field_name];
    }
    else {
      $result[] = $default_value;
    }
  }
  return $result;
}

/**
 * Hide any fields that are empty
 */
function drush_hide_empty_fields($input, $fields) {
  $has_data = array();
  foreach ($input as $key => $data) {
    foreach ($fields as $field => $label) {
      if (isset($data[$field]) && !empty($data[$field])) {
        $has_data[$field] = TRUE;
      }
    }
  }
  foreach ($fields as $field => $label) {
    if (!isset($has_data[$field])) {
      unset($fields[$field]);
    }
  }
  return $fields;
}

/**
 * Convert an array of data rows, where each row contains an
 * associative array of key : value pairs, into
 * a table suitable for processing by drush_print_table.
 * The provided $header determines the order that the items
 * will appear in the output.  Only data items listed in the
 * header will be placed in the output.
 *
 * @param $rows_of_keyvalue_table
 *    array(
 *      'row1' => array('col1' => 'data', 'col2' => 'data'),
 *      'row2' => array('col1' => 'data', 'col2' => 'data'),
 *    )
 * @param $header
 *    array('col1' => 'Column 1 Label', 'col2' => 'Column 2 Label')
 * @param $metadata
 *    (optional) An array of special options, all optional:
 *    - strip-tags: call the strip_tags function on the data
 *         before placing it in the table
 *    - concatenate-columns: an array of:
 *         - dest-col: array('src-col1', 'src-col2')
 *         Appends all of the src columns with whatever is
 *         in the destination column.  Appended columns are
 *         separated by newlines.
 *    - transform-columns: an array of:
 *         - dest-col: array('from' => 'to'),
 *         Transforms any occurance of 'from' in 'dest-col' to 'to'.
 *    - format-cell: Drush output format name to use to format
 *         any cell that is an array.
 *    - process-cell: php function name to use to process
 *         any cell that is an array.
 *    - field-mappings: an array whose keys are some or all of the keys in
 *         $header and whose values are the corresponding keys to use when
 *         indexing the values of $rows_of_keyvalue_table.
 * @return
 *    An array of arrays
 */
function drush_rows_of_key_value_to_array_table($rows_of_keyvalue_table, $header, $metadata) {
  if (isset($metadata['hide-empty-fields'])) {
    $header = drush_hide_empty_fields($rows_of_keyvalue_table, $header);
  }
  if (empty($header)) {
    $first = (array)reset($rows_of_keyvalue_table);
    $keys = array_keys($first);
    $header = array_combine($keys, $keys);
  }
  $table = array(array_values($header));
  if (isset($rows_of_keyvalue_table) && is_array($rows_of_keyvalue_table) && !empty($rows_of_keyvalue_table)) {
    foreach ($rows_of_keyvalue_table as $row_index => $row_data) {
      $row_data = (array)$row_data;
      $row = array();
      foreach ($header as $column_key => $column_label) {
        $data = drush_lookup_field_by_path($row_data, $column_key, $metadata['field-mappings']);
        if (array_key_exists('transform-columns', $metadata)) {
          foreach ($metadata['transform-columns'] as $dest_col => $transformations) {
            if ($dest_col == $column_key) {
              $data = str_replace(array_keys($transformations), array_values($transformations), $data);
            }
          }
        }
        if (array_key_exists('concatenate-columns', $metadata)) {
          foreach ($metadata['concatenate-columns'] as $dest_col => $src_cols) {
            if ($dest_col == $column_key) {
              $data = '';
              if (!is_array($src_cols)) {
                $src_cols = array($src_cols);
              }
              foreach($src_cols as $src) {
                if (array_key_exists($src, $row_data) && !empty($row_data[$src])) {
                  if (!empty($data)) {
                    $data .= "\n";
                  }
                  $data .= $row_data[$src];
                }
              }
            }
          }
        }
        if (array_key_exists('format-cell', $metadata) && is_array($data)) {
          $data = drush_format($data, array(), $metadata['format-cell']);
        }
        if (array_key_exists('process-cell', $metadata) && is_array($data)) {
          $data = $metadata['process-cell']($data, $metadata);
        }
        if (array_key_exists('strip-tags', $metadata)) {
          $data = strip_tags($data);
        }
        $row[] = $data;
      }
      $table[] = $row;
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
  if (version_compare(phpversion(), '5.4.0', '>=')) {
    $json = json_encode($var, JSON_PRETTY_PRINT);
  }
  else {
    $json = json_encode($var);
  }
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
 * Drupal-friendly var_export().  Taken from utility.inc in Drupal 8.
 *
 * @param $var
 *   The variable to export.
 * @param $prefix
 *   A prefix that will be added at the beginning of every lines of the output.
 *
 * @return
 *   The variable exported in a way compatible to Drupal's coding standards.
 */
function drush_var_export($var, $prefix = '') {
  if (is_array($var)) {
    if (empty($var)) {
      $output = 'array()';
    }
    else {
      $output = "array(\n";
      // Don't export keys if the array is non associative.
      $export_keys = array_values($var) != $var;
      foreach ($var as $key => $value) {
        $output .= '  ' . ($export_keys ? drush_var_export($key) . ' => ' : '') . drush_var_export($value, '  ', FALSE) . ",\n";
      }
      $output .= ')';
    }
  }
  elseif (is_bool($var)) {
    $output = $var ? 'TRUE' : 'FALSE';
  }
  elseif (is_string($var)) {
    $line_safe_var = str_replace("\n", '\n', $var);
    if (strpos($var, "\n") !== FALSE || strpos($var, "'") !== FALSE) {
      // If the string contains a line break or a single quote, use the
      // double quote export mode. Encode backslash and double quotes and
      // transform some common control characters.
      $var = str_replace(array('\\', '"', "\n", "\r", "\t"), array('\\\\', '\"', '\n', '\r', '\t'), $var);
      $output = '"' . $var . '"';
    }
    else {
      $output = "'" . $var . "'";
    }
  }
  elseif (is_object($var) && get_class($var) === 'stdClass') {
    // var_export() will export stdClass objects using an undefined
    // magic method __set_state() leaving the export broken. This
    // workaround avoids this by casting the object as an array for
    // export and casting it back to an object when evaluated.
    $output = '(object) ' . drush_var_export((array) $var, $prefix);
  }
  else {
    $output = var_export($var, TRUE);
  }

  if ($prefix) {
    $output = str_replace("\n", "\n$prefix", $output);
  }

  return $output;
}

/**
 * @} End of "defgroup outputfunctions".
 */
