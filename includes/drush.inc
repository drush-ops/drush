<?php
// $Id$

/**
 * @file
 * The drush API implementation and helpers.
 */

/**
* @name Error status definitions
* @{
* Error code definitions for interpreting the current error status.
* @see drush_set_error(), drush_get_error(), drush_get_error_log(), drush_cmp_error()
*/

/** The command completed successfully. */
define('DRUSH_SUCCESS', 0);
/** The command could not be completed because the framework has specified errors that have occured. */
define('DRUSH_FRAMEWORK_ERROR', 1);
/** The command that was executed resulted in an application error,
  The most commom causes for this is invalid PHP or a broken SSH
  pipe when using drush_backend_invoke in a distributed manner. */
define('DRUSH_APPLICATION_ERROR', 255);

/**
 * @} End of "name Error status defintions".
 */

/**
 * The number of bytes in a kilobyte. Copied from Drupal.
 */
define('DRUSH_DRUPAL_KILOBYTE', 1024);

/**
 * Include a file, selecting a version specific file if available.
 *
 * For example, if you pass the path "/var/drush" and the name
 * "update" when bootstrapped on a Drupal 6 site it will first check for
 * the presence of "/var/drush/update_6.inc" in include it if exists. If this
 * file does NOT exist it will proceed and check for "/var/drush/update.inc".
 * If neither file exists, it will return FALSE.
 *
 * @param $path
 *   The path you want to search.
 * @param $name
 *   The file base name you want to include (not including a version suffix
 *   or extension).
 * @param $version
 *   The version suffix you want to include (could be specific to the software
 *   or platform your are connecting to) - defaults to the current Drupal core
 *   major version.
 * @param $extension
 *   The extension - defaults to ".inc".
 *
 * @return
 *   TRUE if the file was found and included.
 */
function drush_include($path, $name, $version = NULL, $extension = 'inc') {
  $version = ($version) ? $version : drush_drupal_major_version();
  $file = sprintf("%s/%s_%s.%s", $path, $name, $version, $extension);
  if (file_exists($file)) {
    // drush_log(dt('Including version specific file : @file', array('@file' => $file)));
    include_once($file);
    return TRUE;
  }
  $file = sprintf("%s/%s.%s", $path, $name, $extension);
  if (file_exists($file)) {
    // drush_log(dt('Including non-version specific file : @file', array('@file' => $file)));
    include_once($file);
    return TRUE;
  }
}

/**
 * Return a structured array of engines of a specific type from commandfiles
 * implementing hook_drush_engine_$type.
 *
 * Engines are pluggable subsystems. Each engine of a specific type will
 * implement the same set of API functions and perform the same high-level
 * task using a different backend or approach.
 *
 * This function/hook is useful when you have a selection of several mutually
 * exclusive options to present to a user to select from.
 *
 * Other commands are able to extend this list and provide their own engines.
 * The hook can return useful information to help users decide which engine
 * they need, such as description or list of available engine options.
 *
 * The engine path element will automatically default to a subdirectory (within
 * the directory of the commandfile that implemented the hook) with the name of
 * the type of engine - e.g. an engine "wget" of type "handler" provided by
 * the "pm" commandfile would automatically be found if the file
 * "pm/handler/wget.inc" exists and a specific path is not provided.
 *
 * @param $type
 *   The type of engine.
 *
 * @return
 *   A structured array of engines.
 */
function drush_get_engines($type) {
  $engines = array();
  $list = drush_commandfile_list();
  foreach ($list as $commandfile => $path) {
    if (drush_command_hook($commandfile, 'drush_engine_' . $type)) {
      $function = $commandfile . '_drush_engine_' . $type;
      $result = $function();
      foreach ((array)$result as $key => $engine) {
        // Add some defaults
        $engine += array(
          'commandfile' => $commandfile,
          // Engines by default live in a subdirectory of the commandfile that
          // declared them, named as per the type of engine they are.
          'path' => sprintf("%s/%s", dirname($path), $type),
        );
        $engines[$key] = $engine;
      }
    }
  }
  return $engines;
}

/**
 * Include the engine code for a specific named engine of a certain type.
 *
 * If the engine type has implemented hook_drush_engine_$type the path to the
 * engine specified in the array will be used.
 *
 * If you don't need to present any user options for selecting the engine
 * (which is common if the selection is implied by the running environment)
 * and you don't need to allow other modules to define their own engines you can
 * simply pass the $path to the directory where the engines are, and the
 * appropriate one will be included.
 *
 * Unlike drush_include this function will set errors if the requested engine
 * cannot be found.
 *
 * @param $type
 *   The type of engine.
 * @param $engine
 *   The key for the engine to be included.
 * @param $version
 *   The version of the engine to be included - defaults to the current Drupal core
 *   major version.
 * @param $path
 *   A path to include from, if the engine has no corresponding
 *   hook_drush_engine_$type item path.
 * @return unknown_type
 */
function drush_include_engine($type, $engine, $version = NULL, $path = NULL) {
  $engines = drush_get_engines($type);
  if (!$path && isset($engines[$engine])) {
    $path = $engines[$engine]['path'];
  }
  if (!$path) {
    return drush_set_error('DRUSH_ENGINE INCLUDE_NO_PATH', dt('No !path was set for including the !type engine !engine.', array('!path' => $path, '!type' => $type, '!engine' => $engine)));
  }
  if (drush_include($path, $engine, $version)) {
    return TRUE;
  }
  return drush_set_error('DRUSH_ENGINE INCLUDE_FAILED', dt('Unable to include the !type engine !engine from !path.' , array('!path' => $path, '!type' => $type, '!engine' => $engine)));
}

