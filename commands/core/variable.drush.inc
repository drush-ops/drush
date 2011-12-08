<?php

/**
 * Implementation of hook_drush_command().
 *
 * In this hook, you specify which commands your
 * drush module makes available, what it does and
 * description.
 *
 * Notice how this structure closely resembles how
 * you define menu hooks.
 *
 * @return
 *   An associative array describing your command(s).
 */
function variable_drush_command() {
  $items['variable-get'] = array(
    'description' => 'Get a list of some or all site variables and values.',
    'arguments' => array(
      'name' => 'A string to filter the variables by.  Only variables beginning with the string will be listed.',
    ),
    'examples' => array(
      'drush vget' => 'List all variables and values.',
      'drush vget user' => 'List all variables beginning with the string "user".',
    ),
    'options' => array(
      'format' => 'Format to output the object. Use "print_r" for print_r (default), "export" for var_export, and "json" for JSON.',
      'pipe' => 'A synonym for --format=export. Useful for pasting into code.',
    ),
    'aliases' => array('vget'),
  );
  $items['variable-set'] = array(
    'description' => "Set a variable.",
    'arguments' => array(
      'name' => 'The name of a variable or the first few letters of its name.',
      'value' => 'The value to assign to the variable. Use \'-\' to read the object from STDIN.',
    ),
    'required-arguments' => TRUE,
    'options' => array(
      'yes' => 'Skip confirmation if only one variable name matches.',
      'always-set' => 'Always skip confirmation.',
      'format' => 'Format to parse the object. Use "auto" to detect format from value (default), "string", "integer" or "boolean" for corresponding primitive type, and "json" for JSON.',
    ),
    'examples' => array(
      'drush vset --yes preprocess_css TRUE' => 'Set the preprocess_css variable to true. Skip confirmation if variable already exists.',
      'drush vset --always-set maintenance_mode 1' => 'Take the site offline; skips confirmation even if maintenance_mode variable does not exist. Variable is rewritten to site_offline for Drupal 6.',
      'drush vset pr TRUE' => 'Choose from a list of variables beginning with "pr" to set to (bool)true.',
      'php -r "print json_encode(array(\'drupal\', \'simpletest\'));"  | drush vset --format=json project_dependency_excluded_dependencies -'=> 'Set a variable to a complex value (e.g. array)',
    ),
    'aliases' => array('vset'),
  );
  $items['variable-delete'] = array(
    'description' => "Delete a variable.",
    'arguments' => array(
      'name' => 'The name of a variable or the first few letters of its name.',
    ),
    'required-arguments' => TRUE,
    'options' => array(
      'yes' => 'Skip confirmation if only one variable name matches.',
      'exact' => 'Only delete the one variable that exactly matches the specified name.',
    ),
    'examples' => array(
      'drush vdel user_pictures' => 'Delete the user_pictures variable.',
      'drush vdel u' => 'Choose from a list of variables beginning with "u" to delete.',
      'drush vdel -y --exact maintenance_mode' => 'Bring the site back online, skipping confirmation. Variable is rewritten to site_offline for Drupal 6.',
    ),
    'aliases' => array('vdel'),
  );

  return $items;
}

/**
 * Command argument complete callback.
 */
function variable_variable_get_complete() {
  return variable_complete_variables();
}

/**
 * Command argument complete callback.
 */
function variable_variable_set_complete() {
  return variable_complete_variables();
}

/**
 * Command argument complete callback.
 */
function variable_variable_delete_complete() {
  return variable_complete_variables();
}

/**
 * List variables for completion.
 *
 * @return
 *  Array of available variables.
 */
function variable_complete_variables() {
  if (drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_FULL)) {
    global $conf;
    return array('values' => array_keys($conf));
  }
}

/**
 * Command callback.
 * List your site's variables.
 */
function drush_variable_get() {
  global $conf;
  $output = NULL;

  $keys = array_keys($conf);
  if ($args = func_get_args()) {
    $args[0] = drush_variable_name_adjust($args[0]);
    $keys = preg_grep("/{$args[0]}/", $keys);
  }

  foreach ($keys as $name) {
    $value = $conf[$name];
    drush_print_pipe(drush_format($value, $name, 'export'));
    drush_print(drush_format($value, $name));
    $returns[$name] = $value;
  }

  if (empty($keys)) {
    return drush_set_error('No matching variable found.');
  }
  else {
    return $returns;
  }
}

