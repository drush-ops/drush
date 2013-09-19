<?php

/**
 * @file
 *   Core drush output formats.
 */

/**
 * @return drush_outputformat The selected output format engine
 */
function drush_get_outputformat() {
  return drush_get_engine('outputformat');
}

/**
 * Dynamically switch to a new output format.  Does NOT override
 * user-selected output format.
 */
function drush_set_default_outputformat($format, $metadata = array()) {
  $command = drush_get_command();
  $command['engines']['outputformat']['default'] = $format;
  $outputformat = drush_load_command_engine($command, 'outputformat', $metadata);
}

/**
 * Given a command name or a command record, return the
 * command formatter that is used to process that command's output.
 */
function drush_get_command_format_metadata($command, $metadata = array()) {
  $commands = drush_get_commands();
  if (!is_array($command) && array_key_exists($command, $commands)) {
    $command = $commands[$command];
  }
  return drush_get_command_engine_config($command, 'outputformat', $metadata);
}

/**
 * Implementation of hook_drush_engine_type_info().
 */
function outputformat_drush_engine_type_info() {
  $info = array();
  $info['outputformat'] = array(
    'description' => 'Output formatting options selection and use.',
    'topic' => 'docs-output-formats',
    'topic-file' => 'docs/output-formats.html',
    'combine-help' => 'TRUE',
    'option' => 'format',
    'options' => array(
      'format' => array(
        'description' => 'Select output format.',
        'example-value' => 'json',
      ),
      'fields' => array(
        'description' => 'Fields to output.',
        'example-value' => 'field1,field2',
        'value' => 'required',
        'list' => TRUE,
      ),
      'list-separator' => array(
        'description' => 'Specify how elements in a list should be separated. In lists of lists, this applies to the elements in the inner lists.',
        'hidden' => TRUE,
      ),
      'line-separator' => array(
        'description' => 'In nested lists of lists, specify how the outer lists ("lines") should be separated.',
        'hidden' => TRUE,
      ),
      'field-labels' => array(
        'description' => 'Add field labels before first line of data. Default is on; --field-labels=0 to disable.',
        'default' => '1',
        'key' => 'include-field-labels',
      ),
    ),
    // Allow output formats to declare their
    // "output data type" instead of their
    // "required engine capability" for readability.
    'config-aliases' => array(
      'output-data-type' => 'require-engine-capability',
    ),
  );
  return $info;
}

/**
 * Implements hook_drush_engine_ENGINE_TYPE().
 *
 * The output format types supported are represented by
 * the 'engine-capabilities' of the output format engine.
 * The different capabilities include:
 *
 * format-single:       A simple string.
 *
 * format-list:         An associative array where the key
 *                      is usually the row label, and the value
 *                      is a simple string.  Some list formatters
 *                      render the label, and others (like
 *                      "list" and "csv") throw it away.
 *
 * format-table:        An associative array, where the key
 *                      is the row id, and the value is the
 *                      column data.  The column data is also
 *                      an associative array where the key
 *                      is the column id and the value is the
 *                      cell data.  The cell data should usually
 *                      be a simple string; however, some
 *                      formatters can recursively format their
 *                      cell contents before rendering (e.g. if
 *                      a cell contains a list of items in an array).
 *
 * These definitions align with the declared 'output-data-type'
 * declared in command records.  @see drush_parse_command().
 *
 * Any output format that does not declare any engine capabilities
 * is expected to be able to render any php data structure that is
 * passed to it.
 */
