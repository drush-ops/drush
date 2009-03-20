<?php
// $Id$
/**
 * @file
 * The Drush context API implementation.
 *
 * This API acts as a storage mechanism for all options,
 * arguments and configuration settings that are loaded into drush.
 *
 * This API also acts as an IPC mechanism between the different drush
 * commands, and provides protection from accidentally overriding
 * settings that are needed by other parts of the system.
 *
 * It also avoids the necessity to pass references through
 * the command chain and allows the scripts to keep track
 * of whether any settings have changed since the previous
 * execution.
 *
 * This API defines several contexts that are used by default.
 *
 * Argument contexts :
 *   These contexts are used by Drush to store information on the command.
 *   They have their own access functions in the forms of @drush_set_arguments,
 *   @drush_get_arguments, @drush_set_command, @drush_get_command.
 *
 *     command : The drush command being executed.
 *     arguments : Any additional arguments that were specified.
 *
 * Setting contexts :
 *   These contexts store options that have been passed to the drush.php
 *   script, either through the use of any of the config files, directly
 *   from the command line through --option='value' or through a JSON
 *   encoded string passed through the STDIN pipe.
 *
 *   These contexts are accessible through the @drush_get_option and @drush_set_option
 *   functions.
 *
 *   These contexts are evaluated in a certain order, and the highest priority value
 *   is returned by default from drush_get_option. This allows scripts to check whether
 *   an option was different before the current execution.
 *
 *    Specified by the script itself : 
 *      process  : Generated in the current process.
 *      options  : Passed as --option=value to the command line.
 *      stdin    : Passed as a JSON encoded string through stdin.
 *
 *    Specified by config files : 
 *      custom   : Loaded from the config file specified by --config or -c
 *      site     : Loaded from the drushrc.php file in the Drupal sites directory.
 *      platform : Loaded from the drushrc.php file in the Drupal root directory.
 *      user     : Loaded from the drushrc.php file in the user's home directory.
 *      drush    : Loaded from the drushrc.php file in the same directory as drush.php.
 *
 *    Specified by the script, but has the lowest priority :
 *      default  : The script might provide some sensible defaults during init.
 *
 *   Drush commands may also choose to save settings for a specific context to the matching configuration
 *   file through the drush_save_config() function.
 */


/**
 * Return a list of possible drushrc file locations.
 *
 * @return
 *   An associative array containing possible config files to load
 *   The keys are the 'context' of the files, the values are the file
 *   system locations.
 */
function _drush_config_files() {
  $configs = array();

  // Did the user explicitly specify a config file?
  if ($config = drush_get_option(array('c', 'config'), FALSE)) {
    $configs['custom'] = $config;
  }

  // in the current site directory
  $configs['site'] = drush_site_path() . "/drushrc.php";

  // in the located drupal root
  $configs['platform'] = drush_get_option(array('r', 'root'), drush_locate_root()). '/drushrc.php';

  // in the user home directory
  $configs['user'] = $_SERVER['HOME'] . '/.drushrc.php';

  // in the drush installation folder
  // TODO: This really should be a proper, system-wide location.
  $configs['drush'] = dirname(__FILE__) . '/../drushrc.php';

  return $configs;
}


/**
 * Load drushrc files (if available) from several possible locations.
 */
function drush_load_config() {
  global $override;

  $configs = _drush_config_files();
  foreach ($configs as $context => $config) {
    $options = array();
    if (file_exists($config)) {
      // Sets all the default options for drush
      require_once($config);
      drush_set_context($context, $options);
    }
  }
}

/**
 * Set a specific context.
 *
 * @param context
 *   Any of the default defined contexts.
 * @param options
 *   The value to store in the context
 * @param merge
 *   Optional. Should the pervious options be merged, retaining any settings not defined in options.
 * 
 * @return
 *   An associative array of the settings specified in the request context.
 */
function drush_set_context($context, $options, $merge = FALSE) {

  $options = (array) $options;

  $cache =& _drush_get_context($context);

  if ($merge) {
    $cache = array_merge($cache, $options);
  }
  else {
    $cache = $options;
  }

  return $cache;
}


/**
 * Return a specific context, or the whole context cache
 *
 * This function provides a storage mechanism for any information
 * the currently running process might need to communicate.
 *
 * This avoids the use of globals, and constants.
 *
 * Functions that operate on the context cache, can retrieve a reference
 * to the context cache using :
 *     $cache = &_drush_get_context($context);
 *
 * This is a private function, because it is meant as an internal 
 * generalized API for writing static cache functions, not as a general
 * purpose function to be used inside commands.
 * 
 * Code that modifies the reference directly might have unexpected consequences,
 * such as modifying the arguments after they have already been parsed and dispatched
 * to the callbacks.
 *
 * @param context
 *   Optional. Any of the default defined contexts. 
 @
 * @return 
 *   If context is not supplied, the entire context cache will be returned.
 *   Otherwise only the requested context will be returned.
 *   If the context does not exist yet, it will be initialied to an empty array.
 */
function &_drush_get_context($context = null) {
  static $cache = array();
  if (!is_null($context)) {
    if (!isset($cache[$context])) {
      $cache[$context] = array();
    }
    return $cache[$context];
  }
  return $cache;
}

/**
 * Set the arguments passed to the drush.php script.
 *
 * This function will set the 'arguments' context of the current running script.
 *
 * When initially called by drush_parse_options, the entire list of arguments will
 * be populated, once the command is dispatched, this will be set to only the remaining
 * arguments to the command.
 *
 * @param arguments
 *   Command line arguments, as an array. 
 */
function drush_set_arguments($arguments) {
  drush_set_context('arguments', $arguments);
}

