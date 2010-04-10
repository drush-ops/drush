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
 * Parse console arguments.
 */
function drush_parse_args() {
  $args = drush_get_context('argv');

  static $arg_opts = array('c', 'h', 'u', 'r', 'l', 'i');

  $arguments = $options = array();

  for ($i = 1; $i < count($args); $i++) {
    $opt = $args[$i];
    // Is the arg an option (starting with '-')?
    if ($opt{0} == "-" && strlen($opt) != 1) {
      // Do we have multiple options behind one '-'?
      if (strlen($opt) > 2 && $opt{1} != "-") {
        // Each char becomes a key of its own.
        for ($j = 1; $j < strlen($opt); $j++) {
          $options[substr($opt, $j, 1)] = true;
        }
      }
      // Do we have a longopt (starting with '--')?
      elseif ($opt{1} == "-") {
        if ($pos = strpos($opt, '=')) {
          $options[substr($opt, 2, $pos - 2)] = substr($opt, $pos + 1);
        }
        else {
          $options[substr($opt, 2)] = true;
        }
      }
      else {
        $opt = substr($opt, 1);
        // Check if the current opt is in $arg_opts (= has to be followed by an argument).
        if ((in_array($opt, $arg_opts))) {
          if (($args[$i+1] == NULL) || ($args[$i+1] == "") || ($args[$i + 1]{0} == "-")) {
            drush_set_error('DRUSH_INVALID_INPUT', "Invalid input: -$opt needs to be followed by an argument.");
          }
          $options[$opt] = $args[$i + 1];
          $i++;
        }
        else {
          $options[$opt] = true;
        }
      }
    }
    // If it's not an option, it's a command.
    else {
      $arguments[] = $opt;
    }
  }
  // If arguments are specified, print the help screen.
  $arguments = sizeof($arguments) ? $arguments : array('help');

  drush_set_arguments($arguments);
  drush_set_context('options', $options);
}


/**
 * Get a list of all implemented commands.
 * This invokes hook_drush_command().
 *
 * @return
 *   Associative array of currently active command descriptors.
 *
 */
function drush_get_commands() {
  $commands = $available_commands = array();
  $list = drush_commandfile_list();
  foreach ($list as $commandfile => $path) {
    if (drush_command_hook($commandfile, 'drush_command')) {
      $function = $commandfile . '_drush_command';
      $result = $function();
      foreach ((array)$result as $key => $command) {
        // Add some defaults and normalize the command descriptor
        $command += array(
          'command' => $key,
          'command-hook' => $key,
          'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_LOGIN,
          'commandfile' => $commandfile,
          'path' => dirname($path),
          'engines' => array(), // Helpful for drush_show_help().
          'callback' => 'drush_command',
          'description' => NULL,
          'arguments' => array(),
          'options' => array(),
          'examples' => array(),
          'aliases' => array(),
          'deprecated-aliases' => array(),
          'extras' => array(),
          'core' => array(),
          'scope' => 'site',
          'drupal dependencies' => array(),
          'drush dependencies' => array(),
          'bootstrap_errors' => array(),
          'hidden' => FALSE,
        );
        // If command callback is correctly named, then fix
        // up the command entry so that drush_invoke will be
        // called.
        if ($command['callback'] != 'drush_command') {
          $required_command_prefix = 'drush_' . $commandfile . '_';
          if ((substr($command['callback'], 0, strlen($required_command_prefix)) == $required_command_prefix)) {
            $command['command-hook'] = substr($command['callback'], strlen($required_command_prefix));
            $command['callback'] = 'drush_command';
          }
          else {
            $command['callback-required-prefix'] = $required_command_prefix;
          }
        }
        // Enforce the no-spaces in command names rule
        if ((!drush_get_option('allow-spaces-in-commands', FALSE)) && (strpos($key, ' ') !== FALSE)) {
          $command['must-replace-spaces'] = TRUE;
        }
        // Temporary:  if there is a dash in the command name,
        // then make a deprecated alias that has a space in the name.
        if (strpos($key, '-') !== FALSE) {
          $command['deprecated-aliases'][] = str_replace('-', ' ', $key);
        }

        // Collect all the commands (without filtering) so we can match non-executable
        // commands, and later explain why they are not executable.
        drush_enforce_requirement_bootstrap_phase($command);
        drush_enforce_requirement_core($command);
        drush_enforce_requirement_drupal_dependencies($command);
        $commands[$key] = $command;
        // For every alias, make a copy of the command and store it in the command list
        // using the alias as a key
        if (isset($command['aliases']) && count($command['aliases'])) {
          foreach ($command['aliases'] as $alias) {
            $commands[$alias] = $command;
            $commands[$alias]['is_alias'] = TRUE;
          }
        }
        // Do the same operation on the deprecated aliases.
        if (isset($command['deprecated-aliases']) && count($command['deprecated-aliases'])) {
          foreach ($command['deprecated-aliases'] as $alias) {
            $commands[$alias] = $command;
            $commands[$alias]['is_alias'] = TRUE;
            $commands[$alias]['deprecated'] = TRUE;
            $commands[$alias]['deprecated-name'] = $alias;
            if ((!drush_get_option('allow-spaces-in-commands', FALSE)) && (strpos($alias, ' ') !== FALSE)) {
              $commands[$alias]['must-not-use-spaces'] = TRUE;
            }
          }
        }

      }
    }
  }

  return drush_set_context('DRUSH_COMMANDS', $commands);
}

