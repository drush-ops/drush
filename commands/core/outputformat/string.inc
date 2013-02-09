<?php

/**
 * Output formatter 'string'
 *
 * @param $data
 *   The render data may be either a string or an array
 *   string - printed as-is, without quotes
 *   array - the value of the first item in the array is printed as-is
 * @param $metadata
 *   'label' - If present, prints "label: " prior to the data
 *
 * Code:
 *
 *   return DRUSH_VERSION;
 *
 * Output with --format=string:
 *
 *   6.0-dev
 */
class drush_outputformat_string extends drush_outputformat {
  function format($data, $metadata) {
    // If the data is an array, print the value of the first item.
    if (is_array($data)) {
      if (count($data) > 1) {
        return $this->format_error("Multiple rows provided where only one is allowed.");
      }
      if (!empty($data)) {
        $data = reset($data);
      }
      if (is_array($data)) {
        return $this->format_error("Array provided where a string is required.");
      }
    }
    return (string)$data;
  }
}