/**
 * Install a newer version of drush, if available
 *
 * @param $explicit
 *   TRUE if called explicitly via the 'drush self-update'
 *   command; FALSE if called implicitly via 'drush --version'
 *   or 'drush pm-updatecode'.  When called via the self-update
 *   command, errors are reported via drush_set_error, and a dev
 *   release can be upgraded to a newer dev release.  If FALSE,
 *   then errors cause self-update to exit silently, and only
 *   stable releases are considered (unless the --dev flag is
 *   specified).  We do not want to nag users to upgrade to
 *   every single available dev release unless they explicitly
 *   ask.
 *
 * @param $update
 *   TRUE if this function is allowed to update drush; if FALSE,
 *   it will inform the user of available updates, but will take
 *   no action.
 *
 * @return
 *   TRUE - installed new version of drush (or new version is available, if $update === FALSE)
 *   FALSE - user cancelled, or error
 *   NULL - no action taken (e.g. no release available)
 */
function drush_self_update($explicit = TRUE, $update = TRUE) {
  $error = "";

  // Do nothing unless we can write to the drush install location
  if (!is_writable(DRUSH_BASE_PATH)) {
    $error = dt('Drush folder at !loc is not writable', array('!loc' => DRUSH_BASE_PATH));
  }
  elseif (is_dir(DRUSH_BASE_PATH . "/CVS")) {
    $error = dt('Drush was checked out from CVS; cannot self-update.');
  }
  else {
    // Don't check unless we have a datestamp in drush.info
    $drush_info = drush_read_drush_info();
    if (($drush_info === FALSE) || (!array_key_exists('datestamp', $drush_info))) {
      $error = dt('Cannot determine release date for drush');
    }
  }

  // Fail with 'return FALSE' or 'drush_set_error', as appropriate.
  if (!empty($error)) {
    if ($explicit) {
      return drush_set_error('DRUSH_CANNOT_SELF_UPDATE', $error, 'error');
    }
    else {
      drush_log($error, 'notice');
      return FALSE;
    }
  }

  // Allow updates to the latest HEAD release if --self-update=head is specified.
  // If we are called from `drush self-update`, then --dev will set --self-update=head.
  $dev_ok = (drush_get_option('self-update') == 'head');
  $is_dev = FALSE;

  // Get release info for drush
  $info = _drush_pm_get_releases(array('drush'));
  // Check for newer releases based on the datestamp.
  // We add 60 seconds to the drush.info date because of a drupal.org WTF. See http://drupal.org/node/1019356.
  $version_date = $drush_info['datestamp'] + 60;
  $newer_version = FALSE;
  foreach ($info['drush']['releases'] as $version => $release_info) {
    // We deliberately skip any dev releases unless the current release is a dev release.
    if ($dev_ok || ((strpos(DRUSH_VERSION, "-dev") !== FALSE) && $explicit) || (!array_key_exists('version_extra', $release_info) || ($release_info['version_extra'] != 'dev'))) {
      if ($release_info['date'] > $version_date) {
        $newer_version = $release_info['version'];
        $version_date = $release_info['date'];
        $is_dev = isset($release_info['version_extra']) && $release_info['version_extra'] == 'dev';
        if ($is_dev) {
          $newer_version .= " (" . date('Y-M-d', $version_date) . ")";
        }
      }
    }
  }

  if ($newer_version) {
    if ($update) {
      $backup_dir = drush_preflight_backup_dir('drush');
      if(drush_confirm(dt('A newer version of drush, !version, is available.  Would you like to back up your current drush, version !currentversion, to !backup and replace it with the newer release?', array('!version' => $newer_version, '!currentversion' => DRUSH_VERSION, '!backup' => $backup_dir)))) {
	// pm-download will select the most recent stable release, which is what we want.
	return (drush_invoke_process_args("pm-download", array("drush"), array("destination" => DRUSH_BASE_PATH, "backup-location" => $backup_dir, "dev" => $is_dev)) !== FALSE);
      }
    }
    else {
      drush_print(dt('A newer version of drush, !version, is available.  You are currently running drush version !currentversion; to update, run `drush self-update`.  To disable this check, put "$options[\'self-update\'] = FALSE;" in your drushrc.php configuration file.' . "\n", array('!version' => $newer_version, '!currentversion' => DRUSH_VERSION)));
      return TRUE;
    }
  }
  else {
    drush_log(dt("drush self-update check: drush !version is up-to-date.", array('!version' => DRUSH_VERSION)), $explicit ? 'ok' : 'notice');
  }

  return NULL;
}

/**
 * Generate code friendly to the Drupal .info format from a structured array.
 * Mostly copied from http://drupalcode.org/viewvc/drupal/contributions/modules/features/features.export.inc.
 *
 * @param $info
 *   An array or single value to put in a module's .info file.
 *
 * @param boolean $integer_keys
 *   Use integer in keys. See archive-dump command.
 *
 * @param $parents
 *   Array of parent keys (internal use only).
 *
 * @return
 *   A code string ready to be written to a module's .info file.
 */
function drush_export_info($info, $integer_keys = FALSE, $parents = array()) {
  $output = '';
  if (is_array($info)) {
    foreach ($info as $k => $v) {
      $child = $parents;
      $child[] = $k;
      $output .= drush_export_info($v, $integer_keys, $child);
    }
  }
  else if (!empty($info) && count($parents)) {
    $line = array_shift($parents);
    foreach ($parents as $key) {
      $line .= (!$integer_keys && is_numeric($key)) ? "[]" : "[{$key}]";
    }
    $line .=  " = \"{$info}\"\n";
    return $line;
  }
  return $output;
}

/**
 * Convert a csv string, or an array of items which
 * may contain csv strings, into an array of items.
 *
 * @param $args
 *   A simple csv string; e.g. 'a,b,c'
 *   or a simple list of items; e.g. array('a','b','c')
 *   or some combination; e.g. array('a,b','c') or array('a,','b,','c,')
 *
 * @returns array
 *   A simple list of items (e.g. array('a','b','c')
 */
function _convert_csv_to_array($args) {
  //
  // Step 1: implode(',',$args) converts from, say, array('a,','b,','c,') to 'a,,b,,c,'
  // Step 2: explode(',', ...) converts to array('a','','b','','c','')
  // Step 3: array_filter(...) removes the empty items
  //
  return array_filter(explode(',', is_array($args) ? implode(',',$args) : $args));
}