/**
 * Matches a commands array, as returned by drush_get_arguments, with the
 * current command table.
 *
 * Note that not all commands may be discoverable at the point-of-call,
 * since Drupal modules can ship commands as well, and they are
 * not available until after bootstrapping.
 *
 * drush_parse_command returns a normalized command descriptor, which
 * is an associative array with the following entries:
 * - callback: name of function to invoke for this command.  The callback
 *     function name _must_ begin with "drush_commandfile_", where commandfile
 *     is from the file "commandfile.drush.inc", which contains the
 *     commandfile_drush_command() function that returned this command.
 *     Note that the callback entry is optional; it is preferable to
 *     omit it, in which case drush_invoke() will generate the hook function name.
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
 *   drush_parse_command();
 *
 */
function drush_parse_command() {
  $args = drush_get_arguments();

  // Get a list of all implemented commands.
  $implemented = drush_get_commands();

  $command = FALSE;
  $arguments = array();
  // Try to determine the handler for the current command.
  while (!$command && count($args)) {
    $part = implode(" ", $args);
    if (isset($implemented[$part])) {
      $command = $implemented[$part];
    }
    else {
      $arguments[] = array_pop($args);
    }
  }

  // We have found a command that matches. Set the appropriate values.
  if ($command) {
    // Special case. Force help command if --help option was specified.
    if (drush_get_option(array('h', 'help'))) {
      $arguments = array($command['command']);
      $command = $implemented['help'];
      $command['arguments'] = $arguments;
    }
    else {
      $arguments = array_reverse($arguments);

      // Merge specified callback arguments, which precede the arguments passed on the command line.
      if (isset($command['callback arguments']) && is_array($command['callback arguments'])) {
        $arguments = array_merge($command['callback arguments'], $arguments);
      }
    }
    $command['arguments'] = $arguments;
    drush_set_command($command);
  }
  return $command;
}

/**
 * Invoke drush api calls.
 *
 * Call the correct hook for all the modules that implement it.
 * Additionally, the ability to rollback when an error has been encountered is also provided.
 * If at any point during execution, the drush_get_error() function returns anything but 0,
 * drush_invoke() will trigger $hook_rollback for each of the hooks that implement it,
 * in reverse order from how they were executed.
 *
 * This function will also trigger pre_$hook and post_$hook variants of the hook
 * and its rollbacks automatically.
 *
 * HOW DRUSH HOOK FUNCTIONS ARE NAMED:
 *
 * The name of the hook is composed from the name of the command and the name of
 * the command file that the command definition is declared in.  The general
 * form for the hook filename is:
 *
 *      drush_COMMANDFILE_COMMANDNAME
 *
 * In many cases, drush commands that are functionally part of a common collection
 * of similar commands will all be declared in the same file, and every command
 * defined in that file will start with the same command prefix.  For example, the
 * command file "pm.drush.inc" defines commands such as "pm-enable" and "pm-disable".
 * In the case of "pm-enable", the command file is "pm", and and command name is
 * "pm-enable".  When the command name starts with the same sequence of characters
 * as the command file, then the repeated sequence is dropped; thus, the command
 * hook for "pm-enable" is "drush_pm_enable", not "drush_pm_pm_enable".
 *
 * @param command
 *   The drush command to execute.
 * @return
 *   A boolean specifying whether or not the command was successfully completed.
 *
 */
