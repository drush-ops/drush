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
 *      drupal   : Loaded from the drushrc.php file in the Drupal root directory.
 *      user     : Loaded from the drushrc.php file in the user's home directory.
 *      system   : Loaded from the drushrc.php file in the system's $PREFIX/etc/drush directory.
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
function _drush_config_file($context) {
  $configs = array();

  // Did the user explicitly specify a config file?
  if ($config = drush_get_option(array('c', 'config'))) {
    $configs['custom'] = $config;
  }

  if ($site_path = drush_get_context('DRUSH_DRUPAL_SITE_ROOT')) {
    $configs['site'] = $site_path . "/drushrc.php";
  }

  if ($drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT')) {
    $configs['drupal'] = $drupal_root . '/drushrc.php';
  }

  // in the user home directory
  // $_SERVER['HOME'] isn't set on windows and generates a Notice.
  if(isset($_SERVER['HOME'])){
    $configs['user'] = $_SERVER['HOME'] . '/.drushrc.php';
  }
  else{
    // home on windows
    $configs['user'] = $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'] . '/.drushrc.php';
  }


  // In the system wide configuration folder.
  $configs['system'] = drush_get_context('ETC_PREFIX', '') . '/etc/drush/drushrc.php';

  // in the drush installation folder
  $configs['drush'] = dirname(__FILE__) . '/../drushrc.php';

  return empty($configs[$context]) ? '' : $configs[$context];
}


/**
 * Load drushrc files (if available) from several possible locations.
 */
function drush_load_config($context) {
  global $drush_conf_override;
  
  $drush_conf_override = array();

  $config = _drush_config_file($context);
  if (file_exists($config)) {
    drush_log(dt('Loading drushrc "!config" into "!context" scope.', array('!config' => realpath($config), '!context' => $context)), 'bootstrap');
    require_once($config);
    
    // If site aliases are defined in multiple drush configuration files,
    // the normal mechanism would cause the highest priority 'site-aliases'
    // array to mask all of the other aliases defined in different contexts.
    // To allow all of the aliases defined in every context to be used together,
    // we merge them all into the 'drush' context as they are encountered.
    if (($context != 'drush') && (array_key_exists('site-aliases', $options))) {
      $cache =& drush_get_context('drush');
      if (array_key_exists('site-aliases', $cache)) {
        $cache['site-aliases'] = array_merge($cache['site-aliases'], $options['site-aliases']);
      }
      else {
        $cache['site-aliases'] = $options['site-aliases'];
      }
      unset($options['site-aliases']);
    }
    
    drush_set_context($context, $options);
    
    // Instruct core not to queries since we are not outputting them.
    // This can be overridden by a command or a drushrc file if needed.
    if (!isset($drush_conf_override['dev_query'])) {
      $drush_conf_override['dev_query'] = FALSE;
    }

    /**
     * Allow the drushrc.php file to override $conf settings.
     * This is a separate variable because the $conf array gets
     * initialized to an empty array, in the drupal bootstrap process,
     * and changes in settings.php would wipe out the drushrc.php settings.
     */
    if (!empty($override)) {
      $drush_conf_override = array_merge($drush_conf_override, $override);
    }
  }
}

/**
 * Set a specific context.
 *
 * @param context
 *   Any of the default defined contexts.
 * @param value 
 *   The value to store in the context
 * 
 * @return
 *   An associative array of the settings specified in the request context.
 */
function drush_set_context($context, $value) {
  $cache =& drush_get_context($context);
  $cache = $value;
  return $value;
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
 *     $cache = &drush_get_context($context);
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
function &drush_get_context($context = null, $default = null) {
  static $cache = array();
  if (!is_null($context)) {
    if (!isset($cache[$context])) {
      $default = !is_null($default) ? $default : array();
      $cache[$context] = $default;
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
  return drush_get_context('arguments');
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
  return drush_get_context('command');
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
    $value = _drush_get_option($option, drush_get_context($context));
  }
  else {
    // We are not checking a specific context, so check them in a predefined order of precedence.
    static $contexts = array(
      'process', 'options', 'stdin', 'alias',
      'custom', 'site', 'drupal', 'user', 'system', 
      'drush', 'default');

    foreach ($contexts as $context) {
      $value = _drush_get_option($option, drush_get_context($context));

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
      'process', 'options', 'stdin', 'alias', 
      'custom', 'site', 'drupal', 'user', 'system',
      'drush', 'default');
  $cache = drush_get_context();
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
  $cache =& drush_get_context($context);
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
  $cache =& drush_get_context($context);
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
  $filename = _drush_config_file($context);

  if ($filename) {
    $cache = drush_get_context($context);

    $fp = fopen($filename, "w+");
    if (!$fp) {
      return drush_set_error('DRUSH_PERM_ERROR', dt('Drushrc (!filename) could not be written', array('!filename' => $filename)));
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
      drush_log(dt('Drushrc file (!filename) was written successfully', array('!filename' => $filename)));
      return true;
    }

  }
  return false;
}