/**
 * Get the available global options. Used by help command. Command files may
 * modify this list using hook_drush_help_alter().
 *
 * @param boolean $brief
 *   Return a reduced set of important options. Used by help command.
 *
 * @return
 *   An associative array containing the option definition as the key, and the description as the value,
 *   for each of the available options.
 */
function drush_get_global_options($brief = FALSE) {
  $options['root']               = array('short-form' => 'r', 'description' => dt("Drupal root directory to use (default: current directory)"), 'example-value' => '<path>');
  $options['uri']                = array('short-form' => 'l', 'description' => dt('URI of the drupal site to use (only needed in multisite environments)'), 'example-value' => 'http://example.com');
  $options['verbose']            = array('short-form' => 'v', 'description' => dt('Display extra information about the command.'));
  $options['debug']              = array('short-form' => 'd', 'description' => dt('Display even more information, including internal messages.'));
  $options['yes']                = array('short-form' => 'y', 'description' => dt("Assume 'yes' as answer to all prompts"));
  $options['no']                 = array('short-form' => 'n', 'description' => dt("Assume 'no' as answer to all prompts"));
  $options['simulate']           = array('short-form' => 's', 'description' => dt("Simulate all relevant actions (don't actually change the system)"));
  $options['pipe']               = array('short-form' => 'p', 'description' => dt("Emit a compact representation of the command for scripting."));
  $options['help']               = array('short-form' => 'h', 'description' => dt("This help system."));
  $options['version']            = dt("Show drush version.");
  $options['php']                = dt("The absolute path to your PHP intepreter, if not 'php' in the path.");

  if (!$brief) {
    $options['quiet']            = array('short-form' => 'q', 'description' => dt('Hide all output'));
    $options['include']          = array('short-form' => 'i', 'description' => dt("A list of paths to search for drush commands"));
    $options['config']           = array('short-form' => 'c', 'description' => dt("Specify a config file to use. See example.drushrc.php"));
    $options['user']             = array('short-form' => 'u', 'description' => dt("Specify a user to login with. May be a name or a number."));
    $options['backend']          = array('short-form' => 'b', 'description' => dt("Hide all output and return structured data (internal use only)."));
    $options['choice']           = dt("Provide an answer to a multiple-choice prompt.");
    $options['no-label']         = dt("Remove the site label that drush includes in multi-site command output(e.g. `drush @site1,@site2 status`).");
    $options['nocolor']          = dt("Suppress color highlighting on log messages.");
    $options['show-passwords']   = dt("Show database passwords in commands that display connection information.");
    $options['show-invoke']      = dt("Show all function names which could have been called for the current command. See drush_invoke().");
    $options['watchdog']         = dt("Control logging of Drupal's watchdog() to drush log. Recognized values are 'log', 'print', 'disabled'. Defaults to log. 'print' shows calls to admin but does not add them to the log.");
  }
  return $options;
}

/**
 * Exits with a message. In general, you should use drush_set_error() instead of
 * this function. That lets drush proceed with other tasks.
 * TODO: Exit with a correct status code.
 */
function drush_die($msg = NULL, $status = NULL) {
  die($msg ? "drush: $msg\n" : '');
}

/*
 * Check to see if the provided line is a "#!/usr/bin/env drush"
 * "shebang" script line.
 */
function _drush_is_drush_shebang_line($line) {
   return ((substr($line,0,2) == '#!') && (strstr($line, 'drush') !== FALSE));
}

/*
 * Check to see if the provided script file is a "#!/usr/bin/env drush"
 * "shebang" script line.
 */
function _drush_is_drush_shebang_script($script_filename) {
  $result = FALSE;

  if (file_exists($script_filename)) {
    $fp = fopen($script_filename, "r");
    if ($fp !== FALSE) {
      $line = fgets($fp);
      $result = _drush_is_drush_shebang_line($line);
      fclose($fp);
    }
  }

  return $result;
}

/**
 * @defgroup userinput Get input from the user.
 * @{

/**
 * Ask the user a basic yes/no question.
 *
 * @param $msg The question to ask
 * @return TRUE if the user entered 'y', FALSE if he entered 'n'
 */
function drush_confirm($msg, $indent = 0) {
  print str_repeat(' ', $indent) . (string)$msg . " (y/n): ";

  // Automatically accept confirmations if the --yes argument was supplied.
  if (drush_get_context('DRUSH_AFFIRMATIVE')) {
    print "y\n";
    return TRUE;
  }
  // Automatically cancel confirmations if the --no argument was supplied.
  elseif (drush_get_context('DRUSH_NEGATIVE')) {
    print "n\n";
    return FALSE;
  }
  // See http://drupal.org/node/499758 before changing this.
  $stdin = fopen("php://stdin","r");

  while ($line = fgets($stdin)) {
  $line = trim($line);
    if ($line == 'y') {
      return TRUE;
    }
    if ($line == 'n') {
      return FALSE;
    }
    print str_repeat(' ', $indent) . (string)$msg . " (y/n): ";
  }
}

/**
 * Ask the user to select an item from a list.
 * From a provided associative array, drush_choice will
 * display all of the questions, numbered from 1 to N,
 * and return the item the user selected. "0" is always
 * cancel; entering a blank line is also interpreted
 * as cancelling.
 *
 * @param $options
 *   A list of questions to display to the user.  The
 *   KEYS of the array are the result codes to return to the
 *   caller; the VALUES are the messages to display on
 *   each line. Special keys of the form '-- something --' can be
 *   provided as separator between choices groups. Separator keys
 *    don't alter the numbering.
 * @param $prompt
 *   The message to display to the user prompting for input.
 * @param $label
 *   Controls the display of each line.  Defaults to
 *   '!value', which displays the value of each item
 *   in the $options array to the user.  Use '!key' to
 *   display the key instead.  In some instances, it may
 *   be useful to display both the key and the value; for
 *   example, if the key is a user id and the value is the
 *   user name, use '!value (uid=!key)'.
 */
