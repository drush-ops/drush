<?php
// $Id$

/**
 * @file
 * The drush command engine.
 *
 * Since drush can be invoked independently of a proper Drupal
 * installation and commands may operate across sites, a distinct
 * command engine is needed.
 *
 * It mimics the Drupal module engine in order to economize on 
 * concepts and to make developing commands as familiar as possible
 * to traditional Drupal module developers.
 */

/**
 * Get a list of all implemented commands.
 * This invokes hook_drush_command().
 *
 * @param boolean
 *   If TRUE, the internal commands table will be rebuild.
 *
 * @return
 *   Associative array of currently active command descriptors.
 *
 */
function drush_get_commands() {
  $commands = drush_command_invoke_all('drush_command');
  
  // Filter out commands which are invalid for the current version of core Drupal.
  $commands = drush_enforce_requirement_core($commands);
  
  // Filter out commands which are missing their dependant Drupal modules.
  $commands = drush_enforce_requirement_drupal_dependencies($commands);
  
  return $commands;
}

/**
 * Matches a commands array, as parsed by drush_parse_args, with the
 * current command table.
 *
 * Note that not all commands may be discoverable at the point-of-call,
 * since Drupal modules can ship commands as well, and they are
 * not available until after bootstrapping.
 *
 * drush_parse_command returns a normalized command descriptor, which
 * is an associative array with the following entries:
 * - callback: name of function to invoke for this command.
 * - callback arguments: an array of arguments to pass to the calback.
 * - description: description of the command.
 * - arguments: an array of arguments that are understood by the command. for help texts.
 * - options: an array of options that are understood by the command. for help texts.
 * - examples: an array of examples that are understood by the command. for help texts.
 * - scope: one of 'system', 'project', 'site'.
 * - bootstrap: drupal bootstrap level (depends on Drupal major version). -1=no_bootstrap.
 * - core: Drupal major version required.
 * - drupal dependencies: drupal modules required for this command.
 * - drush dependencies: other drush command files required for this command (not yet implemented)
 *
 * @example
 *   list($command, $arguments) = drush_parse_command($GLOBALS['args']['commands']);
 *
 * @param array
 *   Commands, as parsed by drush_parse_args()
 *
 * @return array
 *   An array consisting of two elements:
 *   - A normalized command descriptor (itself an array)
 *   - The command arguments, i.e. the remaining
 */
function drush_parse_command($commands) {

  // Get a list of all implemented commands.
  $implemented = drush_get_commands();

  $command = FALSE;
  $arguments = array();
  // Try to determine the handler for the current command.
  while (!$command && count($commands)) {
    $part = implode(" ", $commands);
    if (isset($implemented[$part])) {
      $command = $implemented[$part];
      // We found a command descriptor, now we check if it is valid.
      if (empty($command['callback'])) {
        $command = FALSE;
        break;
      }
      else {
        // Normalize command descriptor
        $command += array(
          'description' => NULL,
          'arguments' => array(),
          'options' => array(),
          'extras' => array(),
          'core' => array(),
          'scope' => 'site',
          'bootstrap' => NULL,
          'drupal dependencies' => array(),
          'drush dependencies' => array(),
        );
      }
    }
    else {
      $arguments[] = array_pop($commands);
    }
  }

  $arguments = array_reverse($arguments);

  // Merge specified callback arguments, which precede the arguments passed on the command line.
  if (is_array($command['callback arguments'])) {
    $arguments = array_merge($command['callback arguments'], $arguments);
  }

  return array($command, $arguments);
}

/**
 * Invoke a hook in all available command files that implement it.
 *
 * @param $hook
 *   The name of the hook to invoke.
 * @param ...
 *   Arguments to pass to the hook.
 * @return
 *   An array of return values of the hook implementations. If commands return
 *   arrays from their implementations, those are merged into one array.
 */
