<?php
// $Id$

/**
 * Implementation of hook_drush_help().
 *
 * This function is called whenever a drush user calls
 * 'drush help <name-of-your-command>'
 *
 * @param
 *   A string with the help section (prepend with 'drush:')
 *
 * @return
 *   A string with the help text for your command.
 */
function variable_drush_help($section) {
  switch ($section) {
    case 'drush:variable get':
      return dt("Lists the variables for your site");
    case 'drush:variable set':
      return dt("Set a variable");
    case 'drush:variable delete':
      return dt("Delete a variable.");
  }
}


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
  $items['variable get'] = array(
    'description' => 'Get a list of some or all site variables and values.',
    'arguments' => array(
      'name' => 'A string to filter the variables by.  Only variables beginning with the string will be listed.',
    ),
    'examples' => array(
      'drush vget' => 'List all variables and values.',
      'drush vget user' => 'List all variables beginning with the string "user".',
    ),
    'aliases' => array('vget'),
  );
  $items['variable set'] = array(
    'description' => "Set a variable.",
    'arguments' => array(
      'name' => 'The name of a variable or the first few letters of its name.',
      'value' => 'The value to assign to the variable.',
    ),
    'options' => array(
      '--yes' => 'Skip confirmation if only one variable name matches.',
    ),
    'examples' => array(
      'drush vset --yes preprocess_css 1' => 'Set the preprocess_css variable to true. Skip confirmation.',
      'drush vset pr 1' => 'Choose from a list of variables beginning with "pr" to set to true.',
    ),
    'aliases' => array('vset'),
  );
  $items['variable delete'] = array(
    'description' => "Delete a variable.",
    'arguments' => array(
      'name' => 'The name of a variable or the first few letters of its name.',
    ),
    'options' => array(
      '--yes' => 'Skip confirmation if only one variable name matches.',
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
  $args = func_get_args();
  $placeholders = array();
  $sql = '';
  if (isset($args[0])) {
    $sql .= "WHERE name LIKE '%s%'";
    $placeholders[] = $args[0];
  }
  $result = db_query("SELECT * FROM {variable} ". $sql ." ORDER BY name ASC", $placeholders);
  while ($variable = db_fetch_object($result)) {
    $value = unserialize($variable->value);
    if ($value === TRUE) {
      $value = 'TRUE';
    }
    elseif ($value === FALSE) {
      $value = 'FALSE';
    }
    elseif (is_string($value)) {
      $value = '"' . $value . '"';
    }
    elseif (is_array($value)) {
      $value = $variable->value;
    }
    drush_print($variable->name . ': ' . $value);
  }
}

/**
 * Command callback.
 * Set a variable.
 */
function drush_variable_set() {
  $args = func_get_args();
  if (!isset($args[0])) {
    drush_set_error('DRUSH_VARIABLE_ERROR', dt('No variable specified.'));
  }
  // Look for similar variable names.
  $result = db_query("SELECT name FROM {variable} WHERE name LIKE '%s%'", $args[0]);
  $value = $args[1];
  if (!isset($value)) {
    drush_set_error('DRUSH_VARIABLE_ERROR', dt('No value specified.'));
  }
  $options = array();
  $options[] = dt('Cancel');
  $options[] = "$args[0] ". dt('(new variable)');
  while (!$match && $name = db_result($result)) {
    if ($name == $args[0]) {
      $options[1] = $name;
      $match = TRUE;
    }
    else {
      $options[] = $name;
    }
  }
  
  if ((count($options) == 2 || $match) && drush_get_context('DRUSH_AFFIRMATIVE')) {
    variable_set($args[0], $value);
    drush_log(dt('!name was set to !value.', array('!name' => $args[0], '!value' => $value)), 'success');
    return '';
  }
  else {
    foreach ($options as $key => $option) {
      print "[$key] : $option \n";
    }
    print dt("Enter a number to choose which variable to set.") . "\n";

    while ($line = trim(fgets(STDIN))) {
      if ($line == 1) {
        $choice = $args[0];
      }
      else {
        $choice = $options[$line];
      } 
      if ($choice) {
        drush_op('variable_set', $choice, $value);
        drush_log(dt('!name was set to !value', array('!name' => $choice, '!value' => $value)), 'success');
        return '';
      }
    }
    drush_print(dt('Cancelled'));
    return '';
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
  $result = db_query("SELECT name FROM {variable} WHERE name LIKE '%s%'", $args[0]);

  $options = array();
  $options[] = dt('Cancel');
  while ($name = db_result($result)) {
    $options[] = $name;
  }
  
  if (count($options) == 1) {
    drush_print(dt('!name not found.', array('!name' => $args[0])));
    return '';
  }
  
  if ((count($options) == 2) && drush_get_context('DRUSH_AFFIRMATIVE')) {
    drush_op('variable_del', $args[0]);
    drush_log(dt('!name was deleted.', array('!name' => $args[0])), 'success');
    return '';
  }
  else {
    foreach ($options as $key => $option) {
      print "[$key] : $option \n";
    }
    print dt("Enter a number to choose which variable to delete.") . "\n";

    while ($line = trim(fgets(STDIN))) {
      $choice = $options[$line];
      if ($choice) {
        drush_op('variable_del', $choice);
        drush_log(dt('!choice was deleted.', array('!choice' => $choice)), 'success');
        return '';
      }
    }
    drush_print(dt('Cancelled'));
    return '';
  }
}

