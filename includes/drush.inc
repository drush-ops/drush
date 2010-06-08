<?php
// $Id$

/**
 * @file
 * The drush API implementation and helpers.
 */

/**
 * The number of bytes in a kilobyte. Copied from Drupal.
 */
define('DRUSH_DRUPAL_KILOBYTE', 1024);


/**
 * Dispatch a given set of commands.
 * Modules can add commands by implementing hook_drush_command().
 *
 * @param
 *   Command whose callback you want to call, defaults to current command.
 */
function drush_dispatch($command = NULL) {
  $command = ($command) ? $command : drush_get_command();
  $return = FALSE;

  if ($command) {
    // Add command-specific options, if applicable
    drush_command_default_options($command);

    if (isset($command['must-replace-spaces'])) {
      $required_name = str_replace(' ', '-', $command['command']);
      drush_set_error(dt('Notice: "!name" must be renamed to "!requiredname" in order to work with this version of drush.  If you are the maintainer for the module that defines this command, please rename it immediately.  If you are a user of this command, you may enable spaces in commands for a while by setting "allow-spaces-in-commands" in your drush configuration file.  See example.drushrc.php.', array('!name' => $command['command'], '!requiredname' => $required_name)));
      return FALSE;
    }
    // Print a warning if someone tries to use a deprecated alias.
    if (isset($command['deprecated'])) {
      drush_log(dt('Warning: The command name "!deprecated" is deprecated.  Please use a recommended form instead (!recommended).', array('!deprecated' => $command['deprecated-name'], '!recommended' => implode(',', array_merge(array($command['command']), $command['aliases'])))), 'warning');
      if (isset($command['must-not-use-spaces'])) {
        drush_set_error(dt('You may enable spaces in commands for a while by setting "allow-spaces-in-commands" in your drush configuration file.  See example.drushrc.php.', array('!name' => $command['command'], '!requiredname' => $required_name)));
        return FALSE;
      }
    }
    // Print a warning if a command callback function is misnamed
    if (isset($command['callback-required-prefix'])) {
      drush_log(dt('Warning: The command callback function !callback has a deprecated name.  It must begin with !requiredprefix.  Skipping hook functions.', array('!callback' => $command['callback'], '!requiredprefix' => $command['callback-required-prefix'])));
    }
    // Call the callback function of the active command.
    // TODO:  If we make the required prefix actually required rather than just emitting a
    // warning, then this could become a direct call to drush_command (all commands with
    // the required prefix will now go through drush_command + drush_invoke).
    $return = call_user_func_array($command['callback'], $command['arguments']);
  }

  // prevent a '1' at the end of the output
  if ($return === TRUE) {
    $return = '';
  }

  // Add a final log entry, just so a timestamp appears.
  drush_log(dt('Command dispatch complete'), 'notice');

  return $return;
}

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
 * Detects the version number of the current Drupal installation,
 * if any. Returns false if there is no current Drupal installation,
 * or it is somehow broken.
 *
 * This function relies on the presence of DRUPAL_ROOT/modules/system/system.module
 *
 * @return
 *   A string containing the version number of the current
 *   Drupal installation, if any. Otherwise, return false.
 */
function drush_drupal_version() {
  static $version = FALSE;

  if (!$version) {
    if ($drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT')) {
      if (file_exists($drupal_root . '/modules/system/system.module')) {
        // We can safely include system.module as it contains defines and functions only.
        require_once($drupal_root . '/modules/system/system.module');
        // We just might be dealing with an early Drupal version (pre 4.7)
        if (defined('VERSION')) {
          $version = VERSION;
        }
      }
    }
  }
  return $version;
}

function drush_drupal_cache_clear_all() {
  $prior = drush_get_context('DRUSH_AFFIRMATIVE');
  drush_set_context('DRUSH_AFFIRMATIVE', TRUE);
  drush_invoke('cache-clear', 'all');
  drush_set_context('DRUSH_AFFIRMATIVE', $prior);
}

/**
 * Returns the Drupal major version number (5, 6, 7 ...)
 */
function drush_drupal_major_version() {
  $major_version = FALSE;
  if ($version = drush_drupal_version()) {
    $version_parts = explode('.', $version);
    if (is_numeric($version_parts[0])) {
      $major_version = (integer)$version_parts[0];
    }
  }
  return $major_version;
}

