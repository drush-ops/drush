<?php

/**
 * Output formatter 'list'
 *
 * @param $data
 *   The $data parameter is expected to be an array of key / value pairs.
 *   Each key / value pair is passed to some other output formatter for
 *   rendering; the key becomes the label, $metadata['label'], and the
 *   value becomes the $data for the sub-formatter.
 * @param $metadata
 *   'matches' - Specifies the exact kind of list to be rendered in an
 *     array of two elements.  $matches[0] is the full name of the
 *     list format (e.g. 'string-list'), and $matches[1] is the type
 *     of the sub-formatter (e.g. 'string').  If not specified, 'string'
 *     is assumed.
 *
 * Code:
 *
 *   return array('a', 'b', 'c');
 *
 * Output with --format=list: (list of string):
 *
 *   a
 *   b
 *   c
 */
class drush_outputformat_list extends drush_outputformat {
  function validate() {
    // Separate the 'list' and 'filter' metadata from everything
    // else in $engine_config['engine-info']
    $list_metadata = array();
    foreach ($this->engine_config as $key => $value) {
      if ((substr($key, 0, 4) == 'list') || (substr($key, -6) == 'filter') || (substr($key, 0, 5) == 'field') || ($key == 'options')) {
        unset($this->engine_config[$key]);
        $list_metadata[$key] = $value;
      }
    }
    foreach ($this->engine_config['engine-info'] as $key => $value) {
      if ((substr($key, 0, 4) == 'list') || (substr($key, -6) == 'filter')) {
        unset($this->engine_config['engine-info'][$key]);
        $list_metadata[$key] = $value;
      }
    }
    $sub_formatter = isset($list_metadata['list-item-type']) ? $list_metadata['list-item-type'] : 'string';
    $this->sub_engine = drush_load_engine('outputformat', $sub_formatter, NULL, NULL, $this->engine_config);
    $engine_info = $this->engine_config['engine-info'];
    $this->engine_config = array(
      'engine-info' => array(
        'machine-parsable' => $this->sub_engine->engine_config['engine-info']['machine-parsable'],
      ),
      'metameta' => $this->sub_engine->engine_config,
    ) + $list_metadata;
    if (!is_object($this->sub_engine)) {
      return FALSE;
    }
    return TRUE;
  }

  function format($input, $metadata) {
    $output = '';
    $separator = isset($metadata['list-separator']) ? $metadata['list-separator'] : "\n";
    if (is_array($input)) {
      // If this list is processing output from a command that produces table
      // output, but our subformatter only supports 'single' items (e.g. csv),
      // then we will convert our data such that the output will be the keys
      // of the table rows.
      if (($this->data_type($metadata) == 'format-table') && ($this->supports_single_only($metadata)) && !isset($metadata['list-item'])) {
        $input = array_keys($input);
      }
      $first = TRUE;
      foreach ($input as $label => $data) {
        if (isset($metadata['list-item'])) {
          $data = isset($data[$metadata['list-item']]) ? $data[$metadata['list-item']] : $metadata['list-item-default-value'];
        }
        if (empty($metadata['metameta'])) {
          $metameta = $metadata;
          unset($metameta['list-item']);
          unset($metameta['list-item-default-value']);
          $metameta['label'] = $label;
        }
        else {
          $metameta = $metadata['metameta'] + array('label' => $label);
        }
        foreach (array('field-labels') as $key) {
          if (isset($metadata[$key])) {
            $metameta[$key] = $metadata[$key];
          }
        }
        $formatted_item = $this->sub_engine->process($data, $metameta);
        if ($formatted_item === FALSE) {
          return FALSE;
        }
        if (!$first) {
          $output .= $separator;
        }
        if (($separator != "\n") && (strpos($formatted_item, $separator) !== FALSE)) {
          $formatted_item = drush_wrap_with_quotes($formatted_item);
        }
        $output .= $formatted_item;
        $first = FALSE;
      }
    }
    return $output;
  }
}
