<?php

/**
 * Output formatter 'ini'
 *
 * @param $data
 *   The $data parameter contains an array of key / value pairs which
 *   are rendered as "key=value", suitable for parsing with the php
 *   function parse_ini_file (or parse_ini_string).  If the value is
 *   an array, it will be imploded into a comma-separated list.
 * @param $metadata
 *   'label' - If present, creates a section header "[label]" prior to the data
 *   'separator' - If present, used instead of ', ' when impoding data values
 *   'ini-item' - If present, selects a single item from any data value that is
 *     an array and uses it instead of imploding all values together.
 *
 * Code:
 *
 *   return array(
 *     "a" => array("b" => 2, "c" => 3),
 *     "d" => array("e" => 5, "f" => 6)
 *   );
 *
 * Output with --format=ini:
 *
 *    a=2, 3
 *    d=5, 6
 *
 * Output with --format=ini-sections:
 *
 *    [a]
 *    b=2
 *    c=3
 *
 *    [d]
 *    e=5
 *    f=6
 */
class drush_outputformat_ini extends drush_outputformat {
  function format($input, $metadata) {
    $simplify_single = ((count($input) == 1) && isset($metadata['simplify-single']));
    if (is_array($input)) {
      $output = '';
      if (isset($metadata['label'])) {
        $output = "[" . $metadata['label'] . "]\n";
      }
      foreach ($input as $key => $value) {
        if (isset($value)) {
          if (is_array($value)) {
            if (isset($metadata['ini-item'])) {
              $value = $value[$metadata['ini-item']];
            }
            $ini_metadata = isset($metadata['ini-metadata']) ? $metadata['ini-metadata'] : array();
            $ini_metadata += array(
              'list-separator' => ' '
            );
            if (is_array($value)) {
              $value = drush_format($value, $ini_metadata, 'list');
            }
          }
          if ($simplify_single) {
            $output = $value;
          }
          else {
            $output .= sprintf("%s=%s\n", strtolower(str_replace(' ', '_', $key)), $value);
          }
        }
      }
    }
    else {
      $output = (string)$input;
    }
    return $output;
  }
}