/**
 * Replace named placeholders in a WHERE snippet.
 *
 * Helper function to allow the usage of Drupal 7 WHERE snippets
 * with named placeholders in code for Drupal 5 and 6.
 *
 * @param $where
 *   Stringwith a WHERE snippet using named placeholders.
 * @param $args
 *   Array of placeholder values.
 * @return
 *   String. $where filled with literals from $args.
 */
function _drush_replace_query_placeholders($where, $args) {
  foreach ($args as $key => $data) {
    if (is_array($data)) {
      $new_keys = array();
      // $data can't have keys that are a prefix of other keys to
      // prevent a corrupted result in the below calls to str_replace().
      // To avoid this we will use an indexed array of the values of $data.
      foreach (array_values($data) as $i => $value) {
        if (!is_numeric($value)) {
          $value = "'".$value."'";
        }
        $new_keys[$key . '_' . $i] = $value;
      }
      $where = preg_replace('#' . $key . '\b#', implode(', ', array_keys($new_keys)), $where);
      unset($args[$key]);
      $args += $new_keys;
    }
    else if (!is_numeric($data)) {
      $args[$key] = "'".$data."'";
    }
  }

  foreach ($args as $key => $data) {
    $where = str_replace($key, $data, $where);
  }

  return $where;
}

/**
 * A db_select() that works for any version of Drupal.
 *
 * @param $table
 *   String. The table to operate on.
 * @param $fields
 *   Array or string. Fields affected in this operation. Valid string values are '*' or a single column name.
 * @param $where
 *   String. WHERE snippet for the operation. It uses named placeholders. see @_drush_replace_query_placeholders()
 * @param $args
 *   Array. Arguments for the WHERE snippet.
 * @param $start
 *   Int. Value for OFFSET.
 * @param $length
 *   Int. Value for LIMIT.
 * @param $order_by_field
 *   String. Database column to order by.
 * @param $order_by_direction
 *   ('ASC', 'DESC'). Ordering direction.
 * @return
 *   A database resource.
 */
function drush_db_select($table, $fields = '*', $where = NULL, $args = NULL, $start = NULL, $length = NULL, $order_by_field = NULL, $order_by_direction = 'ASC') {
  if (drush_drupal_major_version() >= 7) {
    if (!is_array($fields)) {
      if ($fields == '*') {
        $fields = array();
      }
      else {
        $fields = array($fields);
      }
    }
    $query = db_select($table, $table)
      ->fields($table, $fields);
    if (!empty($where)) {
      $query = $query->where($where, $args);
    }
    if (!is_null($order_by_field)) {
      $query = $query->orderBy($order_by_field, $order_by_direction);
    }
    if (!is_null($length)) {
      $query = $query->range($start, $length);
    }
    return $query->execute();
  }
  else {
    if (is_array($fields)) {
      $fields = implode(', ', $fields);
    }
    $query = "SELECT $fields FROM {{$table}}";
    if (!empty($where)) {
      $where = _drush_replace_query_placeholders($where, $args);
      $query .= " WHERE ".$where;
    }
    if (!is_null($order_by_field)) {
      $query .= " ORDER BY $order_by_field $order_by_direction";
    }
    if (!is_null($length)) {
      $limit = " LIMIT $length";
      if (!is_null($start)) {
        $limit .= " OFFSET $start";
      }
      $query .= $limit;
    }

    return db_query($query, $args);
  }
}

/**
 * A db_delete() that works for any version of Drupal.
 *
 * @param $table
 *   String. The table to operate on.
 * @param $where
 *   String. WHERE snippet for the operation. It uses named placeholders. see @_drush_replace_query_placeholders()
 * @param $args
 *   Array. Arguments for the WHERE snippet.
 * @return
 *   Affected rows or FALSE.
 */
function drush_db_delete($table, $where = NULL, $args = NULL) {
  if (drush_drupal_major_version() >= 7) {
    if (!empty($where)) {
      $query = db_delete($table)->where($where, $args);
      return $query->execute();
    }
    else {
      return db_truncate($table)->execute();
    }
  }
  else {
    $query = "DELETE FROM {{$table}}";
    if (!empty($where)) {
      $where = _drush_replace_query_placeholders($where, $args);
      $query .= ' WHERE '.$where;
    }
    if (!db_query($query, $args)) {
      return FALSE;
    }
    return db_affected_rows();
  }
}

