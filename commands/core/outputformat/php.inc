<?php

/**
 * Output formatter 'php'
 *
 * @param $data
 *   The $data parameter is rendered as a serialized php string
 * @param $metadata
 *
 * Code:
 *
 */
class drush_outputformat_php extends drush_outputformat {
  function format($input, $metadata) {
    return serialize($input);
  }
}
