<?php
// $Id$

/**
 * @file
 *   Example drush command.
 *
 *   Shows how to make your own drush command.
 *
 *   You can copy this file to any of the following
 *     1. A .drush folder in your HOME folder.
 *     2. Anywhere in a folder tree below an active module on your site.
 *     3. In an arbitrary folder specified with the --include option.
 */

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
 * @See drush_parse_command() for a list of recognized keys.
 *
 * @return
 *   An associative array describing your command(s).
 */
function example_drush_command() {
  $items = array();

  // the key in the $items array is the name of the command.
  $items['example'] = array(
    // the name of the function implementing your command.
    'callback' => 'example_callback',
    // a short description of your command
    'description' => "Drush example command. It doesn't do a lot",
  );

  // more commands here ...

  return $items;
}

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
function example_drush_help($section) {
  switch ($section) {
    case 'drush:example':
      return dt("Prints the amount of time since january 1st, 1970, in years and weeks.");
  }
}

/**
 * Example drush command callback.
 *
 * This is where the action takes place.
 *
 * In this function, all of Drupals API is (usually) available, including
 * any functions you have added in your own modules/themes.
 *
 * To print something to the terminal window, use drush_print().
 *
 */
function example_callback() {
  $args = func_get_args();

  // Using the Drupal API function 'format_interval()'
  // and Drush API function 'drush_print'
  drush_print(format_interval(time()));
}