/**
 * A db_result() that works consistently for any version of Drupal.
 *
 * @param
 *   A Database result object.
 */
function drush_db_result($result) {
  switch (drush_drupal_major_version()) {
    case 5:
      // In versions of Drupal <= 5, db_result only returns the first row no matter how
      //  many times you call it. So instead of calling it here, we use db_fetch_array which
      //  does increment the pointer to the next row (as db_result does on Drupal 6)
      if ($array = db_fetch_array($result)) {
        return array_shift($array); // return first element in array.
      }
    case 6:
      return db_result($result);
    case 7:
    default:
      return $result->fetchField();
  }
}

/**
 * A db_fetch_object() that works for any version of Drupal.
 *
 * @param
 *   A Database result object.
 */
function drush_db_fetch_object($result) {
  return drush_drupal_major_version() >= 7 ? $result->fetchObject() : db_fetch_object($result);
}

/**
 * Save a string to a temporary file. Does not depend on Drupal's API.
 * The temporary file will be automatically deleted when drush exits.
 *
 * @param string $data
 * @return string
 *   A path to the file.
 */
function drush_save_data_to_temp_file($data) {
  static $fp;

  $fp = tmpfile();
  fwrite($fp, $data);
  $meta_data = stream_get_meta_data($fp);
  $file = $meta_data['uri'];
  drush_register_file_for_deletion($file);

  return $file;
}

/**
 * Creates a temporary file, and registers it so that
 * it will be deleted when drush exits.  Whenever possible,
 * drush_save_data_to_temp_file() should be used instead
 * of this function.
 */
function drush_tempnam($pattern, $tmp_dir = NULL) {
  if ($tmp_dir == NULL) {
    $tmp_dir = sys_get_temp_dir();
  }
  $tmp_file = tempnam($tmp_dir, $pattern);
  drush_register_file_for_deletion($tmp_file);

  return $tmp_file;
}

/**
 * Any file passed in to this function will be deleted
 * when drush exits.
 */
function drush_register_file_for_deletion($file = NULL) {
  static $registered_files = array();

  if (isset($file)) {
    if (empty($registered_files)) {
      register_shutdown_function('_drush_delete_registered_files');
    }
    $registered_files[] = $file;
  }

  return $registered_files;
}

/**
 * Delete all of the registered temporary files.
 */
function _drush_delete_registered_files() {
  $files_to_delete = drush_register_file_for_deletion();

  foreach ($files_to_delete as $file) {
    // We'll make sure that the file still exists, just
    // in case someone came along and deleted it, even
    // though they did not need to.
    if (file_exists($file)) {
      unlink($file);
    }
  }
}

/**
 * Deletes the provided file or folder and
 * everything inside it.
 *
 * @param $dir
 *   The directory to delete
 * @return
 *   FALSE on failure, TRUE if everything was deleted
 */
function drush_delete_dir($dir) {
  if (!file_exists($dir)) {
    return TRUE;
  }
  if (!is_dir($dir)) {
    return unlink($dir);
  }
  foreach (scandir($dir) as $item) {
    if ($item == '.' || $item == '..') {
      continue;
    }
    if (!drush_delete_dir($dir.DIRECTORY_SEPARATOR.$item)) {
      return FALSE;
    }
  }
  return rmdir($dir);
}

/**
 * Move $src to $dest.  If the php 'rename' function
 * doesn't work, then we'll try rsync.
 *
 * @param $src
 *   The directory to move.
 * @param $dest
 *   The destination to move the source to, including
 *   the new name of the folder.  To move folder "a"
 *   from "/b" to "/c", then $src = "/b/a" and $dest = "/c/a".
 *   To move "a" to "/c" and rename it to "d", then
 *   $dest = "/c/d" (just like php rename function).
 * @param $overwrite
 *   If TRUE, the destination will be deleted if it
 *   exists.  Defaults to FALSE.
 * @return
 *   TRUE on success, FALSE on failure
 */