function drush_command_invoke_all() {
  $args = func_get_args();
  $hook = $args[0];
  unset($args[0]);
  $return = array();
  foreach (drush_command_implements($hook) as $module) {
    $function = $module .'_'. $hook;
    $result = call_user_func_array($function, $args);
    if (isset($result) && is_array($result)) {
      $return = array_merge_recursive($return, $result);
    }
    else if (isset($result)) {
      $return[] = $result;
    }
  }
  return $return;
}

/**
 * Determine which command files are implementing a hook.
 *
 * @param $hook
 *   The name of the hook (e.g. "drush_help" or "drush_command").
 *
 * @return
 *   An array with the names of the command files which are implementing this hook.
 */
function drush_command_implements($hook) {
  $implementations[$hook] = array();
  $list = drush_commandfile_list();
  foreach ($list as $commandfile) {
    if (drush_command_hook($commandfile, $hook)) {
      $implementations[$hook][] = $commandfile;
    }
  }
  return (array)$implementations[$hook];
}

/**
 * @param string
 *   name of command to check.
 *
 * @return boolean
 *   TRUE if the given command has an implementation.
 */
function drush_is_command($command) {
  $commands = drush_get_commands();
  // print_r($commands);
  return isset($commands[$command]);
}

/**
 * Collect a list of all available drush command files.
 * 
 * Scans the following paths for drush command files:
 *
 * - The ".drush" folder in the users HOME folder.
 * - The "/path/to/drush/includes" folder.
 * - Folders listed in the 'include' option (see example.drushrc.php).
 * - Active modules in the current Drupal installation (if any).
 *
 * A drush command file is a file that matches "*.drush.inc".
 * 
 * @see drush_scan_directory
 *
 * @return
 *   An associative array whose keys and values are the names of all available
 *   command files.
 */
function drush_commandfile_list() {
  $cache = _drush_commandfile_cache();

  if (!$cache) {
    $searchpath = array();

    // Core commands shipping with drush
    $searchpath[] = dirname(__FILE__);

    // User commands, residing i ~/.drush
    if (!empty($_SERVER['HOME'])) {
      $searchpath[] = $_SERVER['HOME'] . '/.drush';
    }

    // User commands, specified by 'include' option
    if ($include = drush_get_option(array('i', 'include'), FALSE)) {
      $searchpath = array_merge($searchpath, explode(":", $include));
    }
    
    // Site commands, found in current Drupal site.
    if (defined('DRUSH_DRUPAL_BOOTSTRAPPED')) {
      // Add enabled module paths. Since we are bootstrapped,
      // we can use the Drupal API.
      foreach (array_keys(module_rebuild_cache()) as $module) {
        $searchpath[] = drupal_get_path('module', $module);
      }
    }

    $list = array();
    // Scan for drush command files, load if found
    foreach (array_unique($searchpath) as $path) {
      if (is_dir($path)) {
        $files = drush_scan_directory($path, '\.drush\.inc$');
        foreach ($files as $filename => $info) {
          require_once($filename);
          $list[$filename] = basename($filename, '.drush.inc');
        }
      }
    }
  }

  return _drush_commandfile_cache($list);
}

// This constant, when passed to _drush_commandfile_cache, will wipe out the command file cache
define('DRUSH_FLUSH_COMMANDFILE_CACHE', 1);

/**
 * Storage mechanism for the cached commandfile list.
 *
 * This function populates an internal static variable,
 * that contains the previously found command files.
 *
 * @param mixed
 *    Possible types :
 *      null - return the previous data only.
 *      array - replace the current cache contents with the contents of the array
 *      constant - when passed the DRUSH_FLUSH_COMMANDFILE_CACHE constant, this will wipe out the previous values, so future calls
 *                 to drush_command_file will automatically regenerate the cache.
 *  @return array
 *    The current file list that has been cached.
 */
function _drush_commandfile_cache($list = null) {
  static $cache = array();
  if ($list === DRUSH_FLUSH_COMMANDFILE_CACHE) {
    $cache = array();
  }
  elseif (!is_null($list)) {
    $cache = $list;
  }
  return $cache;
}

