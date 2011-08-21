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
      'pipe' => 'Use var_export() to emit executable PHP. Useful for pasting into code.',
    ),
    'aliases' => array('vget'),
  );
  $items['variable-set'] = array(
    'description' => "Set a variable.",
    'arguments' => array(
      'name' => 'The name of a variable or the first few letters of its name.',
      'value' => 'The value to assign to the variable.',
    ),
    'options' => array(
      'yes' => 'Skip confirmation if only one variable name matches.',
      'always-set' => 'Always skip confirmation.',
    ),
    'examples' => array(
      'drush vset --yes preprocess_css 1' => 'Set the preprocess_css variable to true. Skip confirmation if variable already exists.',
      'drush vset --always-set site_offline 1' => 'Take the site offline; Skips confirmation even if site_offline variable does not exist.',
      'drush vset pr 1' => 'Choose from a list of variables beginning with "pr" to set to true.',
    ),
    'aliases' => array('vset'),
  );
  $items['variable-delete'] = array(
    'description' => "Delete a variable.",
    'arguments' => array(
      'name' => 'The name of a variable or the first few letters of its name.',
    ),
    'options' => array(
      'yes' => 'Skip confirmation if only one variable name matches.',
    ),
    'examples' => array(
      'drush vdel user_pictures' => 'Delete the user_pictures variable.',
      'drush vdel u' => 'Choose from a list of variables beginning with "u" to delete.',
    ),
    'aliases' => array('vdel'),
  );

  return $items;
}

/**
 * Command callback.
 * List your site's variables.
 */
function drush_variable_get() {
  global $conf;
  $found = FALSE;
  
  $keys = array_keys($conf);
  if ($args = func_get_args()) {
    $keys = preg_grep("/{$args[0]}/", $keys);
  }
  foreach ($keys as $name) {
    $value = $conf[$name];
    drush_print_pipe("\$variables['$name'] = ". var_export($value, TRUE). ";\n");

    if ($value === TRUE) {
      $value = 'TRUE';
    }
    elseif ($value === FALSE) {
      $value = 'FALSE';
    }
    elseif (is_string($value)) {
      $value = '"' . $value . '"';
    }
    elseif (is_array($value) || is_object($value)) {
      $value = print_r($value, TRUE);
    }
    drush_print($name . ': ' . $value);
    $found = TRUE;
  }
  
  if (!$found) {
    return drush_set_error('No matching variable found.');
  }
}

/**
 * Command callback.
 * Set a variable.
 */
function drush_variable_set() {
  $args = func_get_args();
  if (!isset($args[0])) {
    return drush_set_error('DRUSH_VARIABLE_ERROR', dt('No variable specified.'));
  }
  $value = $args[1];
  if (!isset($value)) {
    return drush_set_error('DRUSH_VARIABLE_ERROR', dt('No value specified.'));
  }

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

  if ((drush_get_option('always-set', FALSE)) || ((count($options) == 1 || $match) && drush_get_context('DRUSH_AFFIRMATIVE'))) {
    variable_set($args[0], $value);
    drush_log(dt('!name was set to !value.', array('!name' => $args[0], '!value' => $value)), 'success');
    return '';
  }
  else {
    $choice = drush_choice($options, 'Enter a number to choose which variable to set.');
    if ($choice !== FALSE) {
      $choice = $options[$choice];
      $choice = str_replace(' ' . dt('(new variable)'), '', $choice);
      drush_op('variable_set', $choice, $value);
      drush_log(dt('!name was set to !value', array('!name' => $choice, '!value' => $value)), 'success');
    }
  }
}

/**
 * Command callback.
 * Delete a variable.
 */
function drush_variable_delete() {
  $args = func_get_args();
  if (!isset($args[0])) {
    drush_set_error('DRUSH_VARIABLE_ERROR', dt('No variable specified'));
  }
  // Look for similar variable names.
  $result = drush_variable_like($args[0]);

  $options = array();
  while ($name = drush_db_result($result)) {
    $options[] = $name;
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