function outputformat_drush_engine_outputformat() {
  $common_topic_example = array(
    "a" => array("b" => 2, "c" => 3),
    "d" => array("e" => 5, "f" => 6)
  );

  $engines = array();
  $engines['table'] = array(
    'description' => 'A formatted, word-wrapped table.',
    'engine-capabilities' => array('format-table'),
  );
  $engines['key-value'] = array(
    'description' => 'A formatted list of key-value pairs.',
    'engine-capabilities' => array('format-single', 'format-list', 'format-table'),
    'hidden' => TRUE,
  );
  $engines['key-value-list'] = array(
    'engine-class' => 'list',
    'list-item-type' => 'key-value',
    'description' => 'A list of formatted lists of key-value pairs.',
    'list-field-selection-control' => 1,
    'engine-capabilities' => array('format-table'),
    'hidden' => TRUE,
  );
  $engines['json'] = array(
    'machine-parsable' => TRUE,
    'description' => 'Javascript Object Notation.',
    'topic-example' => $common_topic_example,
  );
  $engines['string'] = array(
    'machine-parsable' => TRUE,
    'description' => 'A simple string.',
    'engine-capabilities' => array('format-single'),
  );
  $engines['message'] = array(
    'machine-parsable' => FALSE, // depends on the label....
    'hidden' => TRUE,
  );
  $engines['print-r'] = array(
    'machine-parsable' => TRUE,
    'description' => 'Output via php print_r function.',
    'verbose-only' => TRUE,
    'topic-example' => $common_topic_example,
  );
  $engines['var_export'] = array(
    'machine-parsable' => TRUE,
    'description' => 'An array in executable php format.',
    'topic-example' => $common_topic_example,
  );
  $engines['yaml'] = array(
    'machine-parsable' => TRUE,
    'description' => 'Yaml output format.',
    'topic-example' => $common_topic_example,
  );
  $engines['php'] = array(
    'machine-parsable' => TRUE,
    'description' => 'A serialized php string.',
    'verbose-only' => TRUE,
    'topic-example' => $common_topic_example,
  );
  $engines['config'] = array(
    'machine-parsable' => TRUE,
    'engine-class' => 'list',
    'list-item-type' => 'var_export',
    'description' => "A configuration file in executable php format. The variable name is \"config\", and the variable keys are taken from the output data array's keys.",
    'metadata' => array(
      'variable-name' => 'config',
    ),
    'list-field-selection-control' => -1,
    'engine-capabilities' => array('format-list','format-table'),
    'verbose-only' => TRUE,
  );
  $engines['list'] = array(
    'machine-parsable' => TRUE,
    'list-item-type' => 'string',
    'description' => 'A simple list of values.',
    // When a table is printed as a list, only the array keys of the rows will print.
    'engine-capabilities' => array('format-list', 'format-table'),
    'topic-example' => array('a', 'b', 'c'),
  );
  $engines['nested-csv'] = array(
    'machine-parsable' => TRUE,
    'engine-class' => 'list',
    'list-separator' => ',',
    'list-item-type' => 'csv-or-string',
    'hidden' => TRUE,
  );
  $engines['csv-or-string'] = array(
    'machine-parsable' => TRUE,
    'hidden' => TRUE,
  );
  $engines['csv'] = array(
    'machine-parsable' => TRUE,
    'engine-class' => 'list',
    'list-item-type' => 'nested-csv',
    'labeled-list' => TRUE,
    'description' => 'A list of values, one per row, each of which is a comma-separated list of values.',
    'engine-capabilities' => array('format-table'),
    'topic-example' => array(array('a', 12, 'a@one.com'),array('b', 17, 'b@two.com')),
  );
  $engines['variables'] = array(
    'machine-parsable' => TRUE,
    'description' => 'A list of php variable assignments.',
    'engine-capabilities' => array('format-table'),
    'verbose-only' => TRUE,
    'list-field-selection-control' => -1,
    'topic-example' => $common_topic_example,
  );
  $engines['labeled-export'] = array(
    'machine-parsable' => TRUE,
    'description' => 'A list of php exports, labeled with a name.',
    'engine-capabilities' => array('format-table'),
    'verbose-only' => TRUE,
    'engine-class' => 'list',
    'list-item-type' => 'var_export',
    'metadata' => array(
      'label-template' => '!label: !value',
    ),
    'list-field-selection-control' => -1,
    'topic-example' => $common_topic_example,
  );
  return $engines;
}

/**
 * Implements hook_drush_command_alter
 */
function outputformat_drush_command_alter(&$command) {
  // In --pipe mode, change the default format to the default pipe format, or
  // to json, if no default pipe format is given.
  if (drush_get_context('DRUSH_PIPE') && (isset($command['engines']['outputformat']))) {
    $default_format = isset($command['engines']['outputformat']['pipe-format']) ? $command['engines']['outputformat']['pipe-format'] : 'json';
    $command['engines']['outputformat']['default'] = $default_format;
  }
}