function drush_move_dir($src, $dest, $overwrite = FALSE) {
  // Preflight based on $overwrite if $dest exists.
  if (is_dir($dest)) {
    if ($overwrite) {
      drush_op('drush_delete_dir', $dest);
    }
    else {
      return drush_set_error('DRUSH_DESTINATION_EXISTS', dt('Destination directory !dest already exists.', array('!dest' => $dest)));
    }
  }

  // If rename works, then we're done.
  if (@drush_op('rename', $src, $dest)) {
    return TRUE;
  }
  // Bail if we can't make a directory at the
  // destination (e.g. permissions)
  if (!is_dir($dest) && (drush_op('mkdir', $dest) === FALSE)) {
    return FALSE;
  }
  // If rename doesn't work, then try rsync.
  $exec = 'rsync -raz --remove-source-files ' . $src . DIRECTORY_SEPARATOR . ' ' . $dest;
  $rsync_result = drush_op('system', $exec);
  if($rsync_result !== FALSE) {
    // --remove-source-files deletes all of the files, but
    // we still need to get rid of the directories.
    drush_op('drush_delete_dir', $src);
    return TRUE;
  }
  return FALSE;
}

/**
 * Calls a given function, passing through all arguments unchanged.
 *
 * This should be used when calling possibly mutative or destructive functions
 * (e.g. unlink() and other file system functions) so that can be suppressed
 * if the simulation mode is enabled.
 *
 * @param $function
 *   The name of the function.
 * @return
 *   The return value of the function, or TRUE if simulation mode is enabled.
 */
function drush_op($function) {
  $args = func_get_args();
  array_shift($args); // Skip function name

  if (drush_get_context('DRUSH_VERBOSE') || drush_get_context('DRUSH_SIMULATE')) {
     drush_print("Calling $function(". implode(", ", $args) .')');
  }

  if (drush_get_context('DRUSH_SIMULATE')) {
    return TRUE;
  }

  return call_user_func_array($function, $args);
}

/**
 * Rudimentary replacement for Drupal API t() function.
 *
 * @param string
 *   String to process, possibly with replacement item.
 * @param array
 *  An associative array of replacement items.
 *
 * @return
 *   The processed string.
 *
 * @see t()
 */
function dt($string, $args = array()) {
  if (function_exists('t')) {
    return t($string, $args);
  }
  else {
    if (!empty($args)) {
      return strtr($string, $args);
    }
    else {
      return $string;
    }
  }
}

/**
 * Get the available options for Drush for use by help page.
 *
 * @return
 *   An associative array containing the option definition as the key, and the description as the value,
 *   for each of the available options.
 */
function drush_get_option_help() {
  // TODO: Add a hook for this, to allow other modules to add their global options
  $options['-r <path>, --root=<path>'] = dt("Drupal root directory to use (default: current directory)");
  $options['-l <uri>, --uri=http://example.com']   = dt('URI of the drupal site to use (only needed in multisite environments)');
  $options['-v, --verbose']            = dt('Display extra information about the command.');
  $options['-d, --debug']              = dt('Display even more information, including internal messages.');
  $options['-q, --quiet']              = dt('Hide all output');
  $options['-y, --yes']                = dt("Assume 'yes' as answer to all prompts");
  $options['-n, --no']                = dt("Assume 'no' as answer to all prompts");
  $options['-s, --simulate']           = dt("Simulate all relevant actions (don't actually change the system)");
  $options['-i, --include']            = dt("A list of paths to search for drush commands");
  $options['-c, --config']             = dt("Specify a config file to use. See example.drushrc.php");
  $options['-u, --user']               = dt("Specify a user to login with. May be a name or a number.");
  $options['-b, --backend']            = dt("Hide all output and return structured data (internal use only).");
  $options['-p, --pipe']               = dt("Emit a compact representation of the command for scripting.");
  $options['--nocolor']                = dt("Suppress color highlighting on log messages.");
  $options['--show-passwords']         = dt("Show database passwords in commands that display connection information.");
  $options['-h, --help']               = dt("This help system.");
  $options['--php']                    = dt("The absolute path to your PHP intepreter, if not 'php' in the path.");
  return $options;
}

/**
 * Prints out help for a given command.
 */
