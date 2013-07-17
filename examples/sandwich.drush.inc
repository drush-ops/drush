<?php

/**
 * @file
 *   Example drush command.
 *
 *   To run this *fun* command, execute `sudo drush --include=./examples mmas`
 *   from within your drush directory.
 *
 *   See `drush topic docs-commands` for more information about command authoring.
 *
 *   You can copy this file to any of the following
 *     1. A .drush folder in your HOME folder.
 *     2. Anywhere in a folder tree below an active module on your site.
 *     3. /usr/share/drush/commands (configurable)
 *     4. In an arbitrary folder specified with the --include option.
 *     5. Drupal's /drush or /sites/all/drush folders.
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
 * See `drush topic docs-commands` for a list of recognized keys.
 *
 * @return
 *   An associative array describing your command(s).
 */
function sandwich_drush_command() {
  $items = array();

  // The 'make-me-a-sandwich' command
  $items['make-me-a-sandwich'] = array(
    'description' => "Makes a delicious sandwich.",
    'arguments' => array(
      'filling' => 'The type of the sandwich (turkey, cheese, etc.). Defaults to ascii.',
    ),
    'options' => array(
      'spreads' => array(
        'description' => 'Comma delimited list of spreads.',
        'example-value' => 'mayonnaise,mustard',
      ),
    ),
    'examples' => array(
      'drush mmas turkey --spreads=ketchup,mustard' => 'Make a terrible-tasting sandwich that is lacking in pickles.',
    ),
    'aliases' => array('mmas'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH, // No bootstrap at all.
  );

  // The 'sandwiches-served' command.  Informs how many 'mmas' commands completed.
  $items['sandwiches-served'] = array(
    'description' => "Report how many sandwiches we have made.",
    'examples' => array(
      'drush sandwiches-served' => 'Show how many sandwiches we have served.',
    ),
    'aliases' => array('sws'),
    // Example output engine data:  command returns a single keyed
    // data item (e.g. array("served" => 1)) that can either be
    // printed with a label (e.g. "served: 1"), or output raw with
    // --pipe (e.g. "1").
    'engines' => array(
      'outputformat' => array(
        'default' => 'key-value',
        'pipe-format' => 'string',
        'label' => 'Sandwiches Served',
        'require-engine-capability' => array('format-single'),
      ),
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH, // No bootstrap at all.
  );

  // The 'spreads-status' command.  Prints a table about available spreads.
  $items['spreads-status'] = array(
    'description' => "Show a table of information about available spreads.",
    'examples' => array(
      'drush spreads-status' => 'Show a table of spreads.',
    ),
    'aliases' => array('sps'),
    // Example output engine data:  command returns a deep array
    // that can either be printed in table format or as a json array.
    'engines' => array(
      'outputformat' => array(
        'default' => 'table',
        'pipe-format' => 'json',
        // Commands that return deep arrays will usually use
        // machine-ids for the column data.  A 'field-labels'
        // item maps from the machine-id to a human-readable label.
        'field-labels' => array(
          'name' => 'Name',
          'description' => 'Description',
          'available' => 'Num',
          'taste' => 'Taste',
        ),
        // In table format, the 'column-widths' item is consulted
        // to determine the default weights for any named column.
        'column-widths' => array(
          'name' => 10,
          'available' => 3,
        ),
        'require-engine-capability' => array('format-table'),
      ),
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH, // No bootstrap at all.
  );

  // Commandfiles may also add topics.  These will appear in
  // the list of topics when `drush topic` is executed.
  // To view this topic, run `drush --include=/full/path/to/examples topic`
  $items['sandwich-exposition'] = array(
    'description' => 'Ruminations on the true meaning and philosophy of sandwiches.',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'callback' => 'drush_print_file',
    'callback arguments' => array(dirname(__FILE__) . '/sandwich-topic.txt'),
  );

  return $items;
}



/**
 * Implementation of hook_drush_help().
 *
 * This function is called whenever a drush user calls
 * 'drush help <name-of-your-command>'. This hook is optional. If a command
 * does not implement this hook, the command's description is used instead.
 *
 * This hook is also used to look up help metadata, such as help
 * category title and summary.  See the comments below for a description.
 *
 * @param
 *   A string with the help section (prepend with 'drush:')
 *
 * @return
 *   A string with the help text for your command.
 */
function sandwich_drush_help($section) {
  switch ($section) {
    case 'drush:make-me-a-sandwich':
      return dt("This command will make you a delicious sandwich, just how you like it.");
    // The 'title' meta item is used to name a group of
    // commands in `drush help`.  If a title is not defined,
    // the default is "All commands in ___", with the
    // specific name of the commandfile (e.g. sandwich).
    // Command files with less than four commands will
    // be placed in the "Other commands" section, _unless_
    // they define a title.  It is therefore preferable
    // to not define a title unless the file defines a lot
    // of commands.
    case 'meta:sandwich:title':
      return dt("Sandwich commands");
    // The 'summary' meta item is displayed in `drush help --filter`,
    // and is used to give a general idea what the commands in this
    // command file do, and what they have in common.
    case 'meta:sandwich:summary':
      return dt("Automates your sandwich-making business workflows.");
  }
}

/**
 * Implementation of drush_hook_COMMAND_validate().
 *
 * The validate command should exit with
 * `return drush_set_error(...)` to stop execution of
 * the command.  In practice, calling drush_set_error
 * OR returning FALSE is sufficient.  See drush.api.php
 * for more details.
 */
function drush_sandwich_make_me_a_sandwich_validate() {
  if (drush_is_windows()) {
    // $name = drush_get_username();
    // TODO: implement check for elevated process using w32api
    // as sudo is not available for Windows
    // http://php.net/manual/en/book.w32api.php
    // http://social.msdn.microsoft.com/Forums/en/clr/thread/0957c58c-b30b-4972-a319-015df11b427d
  }
  else {
    $name = posix_getpwuid(posix_geteuid());
    if ($name['name'] !== 'root') {
      return drush_set_error('MAKE_IT_YOUSELF', dt('What? Make your own sandwich.'));
    }
  }
}

/**
 * Example drush command callback. This is where the action takes place.
 *
 * The function name should be same as command name but with dashes turned to
 * underscores and 'drush_commandfile_' prepended, where 'commandfile' is
 * taken from the file 'commandfile.drush.inc', which in this case is 'sandwich'.
 * Note also that a simplification step is also done in instances where
 * the commandfile name is the same as the beginning of the command name,
 * "drush_example_example_foo" is simplified to just "drush_example_foo".
 * To also implement a hook that is called before your command, implement
 * "drush_hook_pre_example_foo".  For a list of all available hooks for a
 * given command, run drush in --debug mode.
 *
 * If for some reason you do not want your hook function to be named
 * after your command, you may define a 'callback' item in your command
 * object that specifies the exact name of the function that should be
 * called.
 *
 * In this function, all of Drupal's API is (usually) available, including
 * any functions you have added in your own modules/themes.
 *
 * @see drush_invoke()
 * @see drush.api.php
 */
function drush_sandwich_make_me_a_sandwich($filling = 'ascii') {
  $str_spreads = '';
  // Read options with drush_get_option. Note that the options _must_
  // be documented in the $items structure for this command in the 'command' hook.
  // See `drush topic docs-commands` for more information.
  if ($spreads = drush_get_option('spreads')) {
    $list = implode(' and ', explode(',', $spreads));
    $str_spreads = ' with just a dash of ' . $list;
  }
  $msg = dt('Okay. Enjoy this !filling sandwich!str_spreads.',
            array('!filling' => $filling, '!str_spreads' => $str_spreads)
         );
  drush_print("\n" . $msg . "\n");

  if (drush_get_context('DRUSH_NOCOLOR')) {
    $filename = dirname(__FILE__) . '/sandwich-nocolor.txt';
  }
  else {
    $filename = dirname(__FILE__) . '/sandwich.txt';
  }
  drush_print(file_get_contents($filename));
  // Find out how many sandwiches have been served, and set
  // the cached value to one greater.
  $served = drush_sandwich_sandwiches_served();
  drush_cache_set(drush_get_cid('sandwiches-served'), $served + 1);
}

/**
 * Implementation of hook_drush_command() for sandwiches-served command.
 *
 * Demonstrates how to return a simple value that is transformed by
 * the selected formatter to display either with a label (using the
 * key-value formatter) or as the raw value itself (using the string formatter).
 */
function drush_sandwich_sandwiches_served() {
  $served = 0;
  $served_object = drush_cache_get(drush_get_cid('sandwiches-served'));
  if ($served_object) {
    $served = $served_object->data;
  }
  // In the default format, key-value, this return value
  // will print " Sandwiches Served    :  1".  In the default pipe
  // format, only the array value ("1") is returned.
  return $served;
}

/**
 * Implementation of hook_drush_command() for spreads-status command.
 *
 * This ficticious command shows how a deep array can be constructed
 * and used as a command return value that can be output by different
 * output formatters.
 */
function drush_sandwich_spreads_status() {
  return array(
    'ketchup' => array(
      'name' => 'Ketchup',
      'description' => 'Some say its a vegetable, but we know its a sweet spread.',
      'available' => '7',
      'taste' => 'sweet',
    ),
    'mayonnaise' => array(
      'name' => 'Mayonnaise',
      'description' => 'A nice dairy-free spead.',
      'available' => '12',
      'taste' => 'creamy',
    ),
    'mustard' => array(
      'name' => 'Mustard',
      'description' => 'Pardon me, but could you please pass that plastic yellow bottle?',
      'available' => '8',
      'taste' => 'tangy',
    ),
    'pickles' => array(
      'name' => 'Pickles',
      'description' => 'A necessary part of any sandwich that does not taste terrible.',
      'available' => '63',
      'taste' => 'tasty',
    ),
  );
}

/**
 * Command argument complete callback. Provides argument
 * values for shell completion.
 *
 * @return
 *  Array of popular fillings.
 */
function sandwich_make_me_a_sandwich_complete() {
  return array('values' => array('turkey', 'cheese', 'jelly', 'butter'));
}