/**
 * Command callback.
 * Set a variable.
 */
function drush_variable_set() {
  $args = func_get_args();
  $value = $args[1];
  if (!isset($value)) {
    return drush_set_error('DRUSH_VARIABLE_ERROR', dt('No value specified.'));
  }

  $args[0] = drush_variable_name_adjust($args[0]);
  $result = drush_variable_like($args[0]);

  $options[] = "$args[0] ". dt('(new variable)');
  $match = FALSE;
  while (!$match && $name = drush_db_result($result)) {
    if ($name == $args[0]) {
      $options[0] = $name;
      $match = TRUE;
    }
    else {
      $options[] = $name;
    }
  }

  if ($value == '-') {
    $value = stream_get_contents(STDIN);
  }

  // If the value is a string (usual case, unless we are called from code),
  // then format the input
  if (is_string($value)) {
    $value = _drush_variable_format($value, drush_get_option('format', 'auto'));
  }

  // Format the output for display
  if (is_array($value)) {
    $display = "\n" . var_export($value, TRUE);
  }
  elseif (is_integer($value)) {
    $display = $value;
  }
  elseif (is_bool($value)) {
    $display = $value ? "TRUE" : "FALSE";
  }
  else {
    $display = '"' . $value . '"';
  }

  if (drush_get_option('always-set', FALSE) || count($options) == 1 || $match) {
    variable_set($args[0], $value);
    drush_log(dt('!name was set to !value.', array('!name' => $args[0], '!value' => $display)), 'success');
    return '';
  }
  else {
    $choice = drush_choice($options, 'Enter a number to choose which variable to set.');
    if ($choice !== FALSE) {
      $choice = $options[$choice];
      $choice = str_replace(' ' . dt('(new variable)'), '', $choice);
      drush_op('variable_set', $choice, $value);
      drush_log(dt('!name was set to !value', array('!name' => $choice, '!value' => $display)), 'success');
    }
  }
}

function _drush_variable_format($value, $format) {
  if ($format == 'auto') {
    if (is_numeric($value)) {
      $format = 'integer';
    }
    elseif (($value == 'TRUE') || ($value == 'FALSE')) {
      $format = 'bool';
    }
  }

  // Now, we parse the object.
  switch ($format) {
    case 'integer':
      $value = (integer)$value;
      break;

    case 'bool':
    case 'boolean':
      if ($value == 'TRUE') {
        $value = TRUE;
      }
      elseif ($value == 'FALSE') {
        $value = FALSE;
      }
      else {
        $value = (bool)$value;
      }
      break;

    case 'json':
      $value = drush_json_decode($value);
      break;
  }
  return $value;
}

/**
 * Command callback.
 * Delete a variable.
 */
function drush_variable_delete() {
  $args = func_get_args();
  $args[0] = drush_variable_name_adjust($args[0]);
  // Look for similar variable names.
  $result = drush_variable_like($args[0]);

  $options = array();
  while ($name = drush_db_result($result)) {
    $options[] = $name;
  }
  if (drush_get_option('exact', FALSE)) {
    $options = in_array($args[0], $options) ? array($args[0]) : array();
  }

  if (count($options) == 0) {
    drush_print(dt('!name not found.', array('!name' => $args[0])));
    return '';
  }

  if ((count($options) == 1) && drush_get_context('DRUSH_AFFIRMATIVE')) {
    drush_op('variable_del', $args[0]);
    drush_log(dt('!name was deleted.', array('!name' => $args[0])), 'success');
    return '';
  }
  else {
    $choice = drush_choice($options, 'Enter a number to choose which variable to delete.');
    if ($choice !== FALSE) {
      $choice = $options[$choice];
      drush_op('variable_del', $choice);
      drush_log(dt('!choice was deleted.', array('!choice' => $choice)), 'success');
    }
  }
}

// Query for similar variable names.
function drush_variable_like($arg) {
  return drush_db_select('variable', 'name', 'name LIKE :keyword', array(':keyword' => $arg . '%'), NULL, NULL, 'name');
}

// Unify similar variable names across different versions of Drupal
function drush_variable_name_adjust($arg) {
  if (($arg == 'maintenance_mode') && (drush_drupal_major_version() < 7)) {
    $arg = 'site_offline';
  }
  if (($arg == 'site_offline') && (drush_drupal_major_version() >= 7)) {
    $arg = 'maintenance_mode';
  }
  return $arg;
}
