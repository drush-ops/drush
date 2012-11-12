<?php

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
 * Default amount of time, in seconds, to cache downloads via
 * drush_download_file(). One day is 86400 seconds.
 */
define('DRUSH_CACHE_LIFETIME_DEFAULT', 86400);

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
    //drush_log(dt('Including version specific file : @file', array('@file' => $file)));
    include_once($file);
    return TRUE;
  }
  $file = sprintf("%s/%s.%s", $path, $name, $extension);
  if (file_exists($file)) {
    //drush_log(dt('Including non-version specific file : @file', array('@file' => $file)));
    include_once($file);
    return TRUE;
  }
}

/**
 * Obtain all engine types info and normalize with defaults.
 *
 * @see hook_drush_engine_type_info().
 */
function drush_get_engine_types_info() {
  $info = drush_command_invoke_all('drush_engine_type_info');
  foreach ($info as $type => $data) {
    $info[$type] += array(
      'description' => '',
      'option' => FALSE,
      'default' => NULL,
      'options' => array(),
      'add-options-to-command' => FALSE,
    );
  }

  return $info;
}

/**
 * Return a structured array of engines of a specific type.
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
 * @param $engine_type
 *   The type of engine.
 *
 * @return
 *   A structured array of engines.
 */
function drush_get_engines($engine_type) {
  $info = drush_get_engine_types_info();
  if (!isset($info[$engine_type])) {
    return drush_set_error('DRUSH_UNKNOWN_ENGINE_TYPE', dt('Unknown engine type !engine_type', array('!engine_type' => $engine_type)));
  }

  $engines = array(
    'info' => $info[$engine_type],
    'engines' => array(),
  );
  $list = drush_commandfile_list();
  $hook = 'drush_engine_' . $engine_type;
  foreach ($list as $commandfile => $path) {
    if (drush_command_hook($commandfile, $hook)) {
      $function = $commandfile . '_' . $hook;
      $result = $function();
      foreach ($result as $key => $engine) {
        // Add some defaults
        $engine += array(
          'commandfile' => $commandfile,
          // Engines by default live in a subdirectory of the commandfile that
          // declared them, named as per the type of engine they are.
          'path' => sprintf("%s/%s", dirname($path), $engine_type),
        );
        $engines['engines'][$key] = $engine;
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
 * If a class named in the form drush_$type_$engine exists, it will be an
 * object of that class will be created and returned.
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
 * @return TRUE or instanced object of available class on success. FALSE on fail.
 */
function drush_include_engine($type, $engine, $version = NULL, $path = NULL) {
  $engine_info = drush_get_engines($type);
  if (!$path && isset($engine_info['engines'][$engine])) {
    $path = $engine_info['engines'][$engine]['path'];
  }
  if (!$path) {
    return drush_set_error('DRUSH_ENGINE INCLUDE_NO_PATH', dt('No path was set for including the !type engine !engine.', array('!type' => $type, '!engine' => $engine)));
  }
  if (drush_include($path, $engine, $version)) {
    $class = 'drush_' . $type . '_' . $engine;
    if (class_exists($class)) {
      return new $class();
    }
    return TRUE;
  }
  return drush_set_error('DRUSH_ENGINE INCLUDE_FAILED', dt('Unable to include the !type engine !engine from !path.' , array('!path' => $path, '!type' => $type, '!engine' => $engine)));
}

/**
 * Check to see if a newer version of drush is available
 *
 * @return
 *   TRUE - A new version is available.
 *   FALSE - Error.
 *   NULL - No release available.
 */
function drush_check_self_update() {
  $explicit = FALSE;
  $update = FALSE;
  $error = "";

  // Don't check unless we have a datestamp in drush.info
  $drush_info = drush_read_drush_info();
  if (($drush_info === FALSE) || (!array_key_exists('datestamp', $drush_info))) {
    drush_log(dt('Cannot determine release date for drush'), 'notice');
    return FALSE;
  }

  $is_dev = FALSE;

  // Get release info for drush.
  drush_include_engine('release_info', 'updatexml');
  $request = pm_parse_project_version(array('drush'));
  $info = release_info_get_releases($request);
  // Check for newer releases based on the datestamp.
  // We add 60 seconds to the drush.info date because of a drupal.org WTF. See http://drupal.org/node/1019356.
  $version_date = $drush_info['datestamp'] + 60;
  $newer_version = FALSE;
  foreach ($info['drush']['releases'] as $version => $release_info) {
    // We deliberately skip any dev releases unless the current release is a dev release.
    if ((!array_key_exists('version_extra', $release_info) || ($release_info['version_extra'] != 'dev'))) {
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
    drush_print(dt('A newer version of drush, !version, is available.  You are currently running drush version !currentversion. The update process depends on how you installed drush. Some common update commands are: `pear upgrade drush/drush`,  `git pull`, `drush dl drush --destination=[/path/to/drush]`.' . "\n", array('!version' => $newer_version, '!currentversion' => DRUSH_VERSION)));
    return TRUE;
  }
  else {
    drush_log(dt("drush self-update check: drush !version is up-to-date.", array('!version' => DRUSH_VERSION)), 'notice');
  }

  return NULL;
}

/**
 * Generate an .ini file. used by archive-dump."
 *
 * @param array $ini
 *   A two dimensional associative array where top level are sections and
 *   second level are key => value pairs.
 *
 * @return string
 *   .ini formatted text.
 */
function drush_export_ini($ini) {
  $output = '';
  foreach ($ini as $section => $pairs) {
    if ($section) {
      $output .= "[$section]\n";
    }

    foreach ($pairs as $k => $v) {
      if ($v) {
        $output .= "$k = \"$v\"\n";
      }
    }
  }
  return $output;
}

/**
 * Generate code friendly to the Drupal .info format from a structured array.
 * Mostly copied from http://drupalcode.org/viewvc/drupal/contributions/modules/features/features.export.inc.
 *
 * @param $info
 *   An array or single value to put in a module's .info file.
 *
 * @param boolean $integer_keys
 *   Use integer in keys.
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
 *   An associative array containing the option definition as the key,
 *   and a descriptive array for each of the available options.  The array
 *   elements for each item are:
 *
 *     - short-form: The shortcut form for specifying the key on the commandline.
 *     - context: The drush context where the value of this item is cached.  Used
 *       by backend invoke to propagate values set in code.
 *     - never-post: If TRUE, backend invoke will never POST this item's value
 *       on STDIN; it will always be sent as a commandline option.
 *     - never-propagate: If TRUE, backend invoke will never pass this item on
 *       to the subcommand being executed.
 *     - local-context-only: Backend invoke will only pass this value on for local calls.
 *     - merge: For options such as $options['shell-aliases'] that consist of an array
 *       of items, make a merged array that contains all of the values specified for
 *       all of the contexts (config files) where the option is defined.  The value is stored in
 *       the specified 'context', or in a context named after the option itself if the
 *       context flag is not specified.
 *       IMPORTANT: When the merge flag is used, the option value must be obtained via
 *       drush_get_context('option') rather than drush_get_option('option').
 *     - merge-pathlist: For options such as --include and --config, make a merged list
 *       of options from all contexts; works like the 'merge' flag, but also handles string
 *       values separated by the PATH_SEPARATOR.
 *     - merge-associative: Like 'merge-pathlist', but key values are preserved.
 *     - propagate-cli-value: Used to tell backend invoke to include the value for
 *       this item as specified on the cli.  This can either override 'context'
 *       (e.g., propagate --include from cli value instead of DRUSH_INCLUDE context),
 *       or for an independent global setting (e.g. --user)
 *     - description: The help text for this item. displayed by `drush help`.
 */
function drush_get_global_options($brief = FALSE) {
  $options['root']               = array('short-form' => 'r', 'short-has-arg' => TRUE, 'never-post' => TRUE, 'description' => "Drupal root directory to use (default: current directory).", 'example-value' => 'path');
  $options['uri']                = array('short-form' => 'l', 'short-has-arg' => TRUE, 'never-post' => TRUE, 'description' => 'URI of the drupal site to use (only needed in multisite environments or when running on an alternate port).', 'example-value' => 'http://example.com:8888');
  $options['verbose']            = array('short-form' => 'v', 'context' => 'DRUSH_VERBOSE', 'description' => 'Display extra information about the command.');
  $options['debug']              = array('short-form' => 'd', 'context' => 'DRUSH_DEBUG', 'description' => 'Display even more information, including internal messages.');
  $options['yes']                = array('short-form' => 'y', 'context' => 'DRUSH_AFFIRMATIVE', 'description' => "Assume 'yes' as answer to all prompts.");
  $options['no']                 = array('short-form' => 'n', 'context' => 'DRUSH_NEGATIVE', 'description' => "Assume 'no' as answer to all prompts.");
  $options['simulate']           = array('short-form' => 's', 'context' => 'DRUSH_SIMULATE', 'description' => "Simulate all relevant actions (don't actually change the system).");
  $options['pipe']               = array('short-form' => 'p', 'description' => "Emit a compact representation of the command for scripting.");
  $options['help']               = array('short-form' => 'h', 'description' => "This help system.");
  $options['version']            = array('description' => "Show drush version.");
  $options['php']                = array('description' => "The absolute path to your PHP intepreter, if not 'php' in the path.", 'example-value' => '/path/to/file');
  $options['interactive']        = array('short-form' => 'ia', 'description' => "Force interactive mode for commands run on multiple targets (e.g. `drush @site1,@site2 cc --ia`).");

  if (!$brief) {
    $options['quiet']               = array('short-form' => 'q', 'description' => 'Suppress non-error messages.');
    $options['include']             = array('short-form' => 'i', 'short-has-arg' => TRUE, 'context' => 'DRUSH_INCLUDE', 'local-context-only' => TRUE, 'never-post' => TRUE, 'propagate-cli-value' => TRUE, 'merge-pathlist' => TRUE, 'description' => "A list of additional directory paths to search for drush commands.", 'example-value' => '/path/to/directory');
    $options['config']              = array('short-form' => 'c', 'short-has-arg' => TRUE, 'context' => 'DRUSH_CONFIG', 'local-context-only' => TRUE, 'merge-pathlist' => TRUE, 'description' => "Specify an additional config file to load. See example.drushrc.php.");
    $options['user']                = array('short-form' => 'u', 'short-has-arg' => TRUE, 'propagate-cli-value' => TRUE, 'description' => "Specify a Drupal user to login with. May be a name or a number.", 'example-value' => 'name_or_number');
    $options['backend']             = array('short-form' => 'b', 'never-propagate' => TRUE, 'description' => "Hide all output and return structured data (internal use only).");
    $options['invoke']              = array('hidden' => TRUE, 'description' => 'Invoked from a script; skip option verification.');
    $options['choice']              = array('description' => "Provide an answer to a multiple-choice prompt.", 'example-value' => 'number');
    $options['variables']           = array('description' => "Comma delimited list of name=value pairs. These values take precedence even over settings.php variable overrides.", 'example-value' => 'foo=bar,baz=yaz');
    $options['search-depth']        = array('description' => "Control the depth that drush will search for alias files.", 'example-value' => 'number');
    $options['ignored-modules']     = array('description' => "Exclude some modules from consideration when searching for drush command files.");
    $options['no-label']            = array('description' => "Remove the site label that drush includes in multi-site command output(e.g. `drush @site1,@site2 status`).");
    $options['nocolor']             = array('context' => 'DRUSH_NOCOLOR', 'propagate-cli-value' => TRUE, 'description' => "Suppress color highlighting on log messages.");
    $options['show-passwords']      = array('description' => "Show database passwords in commands that display connection information.");
    $options['show-invoke']         = array('description' => "Show all function names which could have been called for the current command. See drush_invoke().");
    $options['watchdog']            = array('description' => "Control logging of Drupal's watchdog() to drush log. Recognized values are 'log', 'print', 'disabled'. Defaults to log. 'print' shows calls to admin but does not add them to the log.");
    $options['cache-default-class'] = array('description' => "A cache backend class that implements DrushCacheInterface.");
    $options['cache-class-<bin>']   = array('description' => "A cache backend class that implements DrushCacheInterface to use for a specific cache bin.");
    $options['early']               = array('description' => "Include a file (with relative or full path) and call the drush_early_hook() function (where 'hook' is the filename). The function is called pre-bootstrap and offers an opportunity to alter the drush bootstrap environment or process (returning FALSE from the function will continue the bootstrap), or return output very rapidly (e.g. from caches). See includes/complete.inc for an example.");
    $options['alias-path']          = array('context' => 'ALIAS_PATH', 'local-context-only' => TRUE, 'merge-pathlist' => TRUE, 'propagate-cli-value' => TRUE, 'description' => "Specifies the list of paths where drush will search for alias files. Separate paths with ':'.");
    $options['backup-location']     = array('description' => "Specifies the directory where drush will store backups.");
    $options['confirm-rollback']    = array('description' => 'Wait for confirmation before doing a rollback when something goes wrong.');
    $options['complete-debug']      = array('hidden' => TRUE, 'description' => "Turn on debug mode forf completion code");
    $options['php-options']         = array('description' => "Options to pass to php when running drush.  Only effective when using the `drush` script.");
    $options['deferred-sanitization'] = array('hidden' => TRUE, 'description' => "Defer calculating the sanitization operations until after the database has been copied. This is done automatically if the source database is remote.");
    $options['remote-host']         = array('hidden' => TRUE, 'description' => 'Remote site to execute drush command on. Managed by site alias.');
    $options['remote-user']         = array('hidden' => TRUE, 'description' => 'User account to use with a remote drush command. Managed by site alias.');
    $options['remote-os']           = array('hidden' => TRUE, 'description' => 'The operating system used on the remote host. Managed by site alias.');
    $options['site-list']           = array('hidden' => TRUE, 'description' => 'List of sites to run commands on. Managed by site alias.');
    $options['reserve-margin']      = array('hidden' => TRUE, 'description' => 'Remove columns from formatted opions. Managed by multi-site command handling.');
    $options['strict']              = array('hidden' => TRUE, 'description' => 'Check requirements more strictly / remove backwards-compatibility features for unit tests.');
    $options['command-specific']    = array('hidden' => TRUE, 'merge-associative' => TRUE, 'description' => 'Command-specific options.');
    $options['site-aliases']        = array('hidden' => TRUE, 'merge-associative' => TRUE, 'description' => 'List of site aliases.');
    $options['shell-aliases']       = array('hidden' => TRUE, 'merge' => TRUE, 'never-propagate' => TRUE, 'description' => 'List of shell aliases.');
    $options['path-aliases']        = array('hidden' => TRUE, 'never-propagate' => TRUE, 'description' => 'Path aliases from site alias.');
    $options['ssh-options']         = array('never-propagate' => TRUE, 'description' => 'A string of extra options that will be passed to the ssh command (e.g. "-p 100")');

    $command = array(
      'options' => $options,
      '#brief' => FALSE,
    ) + drush_command_defaults('global-options', 'global_options', __FILE__);
    drush_command_invoke_all_ref('drush_help_alter', $command);

    $options = $command['options'];
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
  drush_print_prompt((string)$msg . " (y/n): ", $indent);

  // Automatically accept confirmations if the --yes argument was supplied.
  if (drush_get_context('DRUSH_AFFIRMATIVE')) {
    drush_print("y");
    return TRUE;
  }
  // Automatically cancel confirmations if the --no argument was supplied.
  elseif (drush_get_context('DRUSH_NEGATIVE')) {
    drush_print("n");
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
    drush_print_prompt((string)$msg . " (y/n): ", $indent);
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
function drush_choice($options, $prompt = 'Enter a number.', $label = '!value', $widths = array()) {
  drush_print(dt($prompt));

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
  drush_print_table($rows, FALSE, $widths);
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
    if (isset($max) && count($selections) > $max) {
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
 * @param $password
 *   If TRUE, surpress printing of the input.
 *
 * @see drush_confirm()
 */
function drush_prompt($prompt, $default = NULL, $required = TRUE, $password = FALSE) {
  if (!is_null($default)) {
    $prompt .= " [" . $default . "]";
  }
  $prompt .= ": ";

  drush_print_prompt($prompt);

  if (drush_get_context('DRUSH_AFFIRMATIVE')) {
    return $default;
  }

  $stdin = fopen('php://stdin', 'r');

  if ($password) drush_shell_exec("stty -echo");

  stream_set_blocking($stdin, TRUE);
  while (($line = fgets($stdin)) !== FALSE) {
    $line = trim($line);
    if ($line === "") {
      $line = $default;
    }
    if ($line || !$required) {
      break;
    }
    drush_print_prompt($prompt);
  }
  fclose($stdin);
  if ($password) {
    drush_shell_exec("stty echo");
    print "\n";
  }
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
  $args_printed = array();
  $args = func_get_args();
  array_shift($args); // Skip function name
  foreach ($args as $arg) {
    $args_printed[] = is_scalar($arg) ? $arg : (is_array($arg) ? 'Array' : 'Object');
  }

  // Special checking for drush_op('system')
  if ($function == 'system') {
    drush_log(dt("Do not call drush_op('system'); use drush_op_system instead"), 'debug');
  }

  if (drush_get_context('DRUSH_VERBOSE') || drush_get_context('DRUSH_SIMULATE')) {
     drush_log(sprintf("Calling %s(%s)", $function, implode(", ", $args_printed)), 'debug');
  }

  if (drush_get_context('DRUSH_SIMULATE')) {
    return TRUE;
  }

  return call_user_func_array($function, $args);
}

/**
 * Download a file using wget, curl or file_get_contents, or via download cache.
 *
 * @param string $url
 *   The url of the file to download.
 * @param string $destination
 *   The name of the file to be saved, which may include the full path.
 *   Optional, if omitted the filename will be extracted from the url and the
 *   file downloaded to the current working directory (Drupal root if
 *   bootstrapped).
 * @param integer $cache_duration
 *   The acceptable age of a cached file. If cached file is too old, a fetch
 *   will occur and cache will be updated. Optional, if ommitted the file will
 *   be fetched directly.
 *
 * @return string
 *   The path to the downloaded file, or FALSE if the file could not be
 *   downloaded.
 */
function drush_download_file($url, $destination = FALSE, $cache_duration = 0) {
  // Generate destination of omitted.
  if (!$destination) {
    $file = basename(current(explode('?', $url, 2)));
    $destination = getcwd() . '/' . basename($file);
  }

  if (drush_get_option('cache') && $cache_duration !== 0 && $cache_dir = drush_directory_cache('download')) {
    $cache_name = str_replace(array(':', '/', '?', '='), '-', $url);
    $cache_file = $cache_dir . "/" . $cache_name;
    // Check for cached, unexpired file.
    if (file_exists($cache_file) && filectime($cache_file) > ($_SERVER['REQUEST_TIME']-$cache_duration)) {
      drush_log(dt('!name retrieved from cache.', array('!name' => $cache_name)));
    }
    else {
      if (_drush_download_file($url, $cache_file, TRUE)) {
        // Cache was set just by downloading file to right location.
      }
      elseif (file_exists($cache_file)) {
        drush_log(dt('!name retrieved from an expired cache since refresh failed.', array('!name' => $cache_name)), 'warning');
      }
      else {
        $cache_file = FALSE;
      }
    }

    if ($cache_file && copy($cache_file, $destination)) {
      // Copy cached file to the destination
      return $destination;
    }
  }
  elseif ($return = _drush_download_file($url, $destination)) {
    drush_register_file_for_deletion($return);
    return $return;
  }

  // Unable to retrieve from cache nor download.
  return FALSE;
}

/**
 * Download a file using wget, curl or file_get_contents. Does not use download
 * cache.
 *
 * @param string $url
 *   The url of the file to download.
 * @param string $destination
 *   The name of the file to be saved, which may include the full path.
 * @param boolean $overwrite
 *   Overwrite any file thats already at the destination.
 * @return string
 *   The path to the downloaded file, or FALSE if the file could not be
 *   downloaded.
 */
function _drush_download_file($url, $destination, $overwrite = TRUE) {
  static $use_wget;
  if ($use_wget === NULL) {
    $use_wget = drush_shell_exec('which wget');
  }

  $destination_tmp = drush_tempnam('download_file');
  if ($use_wget) {
    drush_shell_exec("wget -q --timeout=30 -O %s %s", $destination_tmp, $url);
  }
  else {
    drush_shell_exec("curl --fail -s -L --connect-timeout 30 -o %s %s", $destination_tmp, $url);
  }
  if (!drush_file_not_empty($destination_tmp) && $file = @file_get_contents($url)) {
    @file_put_contents($destination_tmp, $file);
  }
  if (!drush_file_not_empty($destination_tmp)) {
    // Download failed.
    return FALSE;
  }

  drush_move_dir($destination_tmp, $destination, $overwrite);
  return $destination;
}

/**
 * Determines the MIME content type of the specified file.
 *
 * The power of this function depends on whether the PHP installation
 * has either mime_content_type() or finfo installed -- if not, only tar,
 * gz, zip and bzip2 types can be detected.
 *
 * If mime type can't be obtained, an error will be set.
 *
 * @return mixed
 *   The MIME content type of the file or FALSE.
 */
function drush_mime_content_type($filename) {
  // 1. Try to use php built-ins.
  $content_type = FALSE;
  if (class_exists('finfo')) {
    drush_log(dt('Fileinfo extension available.'), 'debug');
    // For pecl's fileinfo on php 5.2 there is quite some inconsistency in
    // distributions and loading of magic files.
    // TODO: remove @ and use FILEINFO_MIME_TYPE when drush requires php 5.3
    $finfo = @new finfo(FILEINFO_MIME);
    $ct = @$finfo->file($filename);
    if ($ct) {
      // We only want the first part, before the ;
      $content_type = current(explode(';', $ct));
    }
  }

  // TODO: to be removed in php 5.3 (deprecated).
  if ((!$content_type) && (function_exists('mime_content_type'))) {
    drush_log(dt('mime_magic support enabled.'), 'debug');
    $content_type = trim(mime_content_type($filename));
  }

  if (!$content_type) {
    drush_log(dt('No fileinfo or mime_magic support available.'), 'debug');
  }
  elseif ($content_type == 'application/octet-stream') {
    drush_log(dt('Mime type for !file is application/octet-stream.', array('!file' => $filename)), 'debug');
    $content_type = FALSE;
  }

  // 2. if PHP's built-ins aren't present or PHP is configured in such a way
  // that all these files are considered octet-stream (e.g with mod_mime_magic
  // and an http conf that's serving all archives as octet-stream for other
  // reasons) we'll detect (a few select) mime types on our own by examing the
  // file's magic header bytes.
  if (!$content_type) {
    drush_log(dt('Examining !file headers.', array('!file' => $filename)), 'debug');
    if ($file = fopen($filename, 'rb')) {
      $first = fread($file, 2);
      fclose($file);

      if ($first !== FALSE) {
        // Interpret the two bytes as a little endian 16-bit unsigned int.
        $data = unpack('v', $first);
        switch ($data[1]) {
          case 0x8b1f:
            // First two bytes of gzip files are 0x1f, 0x8b (little-endian).
            // See http://www.gzip.org/zlib/rfc-gzip.html#header-trailer
            $content_type = 'application/x-gzip';
            break;

          case 0x4b50:
            // First two bytes of zip files are 0x50, 0x4b ('PK') (little-endian).
            // See http://en.wikipedia.org/wiki/Zip_(file_format)#File_headers
            $content_type = 'application/zip';
            break;

          case 0x5a42:
            // First two bytes of bzip2 files are 0x5a, 0x42 ('BZ') (big-endian).
            // See http://en.wikipedia.org/wiki/Bzip2#File_format
            $content_type = 'application/x-bzip2';
            break;

          default:
            drush_log(dt('Unable to determine mime type from header bytes 0x!hex of !file.', array('!hex' => dechex($data[1]), '!file' => $filename,), 'debug'));
        }
      }
      else {
        drush_log(dt('Unable to read !file.', array('!file' => $filename)), 'warning');
      }
    }
    else {
      drush_log(dt('Unable to open !file.', array('!file' => $filename)), 'warning');
    }
  }

  // 3. Lastly if above methods didn't work, try to guess the mime type from
  // the file extension. This is useful if the file has no identificable magic
  // header bytes (for example tarballs).
  if (!$content_type) {
    drush_log(dt('Examining !file extension.', array('!file' => $filename)), 'debug');

    // Remove querystring from the filename, if present.
    $filename = basename(current(explode('?', $filename, 2)));
    $extension_mimetype = array(
      '.tar'     => 'application/x-tar',
    );
    foreach ($extension_mimetype as $extension => $ct) {
      if (substr($filename, -strlen($extension)) === $extension) {
        $content_type = $ct;
        break;
      }
    }
  }

  if ($content_type) {
    drush_log(dt('Mime type for !file is !mt', array('!file' => $filename, '!mt' => $content_type)), 'notice');
    return $content_type;
  }

  return drush_set_error('MIME_CONTENT_TYPE_UNKNOWN', dt('Unable to determine mime type for !file.', array('!file' => $filename)));
}

/**
 * Check whether a file is a supported tarball.
 *
 * @return mixed
 *   The file content type if it's a tarball. FALSE otherwise.
 */
function drush_file_is_tarball($path) {
  $content_type = drush_mime_content_type($path);
  $supported = array(
    'application/x-bzip2',
    'application/x-gzip',
    'application/x-tar',
    'application/x-zip',
    'application/zip',
  );
  if (in_array($content_type, $supported)) {
    return $content_type;
  }
  return FALSE;
}

/**
 * Extract a tarball.
 *
 * @param string $path
 *   Path to the archive to be extracted.
 * @param string $destination
 *   The destination directory the tarball should be extracted into.
 *   Optional, if ommitted the tarball directory will be used as destination.
 * @param boolean $listing
 *   If TRUE, a listing of the tar contents will be returned on success.
 *
 * @return mixed
 *   TRUE on success, FALSE on fail. If $listing is TRUE, a file listing of the
 *   tarball is returned if the extraction reported success, instead of TRUE.
 */
function drush_tarball_extract($path, $destination = FALSE, $listing = FALSE) {
  // Check if tarball is supported.
  if (!($mimetype = drush_file_is_tarball($path))) {
    return drush_set_error('TARBALL_EXTRACT_UNKNOWN_FORMAT', dt('Unable to extract !path. Unknown archive format.', array('!path' => $path)));
  }

  // Check if destination is valid.
  if (!$destination) {
    $destination = dirname($path);
  }
  if (!drush_mkdir($destination)) {
    // drush_mkdir already set an error.
    return FALSE;
  }

  // Perform the extraction of a zip file.
  if (($mimetype == 'application/zip') || ($mimetype == 'application/x-zip')) {
    $return = drush_shell_cd_and_exec(dirname($path), "unzip %s -d %s", $path, $destination);
    if (!$return) {
      return drush_set_error('DRUSH_TARBALL_EXTRACT_ERROR', dt('Unable to unzip !filename.', array('!filename' => $path)));
    }
    if ($listing) {
      // unzip prefixes the file listing output with a header line,
      // and prefixes each line with a verb representing the compression type.
      $output = drush_shell_exec_output();
      // Remove the header line.
      array_shift($output);
      // Remove the prefix verb from each line.
      $output = array_map(create_function('$str', 'return substr($str, strpos($str, ":") + 3 + ' . strlen(dirname($path)) . ');'), $output);
      // Remove any remaining blank lines.
      $return = array_filter($output, create_function('$str', 'return $str != "";'));
    }
  }
  // Otherwise we have a possibly-compressed Tar file.
  // If we are not on Windows, then try to do "tar" in a single operation.
  elseif (!drush_is_windows()) {
    $tar = drush_get_tar_executable();
    $tar_compression_flag = '';
    if ($mimetype == 'application/x-gzip') {
      $tar_compression_flag = 'z';
    }
    elseif ($mimetype == 'application/x-bzip2') {
      $tar_compression_flag = 'j';
    }

    $return = drush_shell_cd_and_exec(dirname($path), "$tar -C %s -x%sf %s", $destination, $tar_compression_flag, basename($path));
    if (!$return) {
      return drush_set_error('DRUSH_TARBALL_EXTRACT_ERROR', dt('Unable to untar !filename.', array('!filename' => $path)));
    }
    if ($listing) {
      // We use a separate tar -tf instead of -xvf above because
      // the output is not the same in Mac.
      drush_shell_cd_and_exec(dirname($path), "$tar -t%sf %s", $tar_compression_flag, basename($path));
      $return = drush_shell_exec_output();
    }
  }
  // In windows, do the extraction by its primitive steps.
  else {
    // 1. copy the source tarball to the destination directory. Rename to a
    // temp name in case the destination directory == dirname($path)
    $tmpfile = drush_tempnam(basename($path), $destination);
    drush_copy_dir($path, $tmpfile, FILE_EXISTS_OVERWRITE);

    // 2. uncompress the tarball, if compressed.
    if (($mimetype == 'application/x-gzip') || ($mimetype == 'application/x-bzip2')) {
      if ($mimetype == 'application/x-gzip') {
        $compressed = $tmpfile . '.gz';
        // We used to use gzip --decompress in --stdout > out, but the output
        // redirection sometimes failed on Windows for some binary output.
        $command = 'gzip --decompress %s';
      }
      elseif ($mimetype == 'application/x-bzip2') {
        $compressed = $tmpfile . '.bz2';
        $command = 'bzip2 --decompress %s';
      }
      drush_op('rename', $tmpfile, $compressed);
      $return = drush_shell_cd_and_exec(dirname($compressed), $command, $compressed);
      if (!$return || !file_exists($tmpfile)) {
        return drush_set_error('DRUSH_TARBALL_EXTRACT_ERROR', dt('Unable to decompress !filename.', array('!filename' => $compressed)));
      }
    }

    // 3. Untar.
    $tar = drush_get_tar_executable();
    $return = drush_shell_cd_and_exec(dirname($tmpfile), "$tar -xvf %s", basename($tmpfile));
    if (!$return) {
      return drush_set_error('DRUSH_TARBALL_EXTRACT_ERROR', dt('Unable to untar !filename.', array('!filename' => $tmpfile)));
    }
    if ($listing) {
      $return = drush_shell_exec_output();
      // Cut off the 'x ' prefix for the each line of the tar output
      // See http://drupal.org/node/1775520
      foreach($return as &$line) {
        if(strpos($line, "x ") === 0)
          $line = substr($line, 2);
      }
    }

    // Remove the temporary file so the md5 hash is accurate.
    unlink($tmpfile);
  }

  return $return;
}

/**
 * Download and extract a tarball to the lib directory.
 *
 * Checks for reported success, but callers should normally check for existence
 * of specific expected file(s) in the library.
 *
 * @param string $url
 *   The URL to the library tarball.
 *
 * @return boolean
 *   TRUE is the download and extraction reported success, FALSE otherwise.
 */
function drush_lib_fetch($url) {
  $lib = drush_get_option('lib', DRUSH_BASE_PATH . '/lib');
  if (!is_writable($lib)) {
    return drush_set_error('DRUSH_LIB_UNWRITABLE', dt("Drush needs to download a library from !url in order to function, and the attempt to download this file automatically failed because you do not have permission to write to the library directory !path. To continue you will need to manually download the package from !url, extract it, and copy the directory into your !path directory.", array('!path' => $lib, '!url' => $url)));
  }

  $destination = $lib . '/drush-library-' . mt_rand();
  $path = drush_download_file($url, $destination);
  if (!$path) {
    return FALSE;
  }
  return drush_tarball_extract($path);
}

/**
 * @defgroup commandprocessing Command processing functions.
 * @{
 *
 * These functions manage command processing by the
 * main function in drush.php.
 */

/**
 * Handle any command preprocessing that may need to be done, including
 * potentially redispatching the command immediately (e.g. for remote
 * commands).
 *
 * @return
 *   TRUE if the command was handled remotely.
 */
function drush_preflight_command_dispatch() {
  $interactive = drush_get_option('interactive', FALSE);

  // The command will be executed remotely if the --remote-host flag
  // is set; note that if a site alias is provided on the command line,
  // and the site alias references a remote server, then the --remote-host
  // option will be set when the site alias is processed.
  // @see drush_sitealias_check_arg
  $remote_host = drush_get_option('remote-host');
  // Get the command early so that we can allow commands to directly handle remote aliases if they wish
  $command = drush_parse_command();
  if (isset($command)) {
    drush_command_default_options($command);
  }
  // If the command sets the 'strict-option-handling' flag, then we will remove
  // any cli options that appear after the command name form the 'cli' context.
  // The cli options that appear before the command name are stored in the
  // 'DRUSH_GLOBAL_CLI_OPTIONS' context, so we will just overwrite the cli context
  // with this, after doing the neccessary fixup from short-form to long-form options.
  // After we do that, we put back any local drush options identified by $command['options'].
  if (is_array($command) && !empty($command['strict-option-handling'])) {
    $cli_options = drush_get_context('DRUSH_GLOBAL_CLI_OPTIONS', array());
    // Now we are going to sort out any options that exist in $command['options'];
    // we will remove these from DRUSH_COMMAND_ARGS and put them back into the
    // cli options.
    $cli_context = drush_get_context('cli');
    $remove_from_command_args = array();
    foreach ($command['options'] as $option => $info) {
      if (array_key_exists($option, $cli_context)) {
        $cli_options[$option] = $cli_context[$option];
        $remove_from_command_args[$option] = $option;
      }
    }
    if (!empty($remove_from_command_args)) {
      $drush_command_args = array();
      foreach (drush_get_context('DRUSH_COMMAND_ARGS') as $arg) {
        if (!_drush_should_remove_command_arg($arg, $remove_from_command_args)) {
          $drush_command_args[] = $arg;
        }
      }
      drush_set_context('DRUSH_COMMAND_ARGS', $drush_command_args);
    }
    drush_expand_short_form_options($cli_options);
    drush_set_context('cli', $cli_options);
    _drush_bootstrap_global_options();
  }
  // If the command sets the 'handle-remote-commands' flag, then we will short-circuit
  // remote command dispatching and site-list command dispatching, and always let
  // the command handler run on the local machine.
  if (is_array($command) && !empty($command['handle-remote-commands'])) {
    return FALSE;
  }
  if (isset($remote_host)) {
    $args = drush_get_arguments();
    $command_name = array_shift($args);
    $remote_user = drush_get_option('remote-user');

    // Force interactive mode if there is a single remote target.  #interactive is added by drush_do_command_redispatch
    drush_set_option('interactive', TRUE);
    $values = drush_do_command_redispatch($command_name, $args, $remote_host, $remote_user);
    // In 'interactive' mode, $values is the result code from drush_shell_proc_open.
    // TODO: in _drush_backend_invoke, return array('error_status' => $ret) instead for uniformity.
    if (!is_array($values) && ($values != 0)) {
      // Force an error result code.  Note that drush_shutdown() will still run.
      drush_set_context('DRUSH_EXECUTION_COMPLETED', TRUE);
      exit($values);
    }
    return TRUE;
  }
  // If the --site-list flag is set, then we will execute the specified
  // command once for every site listed in the site list.
  $site_list = drush_get_option('site-list');
  if (isset($site_list)) {
    if (!is_array($site_list)) {
      $site_list = explode(',', $site_list);
    }
    $site_record = array('site-list' => $site_list);
    $args = drush_get_arguments();

    if (!drush_get_context('DRUSH_SIMULATE') && !$interactive) {
      drush_print(dt("You are about to execute '!command' non-interactively (--yes forced) on all of the following targets:", array('!command' => implode(" ", $args))));
      foreach ($site_list as $one_destination) {
        drush_print(dt('  !target', array('!target' => $one_destination)));
      }

      if (drush_confirm('Continue? ') === FALSE) {
        drush_user_abort();
        return TRUE;
      }
    }
    $command_name = array_shift($args);
    $multi_options = drush_get_context('cli');
    $backend_options = array();
    if (drush_get_option('pipe') || drush_get_option('interactive')) {
      $backend_options['interactive'] = TRUE;
    }
    if (drush_get_option('no-label', FALSE)) {
      $backend_options['no-label'] = TRUE;
    }
    $values = drush_invoke_process($site_record, $command_name, $args, $multi_options, $backend_options);
    return TRUE;
  }
  return FALSE;
}

/**
 * Determine whether or not an argument should be removed from the
 * DRUSH_COMMAND_ARGS context.  This method is used when a Drush
 * command has set the 'strict-option-handling' flag indicating
 * that it will pass through all commandline arguments and any
 * additional options (not known to Drush) to some shell command.
 *
 * Take as an example the following call to core-rsync:
 *
 *   drush --yes core-rsync -v -az --exclude-paths='.git:.svn' local-files/ @site:%files
 *
 * In this instance:
 *
 *   --yes is a global Drush option
 *
 *   -v is an rsync option.  It will make rsync run in verbose mode,
 *     but will not make Drush run in verbose mode due to the fact that
 *     core-rsync sets the 'strict-option-handling' flag.
 *
 *   --exclude-paths is a local Drush option.  It will be converted by
 *     Drush into --exclude='.git' and --exclude='.svn', and then passed
 *     on to the rsync command.
 *
 * The parameter $arg passed to this function is one of the elements
 * of DRUSH_COMMAND_ARGS.  It will have values such as:
 *   -v
 *   -az
 *   --exclude-paths='.git:.svn'
 *   local-files/
 *   @site:%files
 *
 * Our job in this function is to determine if $arg should be removed
 * by virtue of appearing in $removal_list.  $removal_list is an array
 * that will contain values such as 'exclude-paths'.  Both the key and
 * the value of $removal_list is the same.
 */
function _drush_should_remove_command_arg($arg, $removal_list) {
  foreach ($removal_list as $candidate) {
    if (($arg == "-$candidate") ||
        ($arg == "--$candidate") ||
        (substr($arg,0,strlen($candidate)+3) == "--$candidate=") ) {
      return TRUE;
    }
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
      $values = drush_invoke_process('@self', $command, array($source_spec . $source_path, $target_spec . $destination_path), $data);
      drush_log(dt('Backend invoke is complete'));
    }
  }

  return $is_multiple_command;
}

/**
 * Run a command on the site specified by the provided command record.
 *
 * The standard function that provides this service is called
 * drush_invoke_process.  Please call the standard function.
 *
 * @param backend_options
 *   TRUE - integrate errors
 *   FALSE - do not integrate errors
 *   array - @see drush_backend_invoke_concurrent
 *
function drush_do_site_command($site_record, $command, $args = array(), $command_options = array(), $backend_options = FALSE) {
  return drush_invoke_process($site_record, $command, $args, $command_options, $backend_options);
}
 */

/**
 * Redispatch the specified command using the same
 * options that were passed to this invocation of drush.
 */
function drush_do_command_redispatch($command, $args = array(), $remote_host = NULL, $remote_user = NULL, $drush_path = NULL) {
  $command_options = drush_redispatch_get_options();

  // If the path to drush was supplied, then use it to invoke the new command.
  if ($drush_path == NULL) {
    $drush_path = drush_get_option('drush-script');
    if (!isset($drush_path)) {
      $drush_folder = drush_get_option('drush');
      if (isset($drush)) {
        $drush_path = $drush_folder . '/drush';
      }
    }
  }
  $backend_options = array('drush-script' => $drush_path, 'remote-host' => $remote_host, 'remote-user' => $remote_user, 'integrate' => TRUE);
  if (drush_get_option('interactive')) {
    $backend_options['interactive'] = TRUE;
  }

  // Run the command in a new process.
  drush_log(dt('Begin redispatch via invoke process'));
  $values = drush_invoke_process('@self', $command, $args, $command_options, $backend_options);
  drush_log(dt('Invoke process is complete'));

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
  drush_backend_packet('log', $entry);

  return $callback($entry);
}

/**
 * Backend command callback. Add a log message to the log history.
 *
 * @param entry
 *   The log entry.
 */
function drush_backend_packet_log($entry, $backend_options) {
  if (!$backend_options['log']) {
    return;
  }
  if (!is_string($entry['message'])) {
    $entry['message'] = implode("\n", (array)$entry['message']);
  }
  $entry['message'] = $entry['message'];
  $log =& drush_get_context('DRUSH_LOG', array());
  $log[] = $entry;
  // Yes, this looks odd, but we might in fact be a backend command
  // that ran another backend command.
  drush_backend_packet('log', $entry);
  if (array_key_exists('#output-label', $backend_options)) {
    $entry['message'] = $backend_options['#output-label'] . $entry['message'];
  }

  // If integrate is FALSE, then log messages are stored in DRUSH_LOG,
  // but are -not- printed to the console.
  if ($backend_options['integrate']) {
    $callback = drush_get_context('DRUSH_LOG_CALLBACK', '_drush_print_log');
    return $callback($entry);
  }
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
  drush_log(print_r($object, TRUE));
}

/**
 * Display the pipe output for the current request.
 */
function drush_pipe_output() {
  $pipe = drush_get_context('DRUSH_PIPE_BUFFER');
  if (!empty($pipe)) {
    drush_print_r($pipe, NULL, FALSE);
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
      // In quiet mode, suppress progress messages
      if (drush_get_context('DRUSH_QUIET')) {
        return TRUE;
      }
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
  // be returned in the JSON encoded associative array.
  if (drush_get_context('DRUSH_BACKEND')) {
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
    drush_print_table($table, TRUE, array(), STDERR);
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
function drush_set_error($error, $message = null, $output_label = "") {
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
  if (!drush_backend_packet('set_error', array('error' => $error, 'message' => $message))) {
    drush_log(($message) ? $output_label . $message : $output_label . $error, 'error', $error);
  }

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
 * Clear error context.
 */
function drush_clear_error() {
  drush_set_context('DRUSH_ERROR_CODE', DRUSH_SUCCESS);
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
 *
 * This also records the previous error_reporting setting, in
 * case it wasn't recorded previously.
 *
 * @see drush_errors_off()
 */
function drush_errors_off() {
  drush_get_context('DRUSH_ERROR_REPORTING', error_reporting(0));
  ini_set('display_errors', FALSE);
}

/**
 * Turn PHP error handling on.
 *
 * We default to error_reporting() here just in
 * case drush_errors_on() is called before drush_errors_off() and
 * the context is not yet set.
 *
 * @arg $errors string
 *   The default error level to set in drush. This error level will be
 *   carried through further drush_errors_on()/off() calls even if not
 *   provided in later calls.
 *
 * @see error_reporting()
 * @see drush_errors_off()
 */
function drush_errors_on($errors = null) {
  if (is_null($errors)) {
    $errors = error_reporting();
  }
  else {
    drush_set_context('DRUSH_ERROR_REPORTING', $errors);
  }
  error_reporting(drush_get_context('DRUSH_ERROR_REPORTING', $errors));
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
 * Return a list of VCSs reserved files and directories.
 */
function drush_version_control_reserved_files() {
  static $files = FALSE;

  if (!$files) {
    // Also support VCSs that are not drush vc engines.
    $files = array('.git', '.gitignore', '.hg', '.hgignore', '.hgrags');
    $engine_info = drush_get_engines('version_control');
    $vcs = array_keys($engine_info['engines']);
    foreach ($vcs as $name) {
      $version_control = drush_include_engine('version_control', $name);
      $files = array_merge($files, $version_control->reserved_files());
    }
  }

  return $files;
}

/**
 * Generate a random alphanumeric password.  Copied from user.module.
 */
function drush_generate_password($length = 10) {
  // This variable contains the list of allowable characters for the
  // password. Note that the number 0 and the letter 'O' have been
  // removed to avoid confusion between the two. The same is true
  // of 'I', 1, and 'l'.
  $allowable_characters = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

  // Zero-based count of characters in the allowable list:
  $len = strlen($allowable_characters) - 1;

  // Declare the password as a blank string.
  $pass = '';

  // Loop the number of times specified by $length.
  for ($i = 0; $i < $length; $i++) {

    // Each iteration, pick a random character from the
    // allowable string and append it to the password:
    $pass .= $allowable_characters[mt_rand(0, $len)];
  }

  return $pass;
}

/**
 * Form an associative array from a linear array.
 *
 * This function walks through the provided array and constructs an associative
 * array out of it. The keys of the resulting array will be the values of the
 * input array. The values will be the same as the keys unless a function is
 * specified, in which case the output of the function is used for the values
 * instead.
 *
 * @param $array
 *   A linear array.
 * @param $function
 *   A name of a function to apply to all values before output.
 *
 * @return
 *   An associative array.
 */
function drush_map_assoc($array, $function = NULL) {
  // array_combine() fails with empty arrays:
  // http://bugs.php.net/bug.php?id=34857.
  $array = !empty($array) ? array_combine($array, $array) : array();
  if (is_callable($function)) {
    $array = array_map($function, $array);
  }
  return $array;
}
/**
 * Clears completion caches.
 *
 * If called with no parameters the entire complete cache will be cleared.
 * If called with just the $type parameter the global cache for that type will
 * be cleared (in the site context, if any). If called with both $type and
 * $command parameters the command cache of that type will be cleared  (in the
 * site context, if any).
 *
 * This is included in drush.inc as complete.inc is only loaded conditionally.
 *
 * @param $type
 *   The completion type (optional).
 * @param $command
 *   The command name (optional), if command specific cache is to be cleared.
 *   If specifying a command, $type is not optional.
 */
function drush_complete_cache_clear($type = NULL, $command = NULL) {
  require_once DRUSH_BASE_PATH . '/includes/complete.inc';
  if ($type) {
    drush_cache_clear_all(drush_complete_cache_cid($type, $command), 'complete');
    return;
  }
  // No type or command, so clear the entire complete cache.
  drush_cache_clear_all('*', 'complete', TRUE);
}