function drush_show_help($commands) {
  $phases = _drush_bootstrap_phases();

  $commandstring = implode(" ", $commands);

  foreach ($phases as $phase_index) {
    if ($phase_index > drush_get_context('DRUSH_BOOTSTRAP_PHASE')) {
      drush_bootstrap($phase_index);
    }
    if (!drush_get_error()) {
      $commands = drush_get_commands();
      if (array_key_exists($commandstring, $commands)) {
        $command = $commands[$commandstring];

        // Merge in engine specific help.
        foreach ($command['engines'] as $type => $description) {
          $all_engines = drush_get_engines($type);
          foreach ($all_engines as $name => $engine) {
            $command = array_merge_recursive($command, $engine);
          }
        }

        if (!$help = drush_command_invoke_all('drush_help', 'drush:'. $command['command'])) {
          $help = array($command['description']);
        }
        drush_print(wordwrap(implode("\n", $help), drush_get_context('DRUSH_COLUMNS', 80)));
        drush_print();

        // TODO: Let commands define additional sections.
        $sections = array(
          'examples' => 'Examples',
          'arguments' => 'Arguments',
          'options' => 'Options',
        );

        foreach ($sections as $key => $value) {
          if (!empty($command[$key])) {
            drush_print(dt($value) . ':');
            foreach ($command[$key] as $name => $description) {
              // '[command] is a token representing the current command. @see pm_drush_engine_version_control().
              $rows[] = array(str_replace('[command]', $commandstring, $name), dt($description));
            }
            drush_print_table($rows, false, array(40));
            unset($rows);
            drush_print();
          }
        }

        // Append aliases if any.
        if ($command['aliases']) {
          drush_print(dt("Aliases: ") . implode(', ', $command['aliases']));
        }

        return TRUE;

      }
    }
    else {
      break;
    }
  }
  return drush_set_error('DRUSH_COMMAND_NOT_FOUND', dt('Invalid command !command.', array('!command' => $commandstring)));
}

/**
 * Executes a shell command.
 * Output is only printed if in verbose mode.
 * Output is stored and can be retrieved using drush_shell_exec_output().
 * If in simulation mode, no action is taken.
 *
 * @param $cmd
 *   The command to execute. May include placeholders used for sprintf.
 * @param ...
 *   Values for the placeholders specified in $cmd. Each of these will be passed through escapeshellarg() to ensure they are safe to use on the command line.
 * @return
 *   0 if success.
 */
function drush_shell_exec($cmd) {
  $args = func_get_args();

  //do not change the command itself, just the parameters.
  for ($x = 1; $x < sizeof($args); $x++) {
    $args[$x] = escapeshellarg($args[$x]);
  }
  $command = call_user_func_array('sprintf', $args);

  if (drush_get_context('DRUSH_VERBOSE') || drush_get_context('DRUSH_SIMULATE')) {
    drush_log('Executing: ' . $command);
  }

  if (!drush_get_context('DRUSH_SIMULATE')) {
    exec($command . ' 2>&1', $output, $result);
    _drush_shell_exec_output_set($output);

    if (drush_get_context('DRUSH_DEBUG')) {
      foreach ($output as $line) {
        drush_print($line, 2);
      }
    }

    // Exit code 0 means success.
    return ($result == 0);
  }
  else {
    return 0;
  }
}

/**
 * Stores output for the most recent shell command.
 * This should only be run from drush_shell_exec().
 *
 * @param $output
 *   The output of the most recent shell command.
 *   If this is not set the stored value will be returned.
 */
function _drush_shell_exec_output_set($output = FALSE) {
  static $stored_output;
  if ($output === FALSE) return $stored_output;
  $stored_output = $output;
}

/**
 * Returns the output of the most recent shell command as an array of lines.
 */
function drush_shell_exec_output() {
  return _drush_shell_exec_output_set();
}

/**
 * Exits with a message. In general, you should use drush_set_error() instead of
 * this function. That lets drush proceed with other tasks.
 * TODO: Exit with a correct status code.
 */
function drush_die($msg = NULL, $status = NULL) {
  die($msg ? "drush: $msg\n" : '');
}