function drush_choice($options, $prompt = 'Enter a number.', $label = '!value') {
  print dt($prompt) . "\n";

  // Preflight so that all rows will be padded out to the same number of columns
  $array_pad = 0;
  foreach ($options as $key => $option) {
    if (is_array($option) && (count($option) > $array_pad)) {
      $array_pad = count($option);
    }
  }

  $rows[] = array_pad(array('[0]', ':', 'Cancel'), $array_pad + 2, '');
  $selection_number = 0;
  foreach ($options as $key => $option) {
    if ((substr($key, 0, 3) == '-- ') && (substr($key, -3) == ' --')) {
      $rows[] = array_pad(array('', '', $option), $array_pad + 2, '');
    }
    else {
      $selection_number++;
      $row = array("[$selection_number]", ':');
      if (is_array($option)) {
        $row = array_merge($row, $option);
      }
      else {
        $row[] = dt($label, array('!number' => $selection_number, '!key' => $key, '!value' => $option));
      }
      $rows[] = $row;
      $selection_list[$selection_number] = $key;
    }
  }
  drush_print_table($rows);
  drush_print_pipe(array_keys($options));

  // If the user specified --choice, then make an
  // automatic selection.  Cancel if the choice is
  // not an available option.
  if (($choice = drush_get_option('choice', FALSE)) !== FALSE) {
    // First check to see if $choice is one of the symbolic options
    if (array_key_exists($choice, $options)) {
      return $choice;
    }
    // Next handle numeric selections
    elseif (array_key_exists($choice, $selection_list)) {
      return $selection_list[$choice];
    }
    return FALSE;
  }

  // If the user specified --no, then cancel; also avoid
  // getting hung up waiting for user input in --pipe and
  // backend modes.  If none of these apply, then wait,
  // for user input and return the selected result.
  if (!drush_get_context('DRUSH_NEGATIVE') && !drush_get_context('DRUSH_AFFIRMATIVE') && !drush_get_context('DRUSH_PIPE')) {
    while ($line = trim(fgets(STDIN))) {
      if (array_key_exists($line, $selection_list)) {
        return $selection_list[$line];
      }
    }
  }
  // We will allow --yes to confirm input if there is only
  // one choice; otherwise, --yes will cancel to avoid ambiguity
  if (drush_get_context('DRUSH_AFFIRMATIVE')  && (count($options) == 1)) {
    return $selection_list[1];
  }
  drush_print(dt('Cancelled'));
  return FALSE;
}

/**
 * Ask the user to select multiple items from a list.
 * This is a wrapper around drush_choice, that repeats the selection process,
 * allowing users to toggle a number of items in a list. The number of values
 * that can be constrained by both min and max: the user will only be allowed
 * finalize selection once the minimum number has been selected, and the oldest
 * selected value will "drop off" the list, if they exceed the maximum number.
 *
 * @param $options
 *   Same as drush_choice() (see above).
 * @param $defaults
 *   This can take 3 forms:
 *   - FALSE: (Default) All options are unselected by default.
 *   - TRUE: All options are selected by default.
 *   - Array of $options keys to be selected by default.
 * @param $prompt
 *   Same as drush_choice() (see above).
 * @param $label
 *   Same as drush_choice() (see above).
 * @param $mark
 *   Controls how selected values are marked.  Defaults to '!value (selected)'.
 * @param $min
 *   Constraint on minimum number of selections. Defaults to zero. When fewer
 *   options than this are selected, no final options will be available.
 * @param $max
 *   Constraint on minimum number of selections. Defaults to NULL (unlimited).
 *   If the a new selection causes this value to be exceeded, the oldest
 *   previously selected value is automatically unselected.
 * @param $final_options
 *   An array of additional options in the same format as $options.
 *   When the minimum number of selections is met, this array is merged into the
 *   array of options. If the user selects one of these values and the
 *   selection process will complete (the key for the final option is included
 *   in the return value). If this is an empty array (default), then a built in
 *   final option of "Done" will be added to the available options (in this case
 *   no additional keys are added to the return value).
 */
function drush_choice_multiple($options, $defaults = FALSE, $prompt = 'Select some numbers.', $label = '!value', $mark = '!value (selected)', $min = 0, $max = NULL, $final_options = array()) {
  $selections = array();
  // Load default selections.
  if (is_array($defaults)) {
    $selections = $defaults;
  }
  elseif ($defaults === TRUE) {
    $selections = array_keys($options);
  }
  $complete = FALSE;
  $final_builtin = array();
  if (empty($final_options)) {
    $final_builtin['done'] = dt('Done');
  }
  $final_options_keys = array_keys($final_options);
  while (TRUE) {
    $current_options = $options;
    // Mark selections.
    foreach ($selections as $selection) {
      $current_options[$selection] = dt($mark, array('!key' => $selection, '!value' => $options[$selection]));
    }
    // Add final options, if the minimum number of selections has been reached.
    if (count($selections) >= $min) {
      $current_options = array_merge($current_options, $final_options, $final_builtin);
    }
    $toggle = drush_choice($current_options, $prompt, $label);
    if ($toggle === FALSE) {
      return FALSE;
    }
    // Don't include the built in final option in the return value.
    if (count($selections) >= $min && empty($final_options) && $toggle == 'done') {
      return $selections;
    }
    // Toggle the selected value.
    $item = array_search($toggle, $selections);
    if ($item === FALSE) {
      array_unshift($selections, $toggle);
    }
    else {
      unset($selections[$item]);
    }
    // If the user selected one of the final options, return.
    if (count($selections) >= $min && in_array($toggle, $final_options_keys)) {
      return $selections;
    }
    // If the user selected too many options, drop the oldest selection.
    if (count($selections) > $max) {
      array_pop($selections);
    }
  }
}

