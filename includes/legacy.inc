<?php
use Drush\Sql\SqlBase;

/**
 * These are deprecated functions that we keep around just to ease the transition
 * to current Drush version. They may not work the same as they did in prior
 * versions. If they aren't working for you, consider upgrading your commandfile
 * so that it works with modern Drush.
 *
 * @deprecated
 */

// @deprecated
function drush_sql_bootstrap_further() {}

function drush_sql_get_class($db_spec = NULL) {
  $options = [];
  if ($url = drush_get_option('db-url')) {
    $options['db-url'] = $url;
  }
  return SqlBase::create($options);
}

/**
 * Retrieves a collapsed list of all options
 * with a specified prefix.
 *
 * @deprecated
 */
function drush_get_merged_prefixed_options($prefix) {
  $merged_options = drush_get_merged_options();
  $result = [];
  foreach ($merged_options as $key => $value) {
    if ($prefix == substr($key, 0, strlen($prefix))) {
      $result[substr($key, strlen($prefix))] = $value;
    }
  }

  return $result;
}

/**
 * Remove the trailing DIRECTORY_SEPARATOR from a path.
 * Will actually remove either / or \ on Windows.
 *
 * @deprecated
 */
function drush_trim_path($path, $os = NULL) {
    if (drush_is_windows($os)) {
        return rtrim($path, '/\\');
    }
    else {
        return rtrim($path, '/');
    }
}

/**
 * Makes sure the path has only path separators native for the current operating system
 *
 * @deprecated
 */
function drush_normalize_path($path) {
    if (drush_is_windows()) {
        $path = str_replace('/', '\\',  strtolower($path));
    }
    else {
        $path = str_replace('\\', '/', $path);
    }
    return drush_trim_path($path);
}

/**
 * Calculates a single md5 hash for all files a directory (incuding subdirectories)
 *
 * @deprecated
 */
function drush_dir_md5($dir) {
    $flist = drush_scan_directory($dir, '/./', ['.', '..'], 0, TRUE, 'filename', 0, TRUE);
    $hashes = [];
    foreach ($flist as $f) {
        $sum = [];
        exec('cksum ' . escapeshellarg($f->filename), $sum);
        $hashes[] = trim(str_replace([$dir], [''], $sum[0]));
    }
    sort($hashes);
    return md5(implode("\n", $hashes));
}

/**
 * Table printing functions.
 */

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
 *
 * @deprecated Use the table output formatter instead. Example: \Drush\Drupal\Commands\pm\PmCommands::pmList
 */
function drush_print_table($rows, $header = FALSE, $widths = [], $handle = NULL) {
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
function drush_format_table($rows, $header = FALSE, $widths = [], $console_table_options = []) {
    $tbl = _drush_format_table($rows, $header, $widths, $console_table_options);
    $output = $tbl->getTable();
    if (!drush_is_windows()) {
        $output = str_replace("\r\n", PHP_EOL, $output);
    }
    return $output;
}

function _drush_format_table($rows, $header = FALSE, $widths = [], $console_table_options = []) {
    // Add defaults.
    $tbl = new ReflectionClass('Console_Table');
    $console_table_options += [CONSOLE_TABLE_ALIGN_LEFT , ''];
    $tbl = $tbl->newInstanceArgs($console_table_options);

    $auto_widths = drush_table_column_autowidth($rows, $widths);

    // Do wordwrap on all cells.
    $newrows = [];
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
    $field_labels = [];
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
    $col_dist = [];
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
 * Convert from an old-style database URL to an array of database settings.
 *
 * @param db_url
 *   A Drupal 6 db url string to convert, or an array with a 'default' element.
 * @return array
 *   An array of database values containing only the 'default' element of
 *   the db url. If the parse fails the array is empty.
 */
function drush_convert_db_from_db_url($db_url) {
  $db_spec = [];

  if (is_array($db_url)) {
    $db_url_default = $db_url['default'];
  }
  else {
    $db_url_default = $db_url;
  }

  // If it's a sqlite database, pick the database path and we're done.
  if (strpos($db_url_default, 'sqlite://') === 0) {
    $db_spec = [
      'driver'   => 'sqlite',
      'database' => substr($db_url_default, strlen('sqlite://')),
    ];
  }
  else {
    $url = parse_url($db_url_default);
    if ($url) {
      // Fill in defaults to prevent notices.
      $url += [
        'scheme' => NULL,
        'user'   => NULL,
        'pass'   => NULL,
        'host'   => NULL,
        'port'   => NULL,
        'path'   => NULL,
      ];
      $url = (object)array_map('urldecode', $url);
      $db_spec = [
        'driver'   => $url->scheme == 'mysqli' ? 'mysql' : $url->scheme,
        'username' => $url->user,
        'password' => $url->pass,
        'host' => $url->host,
        'port' => $url->port,
        'database' => ltrim($url->path, '/'),
      ];
    }
  }

  return $db_spec;
}

