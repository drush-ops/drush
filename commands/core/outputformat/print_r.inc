<?php

/**
 * Output formatter 'print-r'
 *
 * @param $data
 *   The $data parameter is rendered with the php print_r function
 * @param $metadata
 *   'label' - If present, prints "label: " prior to the data
 *
 * Code:
 *
 *   return array(
 *     "a" => array("b" => 2, "c" => 3),
 *     "d" => array("e" => 5, "f" => 6)
 *   );
 *
 * Output with --format=print-r:
 *
 *   Array
 *   (
 *       [a] => Array
 *           (
 *               [b] => 2
 *               [c] => 3
 *           )
 *
 *       [d] => Array
 *           (
 *               [e] => 5
 *               [f] => 6
 *           )
 *   )
 */
class drush_outputformat_print_r extends drush_outputformat {
  function format($input, $metadata) {
    if (is_string($input)) {
      $output = '"' . $input . '"';
    }
    elseif (is_array($input) || is_object($input)) {
      $output = print_r($input, TRUE);
    }
    else {
      $output = $input;
    }
    if (isset($metadata['label'])) {
      $output = $metadata['label'] . ': ' . $output;
    }
    return $output;
  }
}