/**
 * Prints a message with optional indentation. In general,
 * drush_log($message, 'ok') is often a better choice than this function.
 * That gets your confirmation message (for example) into the logs for this
 * drush request. Consider that drush requests may be executed remotely and
 * non interactively.
 *
 * @param $message
 *   The message to print.
 * @param $indent
 *    The indentation (space chars)
 */
function drush_print($message = '', $indent = 0) {
  $msg = str_repeat(' ', $indent) . (string)$message . "\n";
  if ($charset = drush_get_option('output_charset') && function_exists('iconv')) {
    $msg = iconv('UTF-8', $charset, $msg);
  }
  print $msg;
}

/**
 * Stores a message which is printed during drush_shutdown() if in compact mode.
 * @param $message
 *   The message to print.  If $message is an array,
 *   then each element of the array is printed on a
 *   separate line.
 */
function drush_print_pipe($message = '') {
  $buffer = &drush_get_context('DRUSH_PIPE_BUFFER' , '');
  if (is_array($message)) {
    $message = implode("\n", $message) . "\n";
  }
  $buffer .= $message;
}

/**
 * Prints an array or string.
 * @param $array
 *   The array to print.
 */
function drush_print_r($array) {
  print_r($array);
}

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

  drush_print('  [0] : Cancel');
  $selection_number = 0;
  foreach ($options as $key => $option) {
    if ((substr($key, 0, 3) == '-- ') && (substr($key, -3) == ' --')) {
      drush_print("  ".$option);
      continue;
    }
    $selection_number++;
    $message = dt($label, array('!number' => $selection_number, '!key' => $key, '!value' => $option));
    drush_print(dt("  [!number] : !message", array('!number' => $selection_number, '!message' => $message)));
    $selection_list[$selection_number] = $key;
  }

  while ($line = trim(fgets(STDIN))) {
    if (array_key_exists($line, $selection_list)) {
      return $selection_list[$line];
    }
  }
  drush_print(dt('Cancelled'));
  return FALSE;
}

/**
 * Prompt the user for input
 *
 * The input can be anything that fits on a single line (not only y/n),
 * so we can't use drush_confirm()
 *
 * @see drush_confirm()
 */
function drush_prompt($prompt, $default = NULL) {
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
    if ($line) {
      break;
    }
    print $prompt;
  }
  fclose($stdin);
  return $line;
}

/**
 * Print a formatted table.
 *
 * @param $rows
 *   The rows to print.
 * @param $header
 *   If TRUE, the first line will be treated as table header and therefore be
 *   underlined.
 * @param $widths
 *   The widths of each column (in characters) to use - if not specified this
 *   will be determined automatically, based on a "best fit" algorithm.
 */
function drush_print_table($rows, $header = FALSE, $widths = array()) {
  $tbl = new Console_Table(CONSOLE_TABLE_ALIGN_LEFT , '');

  $auto_widths = drush_table_column_autowidth($rows, $widths);

  // Do wordwrap on all cells.
  $newrows = array();
  foreach ($rows as $rowkey => $row) {
    foreach ($row as $col_num => $cell) {
      $newrows[$rowkey][$col_num] = wordwrap($cell, $auto_widths[$col_num], "\n", TRUE);
      if (isset($widths[$col_num])) {
        $newrows[$rowkey][$col_num] = str_pad($newrows[$rowkey][$col_num], $widths[$col_num]);
      }
    }
  }
  if ($header) {
    $headers = array_shift($newrows);
    $tbl->setHeaders($headers);
  }

  $tbl->addData($newrows);
  drush_print($tbl->getTable());
  return $tbl;
}

/**
 * Convert an associative array of key : value pairs into
 * a table suitable for processing by drush_print_table.
 *
 * @param $keyvalue_table
 *    An associative array of key : value pairs.
 * @return
 *    An array of arrays, where the keys from the input
 *    array are stored in the first column, and the values
 *    are stored in the third.  A second colum is created
 *    specifically to hold the ':' separator.
 */
function drush_key_value_to_array_table($keyvalue_table) {
  $table = array();
  foreach ($keyvalue_table as $key => $value) {
    if (isset($value)) {
      $table[] = array($key, ' :', $value);
    }
    else {
      $table[] = array($key . ':', '', '');
    }
  }
  return $table;
}

