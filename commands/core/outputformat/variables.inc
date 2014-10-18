<?php

/**
 * Output formatter 'variables'
 *
 * @param $data
 *   The $data parameter is expected to be a nested array of key / value pairs.
 *   The top-level key becomes the variable name, $metadata['variable-name'],
 *   and the key on the inner-level items becomes the array label,
 *   $metadata['label'].  These items are then rendered by the 'var_export' formatter.
 * @param $metadata
 *   Unused.
 *
 * Code:
 *
 *   return array(
 *     "a" => array("b" => 2, "c" => 3),
 *     "d" => array("e" => 5, "f" => 6)
 *   );
 *
 * Output with --format=variables:
 *
 *   $a['b'] = 2;
 *   $a['c'] = 3;
 *   $d['e'] = 5;
 *   $d['f'] = 6;
 */
class drush_outputformat_variables extends drush_outputformat {
  function validate() {
    $metadata = $this->engine_config;
    $this->sub_engine = drush_load_engine('outputformat', 'var_export', $metadata);
    if (!is_object($this->sub_engine)) {
      return FALSE;
    }
    return TRUE;
  }

  function format($data, $metadata) {
    $output = '';
    if (is_array($data)) {
      foreach ($data as $variable_name => $section) {
        foreach ($section as $label => $value) {
          $metameta = array(
            'variable-name' => $variable_name,
            'label' => $label,
          );
          $formatted_item = $this->sub_engine->process($value, $metameta);
          if ($formatted_item === FALSE) {
            return FALSE;
          }
          $output .= $formatted_item;
          $output .= "\n";
          }
      }
    }
    return $output;
  }
}
