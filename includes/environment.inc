<?php
// $Id$

/**
 * @file
 * Functions used by drush to query the environment and
 * setting the current configuration.
 */

/**
 * The indicator for a Drupal installation folder.
 */
define('DRUSH_DRUPAL_BOOTSTRAP', 'includes/bootstrap.inc');

/**
 * Load drushrc files (if available) from several possible locations.
 */
function drush_load_config() {
  global $override, $args;

  // Did the user explicitly specify a config file?
  if ($config = drush_get_option(array('c', 'config'), FALSE)) {
    // If so, we use only that ...
    $options = array();
    require_once($config);
    if (is_array($options)) {
      // Sets all the default options for drush
      $args['options'] = array_merge($options, $args['options']);
    }
  }
  else {
    // Otherwise, scan the workspace for config files in
    // reverse order of precedence.

    // in the current site directory
    $configs[] = drush_site_path() . "/drushrc.php";

    // in the located drupal root
    $configs[] = drush_get_option(array('r', 'root'), drush_locate_root()). '/drushrc.php';

    // in the user home directory
    $configs[] = $_SERVER['HOME'] . '/.drushrc.php';

    // in the drush installation folder
    // TODO: This really should be a proper, system-wide location.
    $configs[] = dirname(__FILE__) . '/../drushrc.php';

    $options = array();
    foreach ($configs as $config) {
      if (file_exists($config)) {
        // Sets all the default options for drush
        require_once($config);
        $args['options'] = array_merge($options, $args['options']);
      }
    }
  }
  if (DRUSH_BACKEND) {
    $stdin_options = _drush_backend_get_stdin();
    if (is_array($stdin_options)) {
      $args['options'] = array_merge($args['options'], $stdin_options);
    }
  }
}

/**
 * Returns the current working directory.
 *
 * TODO: Could cache result, but it isn't really expensive.
 */
function drush_cwd() {
  // We use PWD if available because getcwd() resolves symlinks, which
  // could take us outside of the Drupal root, making it impossible to find.
  $path = $_SERVER['PWD'];
  if (empty($path)) {
    $path = getcwd();
  }

  // Convert windows paths.
  $path = _drush_convert_path($path);

  return $path;
}

/**
 * Converts a Windows path (dir1\dir2\dir3) into a Unix path (dir1/dir2/dir3).
 */
function _drush_convert_path($path) {
  return str_replace('\\','/', $path);
}

/**
 * Returns parent directory.
 *
 * @param string
 *   Path to start from.
 *
 * @return string
 *   Parent path of given path.
 */
function _drush_shift_path_up($path) {
  if (empty($path)) {
    return FALSE;
  }
  $path = explode('/', $path);
  // Move one directory up.
  array_pop($path);
  return implode('/', $path);
}

/**
 * Like Drupal conf_path, but searching from beneath.
 * Allows proper site uri detection in site sub-directories.
 *
 * Essentially looks for a settings.php file.
 *
 * @param string
 *   Search starting path. Defaults to current working directory.
 *
 * @return
 *   Current site path (folder containing settings.php) or FALSE if not found.
 */
function drush_site_path($path = NULL) {
  static $site_path;
  
  if (!isset($site_path)) {
    $site_path = FALSE;

    $path = empty($path) ? drush_cwd() : $path;
    // Check the current path.
    if (file_exists($path . '/settings.php')) {
      $site_path = $path;
    }
    else {
      // Move up dir by dir and check each.
      while ($path = _drush_shift_path_up($path)) {
        if (file_exists($path . '/settings.php')) {
          $site_path = $path;
          break;
        }
      }
    }
    
    // Last resort: try from site root
    if (!$site_path) {
      if ($site_root = drush_locate_root()) {
        if (file_exists($site_root . '/sites/default/settings.php')) {
          $site_path = $site_root . '/sites/default';
        }
      }
    }
  }

  return $site_path;
}

/**
 * Attempts to determine site URI from the given path, using
 * a couple of heuristics.
 *
 * @param
 *   Starting path or current working directory.
 *
 * @return
 *   Site uri string or false if none was found.
 */
function drush_site_uri($path = NULL) {
  $uri = FALSE;

  if ($site_path = drush_site_path($path)) {
    // TODO: the method of including the settings.php file here is 
    // problematic, since it may contain code that will be executed.
    // An alternative approach would be to use a regexp for extracting
    // a possible $base_url setting.

    // Export the following settings.php variables to the global namespace.
    global $databases, $db_url, $db_prefix, $cookie_domain, $conf, $installed_profile, $update_free_access;
    // If the settings.php has a defined path we use the URI from that.
    include_once($site_path . '/settings.php');
    if (isset($base_url)) {
      $uri = $base_url;
    }
    else {
      // Alternatively we default to the name of the current directory, if it is not 'default'.
      $elements = explode('/', $site_path);
      $current = array_pop($elements);
      if ($current != 'default') {
        $uri = 'http://'. $current;
      }
    }
  }

  return $uri;
}

/**
 * Exhaustive depth-first search to try and locate the Drupal root directory.
 * This makes it possible to run drush from a subdirectory of the drupal root.
 *
 * @param
 *   Search start path. Defaults to current working directory.
 */
function drush_locate_root($path = NULL) {
  static $drupal_root;
  
  if (!isset($drupal_root)) {
    $drupal_root = FALSE;

    $path = empty($path) ? drush_cwd() : $path;
    // Check the start path.
    if (drush_valid_drupal_root($path)) {
      $drupal_root = $path;
    }
    else {
      // Move up dir by dir and check each.
      while ($path = _drush_shift_path_up($path)) {
        if (drush_valid_drupal_root($path)) {
          $drupal_root = $path;
          break;
        }
      }
    }
  }

  return $drupal_root;
}

/**
 * Checks whether given path qualifies as a Drupal root.
 *
 * @param string
 *   Path to check.
 *
 * @return boolean
 *   True if given path seems to be a Drupal root, otherwise false.
 */
function drush_valid_drupal_root($path) {
  return !empty($path) && is_dir($path) && file_exists($path . '/' . DRUSH_DRUPAL_BOOTSTRAP);
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
  $commands = array();

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
      $commands[] = $opt;
    }
  }
  return array('options' => $options, 'commands' => $commands);
}

/**
 * Get the value for an option.
 *
 * If the first argument is an array, then it checks whether one of the options
 * exists and return the value of the first one found. Useful for allowing both
 * -h and --host-name
 *
 */
function drush_get_option($option, $default = NULL) {
  $options = $GLOBALS['args']['options'];
  if (is_array($option)) {
    foreach ($option as $current) {
      if (array_key_exists($current, $options)) {
        return $options[$current];
      }
    }
    return $default;
  }

  if (!array_key_exists($option, $options)) {
    return $default;
  }
  else {
    return $options[$option];
  }
}