/**
 * Determine the best fit for column widths.
 *
 * @param $rows
 *   The rows to use for calculations.
 * @param $widths
 *   Manually specified widths of each column (in characters) - these will be
 *   left as is.
 */
function drush_table_column_autowidth($rows, $widths) {
  $auto_widths = $widths;

  // First we determine the distribution of row lengths in each column.
  // This is an array of descending character length keys (i.e. starting at
  // the rightmost character column), with the value indicating the number
  // of rows where that character column is present.
  $col_dist = array();
  foreach ($rows as $rowkey => $row) {
    foreach ($row as $col_num => $cell) {
      if (empty($widths[$col_num])) {
        $length = strlen($cell);
        while ($length > 0) {
          if (!isset($col_dist[$col_num][$length])) {
            $col_dist[$col_num][$length] = 0;
          }
          $col_dist[$col_num][$length]++;
          $length--;
        }
      }
    }
  }
  foreach ($col_dist as $col_num => $count) {
    // Sort the distribution in decending key order.
    krsort($col_dist[$col_num]);
    // Initially we set all columns to their "ideal" longest width
    // - i.e. the width of their longest column.
    $auto_widths[$col_num] = max(array_keys($col_dist[$col_num]));
  }

  // We determine what width we have available to use, and what width the
  // above "ideal" columns take up.
  $available_width = drush_get_context('DRUSH_COLUMNS', 80) - (count($auto_widths) * 2);
  $auto_width_current = array_sum($auto_widths);

  // If we need to reduce a column so that we can fit the space we use this
  // loop to figure out which column will cause the "least wrapping",
  // (relative to the other columns) and reduce the width of that column.
  while ($auto_width_current > $available_width) {
    $count = 0;
    $width = 0;
    foreach ($col_dist as $col_num => $counts) {
      // If we are just starting out, select the first column.
      if ($count == 0 ||
         // OR: if this column would cause less wrapping than the currently
         // selected column, then select it.
         (current($counts) < $count) ||
         // OR: if this column would cause the same amount of wrapping, but is
         // longer, then we choose to wrap the longer column (proportionally
         // less wrapping, and helps avoid triple line wraps).
         (current($counts) == $count && key($counts) > $width)) {
        // Select the column number, and record the count and current width
        // for later comparisons.
        $column = $col_num;
        $count = current($counts);
        $width = key($counts);
      }
    }
    if ($width <= 1) {
      // If we have reached a width of 1 then give up, so wordwrap can still progress.
      break;
    }
    // Reduce the width of the selected column.
    $auto_widths[$column]--;
    // Reduce our overall table width counter.
    $auto_width_current--;
    // Remove the corresponding data from the disctribution, so next time
    // around we use the data for the row to the left.
    unset($col_dist[$column][$width]);
  }
  return $auto_widths;
}

/**
 * @defgroup dispatching Command dispatching functions.
 * @{
 *
 * These functions manage parameter and option manipulation
 * for calls to drush backend invoke.
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
  // @see _drush_process_site_alias
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
    $args = drush_get_arguments();

    if (!drush_get_context('DRUSH_SIMULATE')) {
      drush_print(dt("You are about to execute '!command' on all of the following targets:", array('!command' => implode(" ", $args))));
      foreach ($site_list as $one_destination) {
        drush_print(dt('  !target', array('!target' => $one_destination)));
      }

      if (drush_confirm('Continue? ') === FALSE) {
         drush_die('Aborting.');
      }
    }
    $command = array_shift($args);

    foreach ($site_list as $site_spec) {
      $values = drush_do_site_command(_drush_sitealias_get_record($site_spec), $command, $args);
      drush_print($values['output']);
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
         drush_die('Aborting.');
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

function drush_do_site_command($site_record, $command, $args = array(), $data = array(), $integrate = FALSE) {
  $values = NULL;
  if (!empty($site_record)) {
    foreach ($site_record as $key => $value) {
      if (!isset($data[$key]) && !in_array($key, drush_sitealias_site_selection_keys())) {
        $data[$key] = $site_record[$key];
      }
    }
    
    $drush_path = NULL;
    if (array_key_exists('path-aliases', $site_record)) {
      if (array_key_exists('%drush-script', $site_record['path-aliases'])) {
        $drush_path = $site_record['path-aliases']['%drush-script'];
      }
    }

    $values = drush_backend_invoke_args($command, $args, $data, 'GET', $integrate, $drush_path, array_key_exists('remote-host', $site_record) ? $site_record['remote-host'] : NULL, array_key_exists('remote-user', $site_record) ? $site_record['remote-user'] : NULL);
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
 * Get the options for this command.
 *
 * This function returns an array that contains all of the options
 * that are appropriate for forwarding along to backend invoke.
 * Pass the result from this function to backend invoke in the $data
 * parameter when doing a redispatch.
 */