/**
 * Implements hook_drush_help_alter().
 */
function outputformat_drush_help_alter(&$command) {
  if (isset($command['engines']['outputformat'])) {
    $outputformat = $command['engines']['outputformat'];
    // If the command defines specific field labels,
    // then modify the help for --fields to include
    // specific information about the available fields.
    if (isset($outputformat['field-labels'])) {
      $all_fields = array();
      $all_fields_description = array();
      foreach ($outputformat['field-labels'] as $field => $human_readable) {
        $all_fields[] = $field;
        if ((strtolower($field) != strtolower($human_readable)) && !array_key_exists(strtolower($human_readable), $outputformat['field-labels'])) {
          $all_fields_description[] = $field . dt(" (or '!other')", array('!other' => strtolower($human_readable)));
        }
        else {
          $all_fields_description[] = $field;
        }
      }
      $field_defaults = isset($outputformat['fields-default']) ? $outputformat['fields-default'] : $all_fields;
      $command['options']['fields']['example-value'] = implode(', ', $field_defaults);
      $command['options']['fields']['description'] .= ' '. dt('All available fields are: !fields.', array('!fields' => implode(', ', $all_fields_description)));
      if (isset($outputformat['fields-default'])) {
        $command['options']['full']['description'] = dt("Show the full output, with all fields included.");
      }
    }
    else {
      // If the command does not define specific field labels,
      // then hide the help for --fields unless the command
      // uses output format engines that format tables.
      if (isset($outputformat['require-engine-capability']) && is_array($outputformat['require-engine-capability'])) {
        if (!in_array('format-table', $outputformat['require-engine-capability'])) {
          unset($command['options']['fields']);
          unset($command['options']['field-labels']);
        }
      }
      // If the command does define output formats, but does not
      // define fields, then just hide the help for the --fields option.
      else {
        $command['options']['fields']['hidden'] = TRUE;
        $command['options']['field-labels']['hidden'] = TRUE;
      }
    }

    // If the command defines a default pipe format, then
    // add '--pipe   Equivalent to --format=<pipe-default>'.
    if (isset($outputformat['pipe-format'])) {
      if (isset($command['options']['pipe'])) {
        $command['options']['pipe'] .= ' ';
      }
      else {
        $command['options']['pipe'] = '';
      }
      if (isset($outputformat['pipe-metadata']['message-template'])) {
        $command['options']['pipe'] .= dt('Displays output in the form "!message"', array('!message' => $outputformat['pipe-metadata']['message-template']));
      }
      else {
        $command['options']['pipe'] .= dt("Equivalent to --format=!default.", array('!default' => $outputformat['pipe-format']));
      }
    }
  }
}

/**
 * Implements hook_drush_engine_topic_additional_text().
 */
function outputformat_drush_engine_topic_additional_text($engine, $instance, $config) {
  $result = array();

  // If the output format engine has a 'topic-example' in
  // its configuration, then format the provided array using
  // the output formatter, and insert the result of the
  // transform into the topic text.
  if ($engine == 'outputformat') {
    if (array_key_exists('topic-example', $config)) {
      $code = $config['topic-example'];
      $formatted = drush_format($code, array(), $instance);
      $result[] = dt("Code:\n\nreturn !code;\n\nOutput with --format=!instance:\n\n!formatted", array('!code' => var_export($code, TRUE), '!instance' => $instance, '!formatted' => $formatted));
    }
  }

  return $result;
}

/**
 * Interface for output format engines.
 */
