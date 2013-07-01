<?php

/**
 * Output formatter 'var_export'
 *
 * Note: this class is also used by format 'config'
 *
 * @param $data
 *   The $data parameter is rendered with the php var_export() function
 * @param $metadata
 *   'label' - If present, prints "$variable['label'] = " prior to the data
 *   'variable-name' - If present, provides an alternate name for $variable
 *     when labels are in use.
 *
 * Code:
 *
 *   return array(
 *     "a" => array("b" => 2, "c" => 3),
 *     "d" => array("e" => 5, "f" => 6)
 *   );
 *
 * Output with --format=export:
 *
 *   array (
 *     'a' =>
 *     array (
 *       'b' => 2,
 *       'c' => 3,
 *     ),
 *     'd' =>
 *     array (
 *       'e' => 5,
 *       'f' => 6,
 *     ),
 *   )
 *
 * Output with --format=config: (list of export)
 *
 *   $config['a'] = array (
 *     'b' => 2,
 *     'c' => 3,
 *   );
 *   $config['d'] = array (
 *     'e' => 5,
 *     'f' => 6,
 *   );
 */
class drush_outputformat_var_export extends drush_outputformat {
  function format($input, $metadata) {
    if (isset($metadata['label'])) {
      $variable_name = isset($metadata['variable-name']) ? $metadata['variable-name'] : 'variables';
      $variable_name = preg_replace("/[^a-zA-Z0-9_-]/", "", str_replace(' ', '_', $variable_name));
      $label = $metadata['label'];
      $label_template = (isset($metadata['label-template'])) ? $metadata['label-template'] : '$!variable["!label"] = !value;';
      $output = dt($label_template, array('!variable' => $variable_name, '!label' => $label, '!value' => var_export($input, TRUE)));
    }
    else {
      $output = drush_var_export($input);
    }
    return $output;
  }
}