/**
 * Flush the commandfile list cache.
 *
 * This is needed whenever drush code changes bootstrap level,
 * to allow additional information (such as commands in enabled modules)
 * to be retrieved.
 */
function drush_commandfile_cache_flush() {
  _drush_commandfile_cache(DRUSH_FLUSH_COMMANDFILE_CACHE);
}

/**
 * Determine whether a command file implements a hook.
 *
 * @param $module
 *   The name of the module (without the .module extension).
 * @param $hook
 *   The name of the hook (e.g. "help" or "menu").
 * @return
 *   TRUE if the the hook is implemented.
 */
function drush_command_hook($commandfile, $hook) {
  return function_exists($commandfile .'_'. $hook);
}


/**
 * Finds all files that match a given mask in a given directory.
 * Directories and files beginning with a period are excluded; this
 * prevents hidden files and directories (such as SVN working directories
 * and GIT repositories) from being scanned.
 *
 * @param $dir
 *   The base directory for the scan, without trailing slash.
 * @param $mask
 *   The regular expression of the files to find.
 * @param $nomask
 *   An array of files/directories to ignore.
 * @param $callback
 *   The callback function to call for each match.
 * @param $recurse
 *   When TRUE, the directory scan will recurse the entire tree
 *   starting at the provided directory.
 * @param $key
 *   The key to be used for the returned array of files. Possible
 *   values are "filename", for the path starting with $dir,
 *   "basename", for the basename of the file, and "name" for the name
 *   of the file without an extension.
 * @param $min_depth
 *   Minimum depth of directories to return files from.
 * @param $depth
 *   Current depth of recursion. This parameter is only used internally and should not be passed.
 *
 * @return
 *   An associative array (keyed on the provided key) of objects with
 *   "path", "basename", and "name" members corresponding to the
 *   matching files.
 */
function drush_scan_directory($dir, $mask, $nomask = array('.', '..', 'CVS'), $callback = 0, $recurse = TRUE, $key = 'filename', $min_depth = 0, $depth = 0) {
  $key = (in_array($key, array('filename', 'basename', 'name')) ? $key : 'filename');
  $files = array();

  if (is_dir($dir) && $handle = opendir($dir)) {
    while (FALSE !== ($file = readdir($handle))) {
      if (!in_array($file, $nomask) && $file[0] != '.') {
        if (is_dir("$dir/$file") && $recurse) {
          // Give priority to files in this folder by merging them in after any subdirectory files.
          $files = array_merge(drush_scan_directory("$dir/$file", $mask, $nomask, $callback, $recurse, $key, $min_depth, $depth + 1), $files);
        }
        elseif ($depth >= $min_depth && ereg($mask, $file)) {
          // Always use this match over anything already set in $files with the same $$key.
          $filename = "$dir/$file";
          $basename = basename($file);
          $name = substr($basename, 0, strrpos($basename, '.'));
          $files[$$key] = new stdClass();
          $files[$$key]->filename = $filename;
          $files[$$key]->basename = $basename;
          $files[$$key]->name = $name;
          if ($callback) {
            $callback($filename);
          }
        }
      }
    }

    closedir($handle);
  }

  return $files;
}

// Return commands which have their declared dependencies or have no dependencies.
function drush_enforce_requirement_drupal_dependencies($commands) {
  foreach ($commands as $key => $command) {
    if (empty($command['drupal dependencies'])) {
      $valid[$key] = $command;
    }
    else {
      foreach ($command['drupal dependencies'] as $dependency) {
        if (function_exists('module_exists') && module_exists($dependency)) {
          $valid[$key] = $command;
        }
      }
    }
  }
  return $valid;
}

// Return commands that are valid for the current major version of core.
function drush_enforce_requirement_core($commands) {
  foreach ($commands as $key => $command) {
    if (empty($command['core']) || in_array(drush_drupal_major_version(), $command['core'])) {
      $valid[$key] = $command;
    }
  }
  return $valid;
}