function drush_invoke($command) {
  drush_command_include($command);
  $args = func_get_args();
  array_shift($args);

  // Generate the base name for the hook by using the
  // php string translation function to convert all
  // dashes and spaces in the command name to underscores.
  // TODO:  put this back to $hook = str_replace("-", "_", $command);
  // for better readability after the allow-spaces-in-commands
  // backwards-compatibility feature is removed.
  $hook = strtr($command, "- ", "__"); // n.b. str tr, not str str.
  $list = drush_commandfile_list();

  $functions = array();
  // First we build a list of functions that are about to be executed
  $variations = array($hook . "_validate", "pre_$hook", $hook, "post_$hook");
  $all_available_hooks = array();
  foreach ($variations as $var_hook) {
    foreach ($list as $commandfile => $filename) {
      $oldfunc = sprintf("drush_%s_%s", $commandfile, $var_hook);
      $func = str_replace('drush_' . $commandfile . '_' . $commandfile, 'drush_' . $commandfile, $oldfunc);
      $all_available_hooks[] = $func;
      if (($oldfunc != $func) && (function_exists($oldfunc))) {
        drush_log(dt("The drush command hook naming conventions have changed; the function !oldfunc must be renamed to !func.  The old function will be called, but this will be removed shortly.", array('!oldfunc' => $oldfunc, '!func' => $func)), "error");
        // TEMPORARY:  Allow the function to be called by its old name.
        $functions[] = $oldfunc;
      }
      if (function_exists($func)) {
        $functions[] = $func;
      }
    }
  }
  // If no hook functions were found, print a warning.
  if (empty($functions)) {
    drush_log(dt("No hook functions were found for !command.", array('!command' => $command)), 'warning');
    drush_log(dt("Available drush_invoke() hooks for !command: !available", array('!command' => $command, '!available' => "\n" . implode("\n", $all_available_hooks))), 'warning');
  }
  elseif (drush_get_option('show-invoke')) {
    drush_log(dt("Available drush_invoke() hooks for !command: !available", array('!command' => $command, '!available' => "\n" . implode("\n", $all_available_hooks))), 'internals');
  }
  $rollback = FALSE;
  $completed = array();
  $available_rollbacks = array();
  foreach ($functions as $func) {
    $available_rollbacks[] = $func . '_rollback';
    if ($rollback === FALSE) {
      $completed[] = $func;
      if (function_exists($func)) {
        call_user_func_array($func, $args);
        _drush_log_drupal_messages();
        if (drush_get_error()) {
          drush_log(dt('An error occurred at function : @func', array('@func' => $func)), 'error');
          $rollback = TRUE;
        }
      }
    }
  }
  if (drush_get_option('show-invoke')) {
    drush_log(dt("Available rollback hooks for !command: !rollback", array('!command' => $command, '!rollback' => "\n" . implode("\n", $available_rollbacks))), 'internals');
  }

  // something went wrong, we need to undo
  if ($rollback) {
    foreach (array_reverse($completed) as $func) {
      $rb_func = $func . '_rollback';
      if (function_exists($rb_func)) {
        call_user_func_array($rb_func, $args);
        _drush_log_drupal_messages();
        drush_log("Changes for $func module have been rolled back.", 'rollback');
      }
    }
  }

  return !$rollback;
}


/**
 * Entry point for commands into the drush_invoke API
 *
 * If a command does not have a callback specified, this function will be called.
 *
 * This function will trigger $hook_drush_init, then if no errors occur,
 * it will call drush_invoke() with the command that was dispatch.
 *
 * If no errors have occured, it will run $hook_drush_exit.
 */
