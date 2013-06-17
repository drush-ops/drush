<?php

/**
 * Output formatter 'csv-or-string'
 *
 * @param $data
 *   The render data may be either a string or an array
 *   - string: printed as-is, without quotes.
 *   - array: the value is printed as a csv list.
 *
 * This is a helper format for handling nested csv lists.
 */
class drush_outputformat_csv_or_string extends drush_outputformat {
  function format($data, $metadata) {
    // If the data is an array, print it as a comma-separated list
    if (is_array($data)) {
      return drush_format($data, $metadata, 'csv');
    }
    return (string)$data;
  }
}
