<?php

/**
 * Output formatter 'json'
 *
 * @param $data
 *   The $data parameter is converted to Javascript Object Notation
 * @param $metadata
 *   Unused
 *
 * Code:
 *
 *   return array(
 *     "a" => array("b" => 2, "c" => 3),
 *     "d" => array("e" => 5, "f" => 6)
 *   );
 *
 * Output with --format=json:
 *
 *   {"a":{"b":2,"c":3},"d":{"e":5,"f":6}}
 */
class drush_outputformat_json extends drush_outputformat {
  function format($input, $metadata) {
    return drush_json_encode($input);
  }
}