/**
 * Prompt the user for input
 *
 * The input can be anything that fits on a single line (not only y/n),
 * so we can't use drush_confirm()
 *
 * @param $prompt
 *   The text which is displayed to the user.
 * @param $default
 *   The default value of the input.
 * @param $required
 *   If TRUE, user may continue even when no value is in the input.
 *
 * @see drush_confirm()
 */
function drush_prompt($prompt, $default = NULL, $required = TRUE) {
  if (!is_null($default)) {
    $prompt .= " [" . $default . "]";
  }
  $prompt .= ": ";

  print $prompt;

  if (drush_get_context('DRUSH_AFFIRMATIVE')) {
    return $default;
  }

  $stdin = fopen('php://stdin', 'r');
  stream_set_blocking($stdin, TRUE);
  while (($line = fgets($stdin)) !== FALSE) {
    $line = trim($line);
    if ($line === "") {
      $line = $default;
    }
    if ($line || !$required) {
      break;
    }
    print $prompt;
  }
  fclose($stdin);
  return $line;
}

/**
 * @} End of "defgroup userinput".
 */

/**
 * Calls a given function, passing through all arguments unchanged.
 *
 * This should be used when calling possibly mutative or destructive functions
 * (e.g. unlink() and other file system functions) so that can be suppressed
 * if the simulation mode is enabled.
 *
 * Important:  Call @see drush_op_system() to execute a shell command,
 * or @see drush_shell_exec() to execute a shell command and capture the
 * shell output.
 *
 * @param $function
 *   The name of the function. Any additional arguments are passed along.
 * @return
 *   The return value of the function, or TRUE if simulation mode is enabled.
 *
 */
function drush_op($function) {
  $args = func_get_args();
  array_shift($args); // Skip function name

  // Special checking for drush_op('system')
  if ($function == 'system') {
    drush_log(dt("Do not call drush_op('system'); use drush_op_system instead"), 'debug');
  }

  if (drush_get_context('DRUSH_VERBOSE') || drush_get_context('DRUSH_SIMULATE')) {
     drush_print(sprintf("Calling %s(%s)", $function, implode(", ", $args)));
  }

  if (drush_get_context('DRUSH_SIMULATE')) {
    return TRUE;
  }

  return call_user_func_array($function, $args);
}

/**
 * Check if the operating system is Windows.
 */
function drush_is_windows() {
  if (substr(php_uname(), 0, 7) == 'Windows') {
    return TRUE;
  }
  else {
    return FALSE;
  }
}

/**
 * Download a file using wget or curl.
 *
 * @param string $url
 *   The path to the file to download
 *
 * @return string
 *   The filename that was downloaded,
 *   or NULL if the file could not be
 *   downloaded.
 */
function _drush_download_file($url) {
  $filename = explode('/', $url);
  $filename = array_pop($filename);

  if (!drush_shell_exec("wget %s", $url)) {
    if(!drush_shell_exec("curl -O %s", $url)) {
      return NULL;
    }
  }

  return $filename;
}

/**
 * @defgroup commandprocessing Command processing functions.
 * @{
 *
 * These functions manage command processing by the
 * main function in drush.php.
 */

/**
 * Process commands that are executed on a remote drush instance.
 *
 * @return
 *   TRUE if the command was handled remotely.
 */
function drush_remote_command() {
  // The command will be executed remotely if the --remote-host flag
  // is set; note that if a site alias is provided on the command line,
  // and the site alias references a remote server, then the --remote-host
  // option will be set when the site alias is processed.
  // @see drush_sitealias_check_arg
  $remote_host = drush_get_option('remote-host');
  if (isset($remote_host)) {

    $args = drush_get_arguments();
    $command = array_shift($args);
    $remote_user = drush_get_option('remote-user');

    drush_do_command_redispatch($command, $args, $remote_host, $remote_user);
    return TRUE;
  }
  // If the --site-list flag is set, then we will execute the specified
  // command once for every site listed in the site list.
  $site_list = drush_get_option('site-list');
  if (isset($site_list)) {
    if (!is_array($site_list)) {
      $site_list = explode(',', $site_list);
    }
    $site_list = drush_sitealias_resolve_sitespecs($site_list);
    $site_list = drush_sitealias_simplify_names($site_list);
    $args = drush_get_arguments();

    if (!drush_get_context('DRUSH_SIMULATE')) {
      drush_print(dt("You are about to execute '!command' on all of the following targets:", array('!command' => implode(" ", $args))));
      foreach ($site_list as $one_destination => $one_record) {
        drush_print(dt('  !target', array('!target' => $one_destination)));
      }

      if (drush_confirm('Continue? ') === FALSE) {
        return drush_user_abort();
      }
    }
    $command = array_shift($args);
    $multi_options = drush_get_context('cli');

    if (!drush_get_option('no-label', FALSE)) {
      $label_separator = ' >> ';
      $max_name_length = 0;
      foreach ($site_list as $alias_name => $alias_record) {
        if (strlen($alias_name) > $max_name_length) {
          $max_name_length = strlen($alias_name);
        }
      }
      $multi_options['reserve-margin'] = $max_name_length + strlen($label_separator);
      foreach ($site_list as $alias_name => $alias_record) {
        $values = drush_do_site_command($alias_record, $command, $args, $multi_options);
        foreach (explode("\n", $values['output']) as $line) {
          if (empty($line)) {
            drush_print();
          }
          else {
            drush_print(str_pad($alias_name, $max_name_length, " ") . $label_separator . $line);
          }
        }
      }
    }
    else {
      foreach ($site_list as $alias_name => $alias_record) {
        $values = drush_do_site_command($alias_record, $command, $args, $multi_options);
        drush_print($values['output']);
      }
    }
    return TRUE;
  }
  return FALSE;
}

/**
 * Used by functions that operate on lists of sites, moving
 * information from the source to the destination.  Currenlty
 * this includes 'drush rsync' and 'drush sql sync'.
 */
