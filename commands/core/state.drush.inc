<?php

/**
 * @file
 *   Provides State commands.
 */

/**
 * Implementation of hook_drush_help().
 */
function state_drush_help($section) {
  switch ($section) {
    case 'meta:state:title':
      return dt('State commands');
    case 'meta:state:summary':
      return dt('Interact with the State system.');
  }
}

/**
 * Implementation of hook_drush_command().
 */
function state_drush_command() {
  $items['state-get'] = array(
    'description' => 'Display a state value.',
    'arguments' => array(
      'key' => 'The key name.',
    ),
    'required-arguments' => 1,
    'examples' => array(
      'drush state-get system.cron_last' => 'Displays last cron run timestamp',
    ),
    'outputformat' => array(
      'default' => 'json',
      'pipe-format' => 'json',
    ),
    'aliases' => array('sget'),
    'core' => array('8+'),
  );

  $items['state-set'] = array(
    'description' => 'Set a state value.',
    'arguments' => array(
      'key' => 'The state key, for example "system.cron_last".',
      'value' => 'The value to assign to the state key. Use \'-\' to read from STDIN.',
    ),
    'required arguments' => 2,
    'options' => array(
      'format' => array(
        'description' => 'Type for  the value. Use "auto" to detect format from value. Other recognized values are string, integer float, or boolean for corresponding primitive type, or json, yaml for complex types.',
        'example-value' => 'boolean',
        'value' => 'required',
      ),
      // A convenient way to pass a multiline value within a backend request.
      'value' => array(
        'description' => 'The value to assign to the state key (if any).',
        'hidden' => TRUE,
      ),
    ),
    'examples' => array(
      'drush state-set system.cron_last 1406682882 --format=integer' => 'Sets a timestamp for last cron run.',
      'php -r "print json_encode(array(\'drupal\', \'simpletest\'));"  | drush state-set --format=json foo.name -'=> 'Set a key to a complex value (e.g. array)',
    ),
    'aliases' => array('sset'),
    'core' => array('8+'),
  );

  $items['state-delete'] = array(
    'description' => 'Delete a state value.',
    'arguments' => array(
      'key' => 'The state key, for example "system.cron_last".',
    ),
    'required arguments' => 1,
    'examples' => array(
      'drush state-del system.cron_last' => 'Delete state entry for system.cron_last.',
    ),
    'aliases' => array('sdel'),
    'core' => array('8+'),
  );

  return $items;
}

/**
 * State get command callback.
 *
 * @state $key
 *   The state key.
 */
function drush_state_get($key = NULL) {
  return \Drupal::state()->get($key);
}

/**
 * State set command callback.
 *
 * @param $key
 *   The config key.
 * @param $value
 *    The data to save to state.
 */
function drush_state_set($key = NULL, $value = NULL) {
  // This hidden option is a convenient way to pass a value without passing a key.
  $value = drush_get_option('value', $value);

  if (!isset($value)) {
    return drush_set_error('DRUSH_STATE_ERROR', dt('No state value specified.'));
  }

  // Special flag indicating that the value has been passed via STDIN.
  if ($value === '-') {
    $value = stream_get_contents(STDIN);
  }

  // If the value is a string (usual case, unless we are called from code),
  // then format the input.
  if (is_string($value)) {
    $value = drush_value_format($value, drush_get_option('format', 'auto'));
  }

  \Drupal::state()->set($key, $value);
}

/**
 * State delete command callback.
 *
 * @state $key
 *   The state key.
 */
function drush_state_delete($key = NULL) {
  \Drupal::state()->delete($key);
}
