<?php

/**
 * Output formatter 'key_value'
 *
 * @param $data
 *   The $data parameter contains an array of key / value pairs which
 *   are rendered as "key   :   value" in a formatted word-wrapped table
 *   with aligned columns.  'value' is expected to always be a simple string;
 *   if it is not, it is rendered with var_export.
 * @param $metadata
 *   'label' - If present, creates a section header "[label]" prior to the data
 *   'separator' - If present, used instead of ', ' when impoding data values
 *   'ini-item' - If present, selects a single item from any data value that is
 *     an array and uses it instead of imploding all values together.
 *
 * Code:
 *
 *   return array(
 *     "b" => "Two B or ! Two B, that is the comparison",
 *     "c" => "I see that C has gone to Sea"
 *   );
 *
 * Output with --format=key-value:
 *
 *     b   :  Two B or ! Two B,
 *            that is the
 *            comparison
 *     c   :  I see that C has gone
 *            to Sea
 *
 * Code:
 *
 *   return array(
 *     "a" => array(
 *       "b" => "Two B or ! Two B, that is the comparison",
 *       "c" => "I see that C has gone to Sea"
 *     ),
 *     "d" => array(
 *       "e" => "Elephants and electron microscopes",
 *       "f" => "My margin is too small"
 *     )
 *   );
 *
 * Output with --format=key-value-list:
 *
 *     b   :  Two B or ! Two B,
 *            that is the
 *            comparison
 *     c   :  I see that C has gone
 *            to Sea
 *
 *     e   :  Elephants and
 *            electron microscopes
 *     f   :  My margin is too
 *            small
 */
class drush_outputformat_key_value extends drush_outputformat {
  function format($input, $metadata) {
    if (!is_array($input)) {
      if (isset($metadata['label'])) {
        $input = array(dt($metadata['label']) => $input);
      }
      else {
        return $this->format_error(dt('No label provided.'));
      }
    }
    $kv_metadata = isset($metadata['table-metadata']) ? $metadata['table-metadata'] : array();
    if ((!isset($kv_metadata['key-value-item'])) && (isset($metadata['field-labels']))) {
      $input = drush_select_output_fields($input, $metadata['field-labels'], $metadata['field-mappings']);
    }
    if (isset($metadata['include-field-labels'])) {
      $kv_metadata['include-field-labels'] = $metadata['include-field-labels'];
    }
    $formatted_table = drush_key_value_to_array_table($input, $kv_metadata);
    if ($formatted_table === FALSE) {
      return FALSE;
    }
    return drush_format_table($formatted_table, FALSE, array());
  }
}