/**
 * Get the arguments passed to the drush.php script.
 *
 * When drush_set_arguments is initially called by drush_parse_options, 
 * the entire list of arguments will be populated.
 * Once the command has been dispatched, this will be return only the remaining
 * arguments to the command.
 */
function drush_get_arguments() {
  return _drush_get_context('arguments');
}

/**
 * Set the command being executed.
 *
 * Drush_dispatch will set the correct command based on it's
 * matching of the script arguments retrieved from drush_get_arguments
 * to the implemented commands specified by drush_get_commands.
 *
 * @param
 *   A numerically indexed array of command components.
 */
function drush_set_command($command) {
  drush_set_context('command', $command);
}

/**
 * Return the command being executed.
 *
 * 
 */
function drush_get_command() {
  return _drush_get_context('command');
}

/**
 * Parse console arguments.
 *
 * @param $args
 *   The console argument array (usually $argv)
 * @param $arg_opts
 *   An array of options that are followed by an argument.
 *   e.g. shell.php -u admin -v --> $arg_opts = array('u')
 * @param $default_options
 * @return
 *   A associative array:
 *   $return['commands'] ia a numeric array of all commands,
 *   $return['options'] contains the options. The option keys
 *   are always noted without - or -- and are set to TRUE if they were
 *   invoked, to the argument if followed by an argument, and if not present
 *   to their default value or FALSE if no default value was specified.
 */
function drush_parse_args($args = array(), $arg_opts = array(), $default_options = array()) {
  $options = $default_options;
  $arguments = array();

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
            exit("Invalid input: -$opt needs to be followed by an argument.");
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
  drush_set_arguments($arguments);
  drush_set_context('options', $options);

  if (DRUSH_BACKEND) {
    // Load options passed as a JSON encoded string through STDIN.
    $stdin_options = _drush_backend_get_stdin();
    if (is_array($stdin_options)) {
      drush_set_context('stdin', $stdin_options);
    }
  }
}

/**
 * Get the value for an option.
 *
 * If the first argument is an array, then it checks whether one of the options
 * exists and return the value of the first one found. Useful for allowing both
 * -h and --host-name
 *
 * @param option
 *   The name of the option to get
 * @param default
 *   Optional. The value to return if the option has not been set
 * @param context
 *   Optional. The context to check for the option. If this is set, only this context will be searched.
 */
function drush_get_option($option, $default = NULL, $context = NULL) {
  $value = null;

  if ($context) {
    // We have a definite context to check for the presence of an option.
    $value = _drush_get_option($option, _drush_get_context($context));
  }
  else {
    // We are not checking a specific context, so check them in a predefined order of precedence.
    static $contexts = array(
      'process', 'options', 'stdin', 
      'custom', 'site', 'platform', 'user', 
      'drush', 'default');

    foreach ($contexts as $context) {
      $value = _drush_get_option($option, _drush_get_context($context));

      if ($value !== null) {
        return $value;
      }
    }
  }

  if ($value !== null) {
    return $value;
  }

  return $default;
}

/**
 * Retrieves a collapsed list of all options
 */
function drush_get_merged_options() {
  static $contexts = array(
      'process', 'options', 'stdin', 
      'custom', 'site', 'platform', 'user',
      'drush', 'default');
  $cache = _drush_get_context();
  $result = array();
  foreach (array_reverse($contexts) as $context) {
    if (array_key_exists($context, $cache)) {
      $result = array_merge($result, $cache[$context]);
    }
  }

  return $result;
}

/**
 * Helper function to recurse through possible option names
 */
function _drush_get_option($option, $context) {
  if (is_array($option)) {
    foreach ($option as $current) {
      if (array_key_exists($current, $context)) {
        return $context[$current];
      }
    }
  }
  elseif (array_key_exists($option, $context)) {
    return $context[$option];
  }

  return null;
}

/**
 * Set an option in one of the option contexts.
 * 
 * @param option
 *   The option to set.
 * @param value
 *   The value to set it to.
 * @param context
 *   Optional. Which context to set it in.
 * @return
 *   The value parameter. This allows for neater code such as 
 *     $myvalue = drush_set_option('http_host', $_SERVER['HTTP_HOST']);
 *   Without having to constantly type out the value parameter.
 */
function drush_set_option($option, $value, $context = 'process') {
  $cache =& _drush_get_context($context);
  $cache[$option] = $value;
  return $value;
}

/**
 * A small helper function to set the value in the default context
 */
function drush_set_default($option, $value) {
  return drush_set_option($option, $value, 'default');
}

/**
 * Remove a setting from a specific context.
 *
 * @param
 *   Option to be unset
 * @param
 *   Context in which to unset the value in.
 */
function drush_unset_option($option, $context) {
  $cache =& _drush_get_context($context);
  if (array_key_exists($option, $cache)) {
    unset($cache[$option]);
  }
}

/**
 * Save the settings in a specific context to the applicable configuration file
 * This is useful is you want certain settings to be available automatically the next time a command is executed.
 *
 * @param $context
 *   The context to save
 */
function drush_save_config($context) {
  $files = _drush_config_files();

  if (array_key_exists($context, $files)) {
    $filename = $files[$context];

    $cache = _drush_get_context($context);

    $fp = fopen($filename, "w+");
    if (!$fp) {
      return drush_set_error(DRUSH_PERM_ERROR, dt('Config file (!filename) could not be written', array('!filename' => $filename)));
    }
    else {
      fwrite($fp, "<?php\n");
      $timestamp = mktime();
      foreach ($cache as $key => $value) {
        $line = "\n\$options['$key'] = ". var_export($value, TRUE) .';';
        fwrite($fp, $line);  
      }
      fwrite($fp, "\n");
      fclose($fp);
      return true;
    }

  }
  return false;
}