function drush_command() {
  $args = func_get_args();
  $command = drush_get_command();

  foreach (drush_command_implements("drush_init") as $name) {
    $func = $name . '_drush_init';
    drush_log(dt("Initializing drush commandfile: !name", array('!name' => $name)), 'bootstrap');
    call_user_func_array($func, $args);
    _drush_log_drupal_messages();
  }

  if (!drush_get_error()) {
    call_user_func_array('drush_invoke', array_merge(array($command['command-hook']), $args));
  }

  if (!drush_get_error()) {
    foreach (drush_command_implements('drush_exit') as $name) {
      $func = $name . '_drush_exit';
      call_user_func_array($func, $args);
      _drush_log_drupal_messages();
    }
  }
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
  return call_user_func_array('drush_command_invoke_all_ref', $args);
}

function drush_command_invoke_all_ref($hook, &$reference_parameter) {
  $args = func_get_args();
  array_shift($args);
  // Insure that call_user_func_array can alter first parameter
  $args[0] = &$reference_parameter;
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
  foreach ($list as $commandfile => $file) {
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
  return isset($commands[$command]);
}

/**
 * Collect a list of all available drush command files.
 *
 * Scans the following paths for drush command files:
 *
 * - The "/path/to/drush/commands" folder.
 * - Folders listed in the 'include' option (see example.drushrc.php).
 * - The system-wide drush commands folder, e.g. /usr/share/drush/commands
 * - The ".drush" folder in the user's HOME folder.
 * - All modules in the current Drupal installation whether they are enabled or
 *   not. Commands implementing hook_drush_load() in MODULE.drush.load.inc with
 *   a return value FALSE will not be loaded.
 *
 * A drush command file is a file that matches "*.drush.inc".
 *
 * @see drush_scan_directory()
 *
 * @return
 *   An associative array whose keys and values are the names of all available
 *   command files.
 */
function drush_commandfile_list() {
  return drush_get_context('DRUSH_COMMAND_FILES', array());
}

function _drush_find_commandfiles($phase) {
  $cache =& drush_get_context('DRUSH_COMMAND_FILES', array());

  static $evaluated = array();
  static $deferred = array();

  $searchpath = array();
  switch ($phase) {
    case DRUSH_BOOTSTRAP_DRUSH:
      // Core commands shipping with drush
      $searchpath[] = realpath(dirname(__FILE__) . '/../commands/');

      // User commands, specified by 'include' option
      if ($include = drush_get_option(array('i', 'include'), FALSE)) {
        foreach (explode(":", $include) as $path) {
          $searchpath[] = $path;
        }
      }

      // System commands, residing in $SHARE_PREFIX/share/drush/commands
      $share_path = drush_get_context('SHARE_PREFIX', '/usr') . '/share/drush/commands';

      if (is_dir($share_path)) {
        $searchpath[] = $share_path;
      }

      // User commands, residing in ~/.drush
      if (!is_null(drush_server_home())) {
        $searchpath[] = drush_server_home() . '/.drush';
      }
      break;
    case DRUSH_BOOTSTRAP_DRUPAL_SITE:
      $searchpath[] = conf_path() . '/modules';
      // Too early for variable_get('install_profile', 'default'); Just use default.
      $searchpath[] = "profiles/default/modules";
      // Add all module paths, even disabled modules. Prefer speed over accuracy.
      $searchpath[] = 'sites/all/modules';
      break;
    case DRUSH_BOOTSTRAP_DRUPAL_FULL:
      // Add enabled module paths. Since we are bootstrapped,
      // we can use the Drupal API.
      foreach (module_list() as $module) {
        $filename = drupal_get_filename('module', $module);
        $searchpath[] = dirname($filename);
      }
      break;
  }

  if (sizeof($searchpath)) {
    // Build a list of all of the modules to attempt to load.
    // Start with any modules deferred from a previous phase.
    $list = $deferred;

    // Scan for drush command files; add to list for consideration if found.
    foreach (array_unique($searchpath) as $path) {
      if (is_dir($path)) {
        $files = drush_scan_directory($path, '/\.drush\.inc$/');
        foreach ($files as $filename => $info) {
          $module = basename($filename, '.drush.inc');
          // Only try to bootstrap modules that we have never seen before, or that we
          // have tried to load but did not due to an unmet _drush_load() requirement.
          if (!array_key_exists($module, $evaluated) && file_exists($filename)) {
            $evaluated[$module] = TRUE;
            $list[$module] = $filename;
          }
        }
      }
    }

    // Check each file in the consideration list; if there is
    // a modulename_drush_load() function in modulename.drush.load.inc,
    // then call it to determine if this file should be loaded.
    foreach ($list as $module => $filename) {
      $load_command = TRUE;
      $load_test_inc = dirname($filename) . "/" . $module . ".drush.load.inc";
      if (file_exists($load_test_inc)) {
        require_once($load_test_inc);
        $load_test_func = $module . "_drush_load";
        if (function_exists($load_test_func)) {
          $load_command = $load_test_func($phase);
        }
      }
      if ($load_command) {
        require_once(realpath($filename));
        unset($deferred[$module]);
      }
      else {
        unset($list[$module]);
        // Signal that we should try again on
        // the next bootstrap phase.  We set
        // the flag to the filename of the first
        // module we find so that only that one
        // will be retried.
        $deferred[$module] = $filename;
      }
    }

    if (sizeof($list)) {
      $cache = array_merge($cache, $list);
      ksort($cache);
    }
  }
}

/**
 * Conditionally include files based on the command used.
 *
 * Steps through each of the currently loaded commandfiles and
 * loads an optional commandfile based on the key.
 *
 * When a command such as 'pm install' is called, this
 * function will find all 'install.pm.inc' files that
 * are present in each of the commandfile directories.
 */
function drush_command_include($command) {
  $parts = explode('-', $command);
  $command = implode(".", array_reverse($parts));

  $commandfiles = drush_commandfile_list();
  $options = array();
  foreach ($commandfiles as $commandfile => $file) {
    $filename = sprintf("%s/%s.inc", dirname($file), $command);
    if (file_exists($filename)) {
      drush_log(dt('Including !filename', array('!filename' => $filename)), 'bootstrap');
      include_once($filename);
    }
  }
}

/**
 * Conditionally include default options based on the command used.
 */
function drush_command_default_options($command = NULL) {
  if (!$command) {
    $command = drush_get_command();
  }
  if ($command) {
    // Look for command-specific options for this command
    // keyed both on the command's primary name, and on each
    // of its aliases.
    $options_were_set = _drush_command_set_default_options($command['command']);
    if (isset($command['aliases']) && count($command['aliases'])) {
      foreach ($command['aliases'] as $alias) {
        if (_drush_command_set_default_options($alias) === TRUE) {
          $options_were_set = TRUE;
        }
      }
    }
    // Take the time here to clear out any options that may
    // have "--no-xxx" overrides on the command line.
    $commandline_options = drush_get_context('options');
    foreach ($commandline_options as $key => $value) {
      if (substr($key, 0, strlen("no-") ) == "no-") {
        drush_unset_option(substr($key, strlen("no-")));
        $options_were_set = TRUE;
      }
    }
    // If we set or cleared any options, go back and re-bootstrap any global
    // options such as -y and -v.
    if ($options_were_set) {
      _drush_bootstrap_global_options();
    }
  }
}

function _drush_command_set_default_options($command) {
  $options_were_set = FALSE;
  $command_default_options = drush_get_context('command-specific');
  if (array_key_exists($command, $command_default_options)) {
    foreach ($command_default_options[$command] as $key => $value) {
      // We set command-specific options in their own context
      // that is higher precidence than the various config file
      // context, but lower than command-line options.
      drush_set_option($key, $value, 'specific');
      $options_were_set = TRUE;
    }
  }
  return $options_were_set;
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
 * @param $recurse_max_depth
 *   When TRUE, the directory scan will recurse the entire tree
 *   starting at the provided directory.  When FALSE, only files
 *   in the provided directory are returned.  Integer values
 *   limit the depth of the traversal, with zero being treated
 *   identically to FALSE, and 1 limiting the traversal to the
 *   provided directory and its immediate children only, and so on.
 * @param $key
 *   The key to be used for the returned array of files. Possible
 *   values are "filename", for the path starting with $dir,
 *   "basename", for the basename of the file, and "name" for the name
 *   of the file without an extension.
 * @param $min_depth
 *   Minimum depth of directories to return files from.
 * @param $include_dot_files
 *   If TRUE, files that begin with a '.' will be returned if they
 *   match the provided mask.  If FALSE, files that begin with a '.'
 *   will not be returned, even if they match the provided mask.
 * @param $depth
 *   Current depth of recursion. This parameter is only used internally and should not be passed.
 *
 * @return
 *   An associative array (keyed on the provided key) of objects with
 *   "path", "basename", and "name" members corresponding to the
 *   matching files.
 */
function drush_scan_directory($dir, $mask, $nomask = array('.', '..', 'CVS'), $callback = 0, $recurse_max_depth = TRUE, $key = 'filename', $min_depth = 0, $include_dot_files = FALSE, $depth = 0) {
  $key = (in_array($key, array('filename', 'basename', 'name')) ? $key : 'filename');
  $files = array();

  if (is_dir($dir) && $handle = opendir($dir)) {
    while (FALSE !== ($file = readdir($handle))) {
      if (!in_array($file, $nomask) && (($include_dot_files && (!preg_match("/\.\+/",$file))) || ($file[0] != '.'))) {
        if (is_dir("$dir/$file") && (($recurse_max_depth === TRUE) || ($depth < $recurse_max_depth))) {
          // Give priority to files in this folder by merging them in after any subdirectory files.
          $files = array_merge(drush_scan_directory("$dir/$file", $mask, $nomask, $callback, $recurse_max_depth, $key, $min_depth, $include_dot_files, $depth + 1), $files);
        }
        elseif ($depth >= $min_depth && preg_match($mask, $file)) {
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

/**
 * Check that a command is valid for the current bootstrap phase.
 *
 * @param $command
 *   Command to check. Any errors will be added to the 'bootstrap_errors' element.
 *
 * @return
 *   TRUE if command is valid.
 */
function drush_enforce_requirement_bootstrap_phase(&$command) {
  $valid = array();
  $current_phase = drush_get_context('DRUSH_BOOTSTRAP_PHASE');
  if ($command['bootstrap'] <= $current_phase) {
    return TRUE;
  }
  // TODO: provide description text for each bootstrap level so we can give
  // the user something more helpful and specific here.
  $command['bootstrap_errors']['DRUSH_COMMAND_INSUFFICIENT_BOOTSTRAP'] = dt('Command !command needs a higher bootstrap level to run - you will need invoke drush from a more functional Drupal environment to run this command.', array('!command' => $command['command']));
}

/**
 * Check that a command has its declared dependencies available or have no
 * dependencies.
 *
 * @param $command
 *   Command to check. Any errors  will be added to the 'bootstrap_errors' element.
 *
 * @return
 *   TRUE if command is valid.
 */
function drush_enforce_requirement_drupal_dependencies(&$command) {
  if (empty($command['drupal dependencies'])) {
    return TRUE;
  }
  else {
    foreach ($command['drupal dependencies'] as $dependency) {
      if (function_exists('module_exists') && module_exists($dependency)) {
        return TRUE;
      }
    }
  }
  $command['bootstrap_errors']['DRUSH_COMMAND_DEPENDENCY_ERROR'] = dt('Command !command needs the following modules installed/enabled to run: !dependencies.', array('!command' => $command['command'], '!dependencies' => implode(', ', $command['drupal dependencies'])));
}

/**
 * Check that a command is valid for the current major version of core.
 *
 * @param $command
 *   Command to check. Any errors  will be added to the 'bootstrap_errors' element.
 *
 * @return
 *   TRUE if command is valid.
 */
function drush_enforce_requirement_core(&$command) {
  $core = $command['core'];
  if (empty($core) || in_array(drush_drupal_major_version(), $core)) {
    return TRUE;
  }
  $versions = array_pop($core);
  if (!empty($core)) {
    $versions = implode(', ', $core) . dt(' or ') . $versions;
  }
  $command['bootstrap_errors']['DRUSH_COMMAND_CORE_VERSION_ERROR'] = dt('Command !command requires Drupal core version !versions to run.', array('!command' => $command['command'], '!versions' => $versions));
}