function drush_do_multiple_command($command, $source_record, $destination_record, $allow_single_source = FALSE) {
  $is_multiple_command = FALSE;

  if ((($allow_single_source == TRUE) || array_key_exists('site-list', $source_record)) && array_key_exists('site-list', $destination_record)) {
    $is_multiple_command = TRUE;
    $source_path = array_key_exists('path-component', $source_record) ? $source_record['path-component'] : '';
    $destination_path = array_key_exists('path-component', $destination_record) ? $destination_record['path-component'] : '';

    $target_list = array_values(drush_sitealias_resolve_sitelist($destination_record));
    if (array_key_exists('site-list', $source_record)) {
      $source_list = array_values(drush_sitealias_resolve_sitelist($source_record));

      if (drush_sitealias_check_lists_alignment($source_list, $target_list) === FALSE) {
        if (array_key_exists('unordered-list', $source_record) || array_key_exists('unordered-list', $destination_record)) {
          drush_sitelist_align_lists($source_list, $target_list, $aligned_source, $aligned_target);
          $source_list = $aligned_source;
          $target_list = $aligned_target;
        }
      }
    }
    else {
      $source_list = array_fill(0, count($target_list), $source_record);
    }

    if (!drush_get_context('DRUSH_SIMULATE')) {
      drush_print(dt('You are about to !command between all of the following targets:', array('!command' => $command)));
      $i = 0;
      foreach ($source_list as $one_source) {
        $one_target = $target_list[$i];
        ++$i;
        drush_print(dt('  !source will overwrite !target', array('!source' => drush_sitealias_alias_record_to_spec($one_source) . $source_path, '!target' => drush_sitealias_alias_record_to_spec($one_target) . $destination_path)));
      }

      if (drush_confirm('Continue? ') === FALSE) {
         return drush_user_abort();
      }
    }

    $data = drush_redispatch_get_options();
    $i = 0;
    foreach ($source_list as $one_source) {
      $one_target = $target_list[$i];
      ++$i;

      $source_spec = drush_sitealias_alias_record_to_spec($one_source);
      $target_spec = drush_sitealias_alias_record_to_spec($one_target);

      drush_log(dt('Begin do_multiple !command via backend invoke', array('!command' => $command)));
      $values = drush_backend_invoke_args($command, array($source_spec . $source_path, $target_spec . $destination_path), $data, 'GET', TRUE);
      drush_log(dt('Backend invoke is complete'));
    }
  }

  return $is_multiple_command;
}

/**
 * Run a command on the site specified by the provided command record.
 *
 * The standard function that provides this service is called
 * drush_invoke_sitealias_args.  Please call the standard function
 * unless you need to set $integrate = TRUE.
 */
function drush_do_site_command($site_record, $command, $args = array(), $data = array(), $integrate = FALSE) {
  $values = NULL;
  if (!empty($site_record)) {
    foreach ($site_record as $key => $value) {
      if (!isset($data[$key]) && !in_array($key, drush_sitealias_site_selection_keys())) {
        $data[$key] = $site_record[$key];
      }
    }
    $values = drush_backend_invoke_sitealias($site_record, $command, $args, $data, 'GET', $integrate);
  }
  return $values;
}

/**
 * Redispatch the specified command using the same
 * options that were passed to this invocation of drush.
 */
function drush_do_command_redispatch($command, $args = array(), $remote_host = NULL, $remote_user = NULL, $drush_path = NULL) {
  $data = drush_redispatch_get_options();

  // If the path to drush was supplied, then pass it to backend invoke.
  if ($drush_path == NULL) {
    $drush_path = drush_get_option('drush-script');
    if (!isset($drush_path)) {
      $drush_folder = drush_get_option('drush');
      if (isset($drush)) {
        $drush_path = $drush_folder . '/drush';
      }
    }
  }
  // Call through to backend invoke.
  drush_log(dt('Begin redispatch via backend invoke'));
  $values = drush_backend_invoke_args($command, $args, $data, 'GET', TRUE, $drush_path, $remote_host, $remote_user);
  drush_log(dt('Backend invoke is complete'));

  return $values;
}


/**
 * @} End of "defgroup commandprocessing".
 */

/**
 * @defgroup logging Logging information to be provided as output.
 * @{
 *
 * These functions are primarily for diagnostic purposes, but also provide an overview of tasks that were taken
 * by drush.
 */

/**
 * Add a log message to the log history.
 *
 * This function calls the callback stored in the 'DRUSH_LOG_CALLBACK' context with
 * the resulting entry at the end of execution.
 *
 * This allows you to replace it with custom logging implementations if needed,
 * such as logging to a file or logging to a database (drupal or otherwise).
 *
 * The default callback is the _drush_print_log() function with prints the messages
 * to the shell.
 *
 * @param message
 *   String containing the message to be logged.
 * @param type
 *   The type of message to be logged. Common types are 'warning', 'error', 'success' and 'notice'.
 *   A type of 'failed' can also be supplied to flag as an 'error'.
 *   A type of 'ok' or 'completed' can also be supplied to flag as a 'success'
 *   All other types of messages will be assumed to be notices.
 */
function drush_log($message, $type = 'notice', $error = null) {
  $log =& drush_get_context('DRUSH_LOG', array());
  $callback = drush_get_context('DRUSH_LOG_CALLBACK', '_drush_print_log');
  $entry = array(
     'type' => $type,
     'message' => $message,
     'timestamp' => microtime(TRUE),
     'memory' => memory_get_usage(),
   );
  $entry['error'] = $error;
  $log[] = $entry;
  return $callback($entry);
}

/**
 * Retrieve the log messages from the log history
 *
 * @return
 *   Entire log history
 */
function drush_get_log() {
  return drush_get_context('DRUSH_LOG', array());
}

/**
 * Run print_r on a variable and log the output.
 */
function dlm($object) {
  ob_start();
  print_r($object);
  $contents = ob_get_contents();
  ob_end_clean();

  drush_log($contents);
}

/**
 * Display the pipe output for the current request.
 */
