<?php

/**
 * Output formatter 'message'
 *
 * @param $data
 *   The parameters for the render data
 * @param $metadata
 *   'message-template' - Provides the template string to use with dt().
 *
 * Code:
 *
 *   return array('a' => 1, 'b' => 2);
 *
 * Given 'message-template' == 'The first is !a and the second is !b',
 * output with --format=message:
 *
 *   The first is 1 and the second is 2
 */
class drush_outputformat_message extends drush_outputformat {
  function format($data, $metadata) {
    $result = '';
    if (isset($metadata['message-template'])) {
      foreach ($data as $key => $value) {
        $data_for_dt['!' . $key] = $value;
      }
      $result = dt($metadata['message-template'], $data_for_dt);
    }
    return $result;
  }
}