function drush_redispatch_get_options() {
  // Start off by taking everything from the site alias and command line
  // ('options' context)
  $alias_context = array_diff_key(drush_get_context('alias'), array_flip(drush_sitealias_site_selection_keys()));
  $options = array_merge($alias_context, drush_get_context('options'));
  unset($options['command-specific']);
  unset($options['path-aliases']);
  // If we can parse the current command, then examine all contexts
  // in order for any option that is directly related to the current command
  $command = drush_parse_command();
  if (is_array($command)) {
    foreach ($command['options'] as $key => $value) {
      // Strip leading --
      $key = ltrim($key, '-');
      $value = drush_get_option($key);
      if (isset($value)) {
        $options[$key] = $value;
      }
    }
  }
  // 'php', if needed, will be included in DRUSH_COMMAND.  If DRUSH_COMMAND
  // is not used (e.g. when calling a remote instance of drush), then --php
  // should not be passed along.
  unset($options['php']);

  return $options;
}

/**
 * @} End of "defgroup dispatching".
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

/*
 * Display the pipe output for the current request.
 */
function drush_pipe_output() {
  $pipe = drush_get_context('DRUSH_PIPE_BUFFER');
  drush_print_r($pipe);
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
    $green = "\033[0;33;40m\033[1m[%s]\033[0m";
  }

  $verbose = drush_get_context('DRUSH_VERBOSE');
  $debug = drush_get_context('DRUSH_DEBUG');

  $return = TRUE;
  switch ($entry['type']) {
    case 'warning' :
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
  drush_print($message);
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

    $messages = drupal_get_messages();

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
 * Log Drupal watchdog() calls.
 *
 * A sneaky implementation of hook_watchdog().
 */
function system_watchdog($log_entry) {

  // Transform non informative severity levels to 'error' for compatibility with _drush_print_log.
  // Other severity levels are coincident with the ones we use in drush.
  if (drush_drupal_major_version() >= 6 && $log_entry['severity'] <= 2) {
    $severity = 'error';
  }
  else {
    drush_include_engine('drupal', 'environment');
    $levels = core_watchdog_severity_levels();
    $severity = $levels[$log_entry['severity']];
  }
  // Format the message.
  if (is_array($log_entry['variables'])) {
    $message = strtr($log_entry['message'], $log_entry['variables']);
  }
  else {
    $message = $log_entry['message'];
  }
  $message = strip_tags(decode_entities($message));
  // Log it.
  drush_log('WD '. $log_entry['type'] . ': '.$message, $severity);
}

/**
 * Log the return value of Drupal hook_update_n functions.
 *
 * This is used during install and update to log the output
 * of the update process to the logging system.
 */
function _drush_log_update_sql($ret) {
  if (sizeof($ret)) {
    foreach ($ret as $info) {
      if (is_array($info)) {
        if (!$info['success']) {
          drush_set_error('DRUPAL_UPDATE_FAILED', $info['query']);
        }
        else {
          drush_log($info['query'], ($info['success']) ? 'success' : 'error');
        }
      }
    }
  }
}

/**
 * @} End of "defgroup logging".
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
      $value *= 1024;
    case 'm':
      $value *= 1024;
    case 'k':
      $value *= 1024;
  }

  return $value;
}

/**
 * Unset the named key anywhere in the provided
 * data structure.
 */
function drush_unset_recursive(&$data, $unset_key) {
  unset($data[$unset_key]);
  foreach ($data as $key => $value) {
    if (is_array($value)) {
      drush_unset_recursive($data[$key], $unset_key);
    }
  }
}