class drush_outputformat {
  function __construct($config) {
    $config += array(
      'column-widths' => array(),
      'field-mappings' => array(),
      'engine-info' => array(),
    );
    $config['engine-info'] += array(
      'machine-parsable' => FALSE,
      'metadata' => array(),
    );
    $config += $config['engine-info']['metadata'];
    $this->engine_config = $config;
  }
  function format_error($message) {
    return drush_set_error('DRUSH_FORMAT_ERROR', dt("The output data could not be processed by the selected format '!type'.  !message", array('!type' => $this->selected_engine, '!message' => $message)));
  }
  function formatter_type() {
    return $this->engine;
  }
  function is_list() {
    return FALSE;
  }
  function formatter_is_simple_list() {
    if (!isset($this->sub_engine)) {
      return false;
    }
    return ($this->formatter_type() == 'list') && ($this->sub_engine->supports_single_only());
  }
  function data_type($metadata) {
    if (isset($metadata['metameta']['require-engine-capability']) && is_array($metadata['metameta']['require-engine-capability'])) {
      return  $metadata['metameta']['require-engine-capability'][0];
    }
    if (isset($metadata['require-engine-capability']) && is_array($metadata['require-engine-capability'])) {
      return  $metadata['require-engine-capability'][0];
    }
    return 'unspecified';
  }
  function supported_data_types($metadata = NULL) {
    if ($metadata == NULL) {
      $metadata = $this->engine_config;
    }
    if (isset($metadata['metameta']['engine-info']['engine-capabilities'])) {
      return  $metadata['metameta']['engine-info']['engine-capabilities'];
    }
    if (isset($metadata['engine-info']['engine-capabilities'])) {
      return  $metadata['engine-info']['engine-capabilities'];
    }
    return array();
  }
  function supports_single_only($metadata = NULL) {
    $supported = $this->supported_data_types($metadata);
    return (count($supported) == 1) && ($supported[0] == 'format-single');
  }
  function get_info($key) {
    if (array_key_exists($key, $this->engine_config)) {
      return $this->engine_config[$key];
    }
    elseif (isset($this->sub_engine)) {
      return $this->sub_engine->get_info($key);
    }
    return FALSE;
  }
  /**
   * Perform pre-processing and then format() the $input.
   */
  function process($input, $metadata = array()) {
    $metadata = array_merge_recursive($metadata, $this->engine_config);
    if (isset($metadata['private-fields']) && is_array($input)) {
      if (!drush_get_option('show-passwords', FALSE)) {
        if (!is_array($metadata['private-fields'])) {
          $metadata['private-fields'] = array($metadata['private-fields']);
        }
        foreach ($metadata['private-fields'] as $private) {
          drush_unset_recursive($input, $private);
        }
      }
    }
    if (isset($metadata[$this->selected_engine . '-metadata'])) {
      $engine_specific_metadata = $metadata[$this->selected_engine . '-metadata'];
      unset($metadata[$this->selected_engine . '-metadata']);
      $metadata = array_merge($metadata, $engine_specific_metadata);
    }
    if ((drush_get_context('DRUSH_PIPE')) && (isset($metadata['pipe-metadata']))) {
      $pipe_specific_metadata = $metadata['pipe-metadata'];
      unset($metadata['pipe-metadata']);
      $metadata = array_merge($metadata, $pipe_specific_metadata);
    }
    $machine_parsable = $this->engine_config['engine-info']['machine-parsable'];
    $formatter_type = $machine_parsable ? 'parsable' : 'formatted';
    if ((!$machine_parsable) && is_bool($input)) {
      $input = $input ? 'TRUE' : 'FALSE';
    }

    // Run $input through any filters that are specified for this formatter.
    if (isset($metadata[$formatter_type . '-filter'])) {
      $filters = $metadata[$formatter_type . '-filter'];
      if (!is_array($filters)) {
        $filters = array($filters);
      }
      foreach ($filters as $filter) {
        if (function_exists($filter)) {
          $input = $filter($input, $metadata);
        }
      }
    }
    if (isset($metadata['field-labels'])) {
      foreach (drush_hide_output_fields() as $hidden_field) {
        unset($metadata['field-labels'][$hidden_field]);
      }
    }
    return $this->format($input, $metadata);
  }
  function format($input, $metadata) {
    return $input;
  }
}

/**
 * Specify that certain fields should not appear in the resulting output.
 */
function drush_hide_output_fields($fields_to_hide = array()) {
  $already_hidden = drush_get_context('DRUSH_HIDDEN_OUTPUT_FIELDS');
  if (!is_array($fields_to_hide)) {
    $fields_to_hide = array($fields_to_hide);
  }
  $result = array_merge($already_hidden, $fields_to_hide);
  drush_set_context('DRUSH_HIDDEN_OUTPUT_FIELDS', $result);
  return $result;
}