function drush_pipe_output() {
  $pipe = drush_get_context('DRUSH_PIPE_BUFFER');
  if (!empty($pipe)) {
    drush_print_r($pipe);
  }
}

/**
 * Display the log message
 *
 * By default, only warnings and errors will be displayed, if 'verbose' is specified, it will also display notices.
 *
 * @param
 *   The associative array for the entry.
 *
 * @return
 *   False in case of an error or failed type, True in all other cases.
 */
function _drush_print_log($entry) {
  if (drush_get_context('DRUSH_NOCOLOR')) {
    $red = "[%s]";
    $yellow = "[%s]";
    $green = "[%s]";
  }
  else {
    $red = "\033[31;40m\033[1m[%s]\033[0m";
    $yellow = "\033[1;33;40m\033[1m[%s]\033[0m";
    $green = "\033[1;32;40m\033[1m[%s]\033[0m";
  }

  $verbose = drush_get_context('DRUSH_VERBOSE');
  $debug = drush_get_context('DRUSH_DEBUG');

  $return = TRUE;
  switch ($entry['type']) {
    case 'warning' :
    case 'cancel' :
      $type_msg = sprintf($yellow, $entry['type']);
      break;
    case 'failed' :
    case 'error' :
      $type_msg = sprintf($red, $entry['type']);
      $return = FALSE;
      break;
    case 'ok' :
    case 'completed' :
    case 'success' :
    case 'status':
      $type_msg = sprintf($green, $entry['type']);
      break;
    case 'notice' :
    case 'message' :
    case 'info' :
      if (!$verbose) {
        // print nothing. exit cleanly.
        return TRUE;
      }
      $type_msg = sprintf("[%s]", $entry['type']);
      break;
    default :
      if (!$debug) {
        // print nothing. exit cleanly.
        return TRUE;
      }
      $type_msg = sprintf("[%s]", $entry['type']);
      break;
  }

  // When running in backend mode, log messages are not displayed, as they will
  // be returned in the JSON encoded associative array.  In quiet mode, we
  // just drop log messages.
  if (drush_get_context('DRUSH_BACKEND') || drush_get_context('DRUSH_QUIET')) {
    return $return;
  }

  $columns = drush_get_context('DRUSH_COLUMNS', 80);

  $width[1] = 11;
  // Append timer and memory values.
  if ($debug) {
    $timer = sprintf('[%s sec, %s]', round($entry['timestamp']-DRUSH_REQUEST_TIME, 2), drush_format_size($entry['memory']));
    $entry['message'] = $entry['message'] . ' ' . $timer;
  }

  $width[0] = ($columns - 11);

  $format = sprintf("%%-%ds%%%ds", $width[0], $width[1]);

  // Place the status message right aligned with the top line of the error message.
  $message = wordwrap($entry['message'], $width[0]);
  $lines = explode("\n", $message);
  $lines[0] = sprintf($format, $lines[0], $type_msg);
  $message = implode("\n", $lines);
  drush_print($message, 0, STDERR);
  return $return;
}

// Print all timers for the request.
function drush_print_timers() {
  global $timers;
  $temparray = array();
  foreach ((array)$timers as $name => $timerec) {
    // We have to use timer_read() for active timers, and check the record for others
    if (isset($timerec['start'])) {
      $temparray[$name] = timer_read($name);
    }
    else {
      $temparray[$name] = $timerec['time'];
    }
  }
  // Go no farther if there were no timers
  if (count($temparray) > 0) {
    // Put the highest cumulative times first
    arsort($temparray);
    $table = array();
    $table[] = array('Timer', 'Cum (sec)', 'Count', 'Avg (msec)');
    foreach ($temparray as $name => $time) {
      $cum = round($time/1000, 3);
      $count = $timers[$name]['count'];
      if ($count > 0) {
        $avg = round($time/$count, 3);
      }
      else {
        $avg = 'N/A';
      }
      $table[] = array($name, $cum, $count, $avg);
    }
    drush_print_table($table, TRUE);
  }
}

/**
* Turn drupal_set_message errors into drush_log errors
*/
function _drush_log_drupal_messages() {
  if (function_exists('drupal_get_messages')) {

    $messages = drupal_get_messages(NULL, TRUE);

    if (array_key_exists('error', $messages)) {
      //Drupal message errors.
      foreach ((array) $messages['error'] as $error) {
        $error = strip_tags($error);
        $header = preg_match('/^warning: Cannot modify header information - headers already sent by /i', $error);
        $session = preg_match('/^warning: session_start\(\): Cannot send session /i', $error);
        if ($header || $session) {
          //These are special cases for an unavoidable warnings
          //that are generated by generating output before Drupal is bootstrapped.
          //or sending a session cookie (seems to affect d7 only?)
          //Simply ignore them.
          continue;
        }
        elseif (preg_match('/^warning:/i', $error)) {
          drush_log(preg_replace('/^warning: /i', '', $error), 'warning');
        }
        elseif (preg_match('/^notice:/i', $error)) {
          drush_log(preg_replace('/^notice: /i', '', $error), 'notice');
        }
        elseif (preg_match('/^user warning:/i', $error)) {
          // This is a special case. PHP logs sql errors as 'User Warnings', not errors.
          drush_set_error('DRUSH_DRUPAL_ERROR_MESSAGE', preg_replace('/^user warning: /i', '', $error));
        }
        else {
          drush_set_error('DRUSH_DRUPAL_ERROR_MESSAGE', $error);
        }
      }
    }
    unset($messages['error']);

    // Log non-error messages.
    foreach ($messages as $type => $items) {
      foreach ($items as $item) {
        drush_log(strip_tags($item), $type);
      }
    }
  }
}

// Copy of format_size() in Drupal.
function drush_format_size($size, $langcode = NULL) {
  if ($size < DRUSH_DRUPAL_KILOBYTE) {
    // format_plural() not always available.
    return dt('@count bytes', array('@count' => $size));
  }
  else {
    $size = $size / DRUSH_DRUPAL_KILOBYTE; // Convert bytes to kilobytes.
    $units = array(
      dt('@size KB', array(), array('langcode' => $langcode)),
      dt('@size MB', array(), array('langcode' => $langcode)),
      dt('@size GB', array(), array('langcode' => $langcode)),
      dt('@size TB', array(), array('langcode' => $langcode)),
      dt('@size PB', array(), array('langcode' => $langcode)),
      dt('@size EB', array(), array('langcode' => $langcode)),
      dt('@size ZB', array(), array('langcode' => $langcode)),
      dt('@size YB', array(), array('langcode' => $langcode)),
    );
    foreach ($units as $unit) {
      if (round($size, 2) >= DRUSH_DRUPAL_KILOBYTE) {
        $size = $size / DRUSH_DRUPAL_KILOBYTE;
      }
      else {
        break;
      }
    }
    return str_replace('@size', round($size, 2), $unit);
  }
}

/**
 * @} End of "defgroup logging".
 */

/**
 * @defgroup errorhandling Managing errors that occur in the Drush framework.
 * @{
 * Functions that manage the current error status of the Drush framework.
 *
 * These functions operate by maintaining a static variable that is a equal to the constant DRUSH_FRAMEWORK_ERROR if an
 * error has occurred.
 * This error code is returned at the end of program execution, and provide the shell or calling application with
 * more information on how to diagnose any problems that may have occurred.
 */

/**
 * Set an error code for the error handling system.
 *
 * @param error
 *   A text string identifying the type of error.
 *
 * @param message
 *   Optional. Error message to be logged. If no message is specified, hook_drush_help will be consulted,
 *   using a key of 'error:MY_ERROR_STRING'.
 *
 * @return
 *   Always returns FALSE, to allow you to return with false in the calling functions,
 *   such as <code>return drush_set_error('DRUSH_FRAMEWORK_ERROR')</code>
 */
function drush_set_error($error, $message = null) {
  $error_code =& drush_get_context('DRUSH_ERROR_CODE', DRUSH_SUCCESS);
  $error_code = DRUSH_FRAMEWORK_ERROR;

  $error_log =& drush_get_context('DRUSH_ERROR_LOG', array());

  if (is_numeric($error)) {
    $error = 'DRUSH_FRAMEWORK_ERROR';
  }

  $message = ($message) ? $message : drush_command_invoke_all('drush_help', 'error:' . $error);

  if (is_array($message)) {
    $message = implode("\n", $message);
  }

  $error_log[$error][] = $message;
  drush_log(($message) ? $message : $error, 'error', $error);

  return FALSE;
}

/**
 * Return the current error handling status
 *
 * @return
 *   The current aggregate error status
 */
function drush_get_error() {
  return drush_get_context('DRUSH_ERROR_CODE', DRUSH_SUCCESS);
}

/**
 * Return the current list of errors that have occurred.
 *
 * @return
 *   An associative array of error messages indexed by the type of message.
 */
function drush_get_error_log() {
  return drush_get_context('DRUSH_ERROR_LOG', array());
}

/**
 * Check if a specific error status has been set.
 *
 * @param error
 *   A text string identifying the error that has occurred.
 * @return
 *   TRUE if the specified error has been set, FALSE if not
 */
function drush_cmp_error($error) {
  $error_log = drush_get_error_log();

  if (is_numeric($error)) {
    $error = 'DRUSH_FRAMEWORK_ERROR';
  }

  return array_key_exists($error, $error_log);
}

/**
 * Exit due to user declining a confirmation prompt.
 *
 * Usage:  return drush_user_abort();
 */
function drush_user_abort($msg = NULL) {
  drush_set_context('DRUSH_USER_ABORT', TRUE);
  drush_log($msg ? $msg : dt('Aborting.'), 'cancel');
  return FALSE;
}

/**
 * Turn PHP error handling off.
 *
 * This is commonly used while bootstrapping Drupal for install
 * or updates.
 */
function drush_errors_off() {
  $errors =& drush_get_context('DRUSH_ERROR_REPORTING', 0);
  $errors = error_reporting(0);
  ini_set('display_errors', FALSE);
}

/**
 * Turn PHP error handling on.
 */
function drush_errors_on() {
  $errors =& drush_get_context('DRUSH_ERROR_REPORTING', E_ALL ^ E_NOTICE);
  $errors = error_reporting($errors);
  ini_set('display_errors', TRUE);
}

/**
 * @} End of "defgroup errorhandling".
 */

/**
 * Get the PHP memory_limit value in bytes.
 */
function drush_memory_limit() {
  $value = trim(ini_get('memory_limit'));
  $last = strtolower($value[strlen($value)-1]);
  switch ($last) {
    case 'g':
      $value *= DRUSH_DRUPAL_KILOBYTE;
    case 'm':
      $value *= DRUSH_DRUPAL_KILOBYTE;
    case 'k':
      $value *= DRUSH_DRUPAL_KILOBYTE;
  }

  return $value;
}

/**
 * Unset the named key anywhere in the provided
 * data structure.
 */
function drush_unset_recursive(&$data, $unset_key) {
  if (!empty($data) && is_array($data)) {
    unset($data[$unset_key]);
    foreach ($data as $key => $value) {
      if (is_array($value)) {
        drush_unset_recursive($data[$key], $unset_key);
      }
    }
  }
}

/**
 * Return a list of all supported VCSs reserved files and directories.
 */
function drush_version_control_reserved_files() {
  static $files = FALSE;

  if (!$files) {
    $files = array();
    $vcs = array_keys(drush_get_engines('version_control'));
    foreach ($vcs as $name) {
      drush_include_engine('version_control', $name);
      $class = 'drush_pm_version_control_' . $name;
      // For php < 5.3 we can't access a static method by referencing the class
      // using a variable.
      $version_control = new $class();
      $files = array_merge($files, $version_control->reserved_files());
    }
  }

  return $files;
}
