<?php

/**
 * @file
 * The drush API implementation and helpers.
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
    return drush_set_error('DRUSH_ENGINE INCLUDE_NO_PATH', dt('No path was set for including the !type engine !engine.', array('!type' => $type, '!engine' => $engine)));
  }
  if (drush_include($path, $engine, $version)) {
    return TRUE;
  }
  return drush_set_error('DRUSH_ENGINE INCLUDE_FAILED', dt('Unable to include the !type engine !engine from !path.' , array('!path' => $path, '!type' => $type, '!engine' => $engine)));
}

/**
 * Detects the version number of the current Drupal installation,
 * if any. Returns FALSE if there is no current Drupal installation,
 * or it is somehow broken.
 *
 * @return
 *   A string containing the version number of the current
 *   Drupal installation, if any. Otherwise, return FALSE.
 */
function drush_drupal_version($drupal_root = NULL) {
  static $version = FALSE;

  if (!$version) {
    if (($drupal_root != NULL) || ($drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT'))) {
      // D7 stores VERSION in bootstrap.inc
      $version_constant_paths = array('/modules/system/system.module', '/includes/bootstrap.inc');
      foreach ($version_constant_paths as $path) {
        if (file_exists($drupal_root . $path)) {
          require_once $drupal_root . $path;
        }
      }
      // We just might be dealing with an early Drupal version (pre 4.7)
      if (defined('VERSION')) {
        $version = VERSION;
      }
    }
  }
  return $version;
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
  $error = "";

  // Don't check unless we have a datestamp in drush.info
  $drush_info = drush_read_drush_info();
  if (($drush_info === FALSE) || (!array_key_exists('datestamp', $drush_info))) {
    drush_log(dt('Cannot determine release date for drush'), 'notice');
    return FALSE;
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
    if ($dev_ok || ((!array_key_exists('version_extra', $release_info) || ($release_info['version_extra'] != 'dev')))) {
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
    drush_print(dt('A newer version of drush, !version, is available.  You are currently running drush version !currentversion; to update, run `drush self-update`.  To disable this check, put "$options[\'self-update\'] = FALSE;" in your drushrc.php configuration file.' . "\n", array('!version' => $newer_version, '!currentversion' => DRUSH_VERSION)));
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

function drush_drupal_cache_clear_all() {
  $prior = drush_get_context('DRUSH_AFFIRMATIVE');
  drush_set_context('DRUSH_AFFIRMATIVE', TRUE);
  drush_invoke('cache-clear', 'all');
  drush_set_context('DRUSH_AFFIRMATIVE', $prior);
}

/**
 * Returns the Drupal major version number (5, 6, 7 ...)
 */
function drush_drupal_major_version($drupal_root = NULL) {
  $major_version = FALSE;
  if ($version = drush_drupal_version($drupal_root)) {
    $version_parts = explode('.', $version);
    if (is_numeric($version_parts[0])) {
      $major_version = (integer)$version_parts[0];
    }
  }
  return $major_version;
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
  $options['uri']                = array('short-form' => 'l', 'description' => dt('URI of the drupal site to use (only needed in multisite environments or when running on an alternate port)'), 'example-value' => 'http://example.com:8888');
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
 * Prints out help for a given command.
 */
function drush_show_help($commandstring) {
  // First check and see if the command can already be found.
  $commands = drush_get_commands();
  if (!array_key_exists($commandstring, $commands)) {
    // If the command cannot be found, then bootstrap so that
    // additional commands will be brought in.
    // For speed, only bootstrap up to DRUSH_BOOTSTRAP_DRUPAL_SITE.
    drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_SITE);
    $commands = drush_get_commands();
  }
  if (array_key_exists($commandstring, $commands)) {
    $command = $commands[$commandstring];
    drush_print_help($command);
    return TRUE;
  }
  return drush_set_error('DRUSH_COMMAND_NOT_FOUND', dt('Invalid command !command.', array('!command' => $commandstring)));
}

/**
 * Print the help for a single command to the screen.
 *
 * @param array $command
 *   A fully loaded $command array.
 */
function drush_print_help($command) {
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

  // Give commandfiles an opportunity to add examples and options to the command.
  drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_SITE);
  drush_command_invoke_all_ref('drush_help_alter', $command);

  drush_print(wordwrap(implode("\n", $help), drush_get_context('DRUSH_COLUMNS', 80)));
  drush_print();

  foreach ($command['sections'] as $key => $value) {
    if (!empty($command[$key])) {
      drush_print(dt($value) . ':');
      $rows = drush_format_help_section($command, $key);
      drush_print_table($rows, FALSE, array(40));
      unset($rows);
      drush_print();
    }
  }

  // Append aliases if any.
  if ($command['aliases']) {
    drush_print(dt("Aliases: ") . implode(', ', $command['aliases']));
  }
}

/**
 * Format one named help section from a command record
 *
 * @param $command
 *   A command record with help information
 * @param $section
 *   The name of the section to format ('options', 'topic', etc.)
 * @returns array
 *   Formatted rows, suitable for printing via drush_print_table.
 */
function drush_format_help_section($command, $section) {
  $formatter = (function_exists('drush_help_section_formatter_' . $section)) ? 'drush_help_section_formatter_' . $section : 'drush_help_section_default_formatter';
  foreach ($command[$section] as $name => $help_attributes) {
    if (!is_array($help_attributes)) {
      $help_attributes = array('description' => $help_attributes);
    }
    $help_attributes['label'] = $name;
    call_user_func_array($formatter, array($command, &$help_attributes));
    $rows[] = array($help_attributes['label'], $help_attributes['description']);

    // Process the subsections too, if any
    if (!empty($command['sub-' . $section]) && array_key_exists($name, $command['sub-' . $section])) {
      $rows = array_merge($rows, _drush_format_help_subsection($command, $section, $name, $formatter));
    }
  }
  return $rows;
}

/**
 * Format one named portion of a subsection from a command record.
 * Subsections allow related parts of a help record to be grouped
 * together.  For example, in the 'options' section, sub-options that
 * are related to a particular primary option are stored in a 'sub-options'
 * section whose name == the name of the primary option.
 *
 * @param $command
 *   A command record with help information
 * @param $section
 *   The name of the section to format ('options', 'topic', etc.)
 * @param $subsection
 *   The name of the subsection (e.g. the name of the primary option)
 * @param $formatter
 *   The name of a function to use to format the rows of the subsection
 * @param $prefix
 *   Characters to prefix to the front of the label (for indentation)
 * @returns array
 *   Formatted rows, suitable for printing via drush_print_table.
 */
function _drush_format_help_subsection($command, $section, $subsection, $formatter, $prefix = '  ') {
  foreach ($command['sub-' . $section][$subsection] as $name => $help_attributes) {
    if (!is_array($help_attributes)) {
      $help_attributes = array('description' => $help_attributes);
    }
    $help_attributes['label'] = $name;
    call_user_func_array($formatter, array($command, &$help_attributes));
    $rows[] = array($prefix . $help_attributes['label'], $help_attributes['description']);
    // Process the subsections too, if any
    if (!empty($command['sub-' . $section]) && array_key_exists($name, $command['sub-' . $section])) {
      $rows = array_merge($rows, _drush_format_help_subsection($command, $section, $name, $formatter, $prefix . '  '));
    }
  }
  return $rows;
}

/**
 * The options section formatter.  Adds a "--" in front of each
 * item label.  Also handles short-form and example-value
 * components in the help attributes.
 */
function drush_help_section_formatter_options($command, &$help_attributes) {
  if ($help_attributes['label'][0] == '-') {
    drush_log(dt("Option '%option' of command %command should instead be declared as '%fixed'", array('%option' => $help_attributes['label'], '%command' => $command['command'], '%fixed' => preg_replace('/^--*/', '', $help_attributes['label']))), 'debug');
  }
  else {
    $help_attributes['label'] = '--' . $help_attributes['label'];
  }
  if (array_key_exists('example-value', $help_attributes)) {
    $help_attributes['label'] .= '=' . $help_attributes['example-value'];
    if (array_key_exists('short-form', $help_attributes)) {
      $help_attributes['short-form'] .= ' ' . $help_attributes['example-value'];
    }
  }
  if (array_key_exists('short-form', $help_attributes)) {
    $help_attributes['label'] = '-' . $help_attributes['short-form'] . ', ' . $help_attributes['label'];
  }
  drush_help_section_default_formatter($command, $help_attributes);
}

/**
 * The default section formatter.  Replaces '[command]' with the
 * command name.
 */
function drush_help_section_default_formatter($command, &$help_attributes) {
  // '[command]' is a token representing the current command. @see pm_drush_engine_version_control().
  $help_attributes['label'] = str_replace('[command]', $command['command'], $help_attributes['label']);
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
 * @defgroup outputfunctions Process output text.
 * @{

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
 * @param $handle
 *    File handle to write to.  NULL will write
 *    to standard output, STDERR will write to the standard
 *    error.  See http://php.net/manual/en/features.commandline.io-streams.php
 */
function drush_print($message = '', $indent = 0, $handle = NULL) {
  $msg = str_repeat(' ', $indent) . (string)$message . "\n";
  if (($charset = drush_get_option('output_charset')) && function_exists('iconv')) {
    $msg = iconv('UTF-8', $charset, $msg);
  }
  if (isset($handle)) {
    fwrite($handle, $msg);
  }
  else {
    print $msg;
  }
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
function drush_print_r($array, $handle = NULL) {
  drush_print(print_r($array, TRUE), 0, $handle);
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
 * Convert html to readable text.  Compatible API to
 * drupal_html_to_text, but less functional.  Caller
 * might prefer to call drupal_html_to_text if there
 * is a bootstrapped Drupal site available.
 *
 * @param string $html
 *   The html text to convert.
 *
 * @return string
 *   The plain-text representation of the input.
 */
function drush_html_to_text($html, $allowed_tags = NULL) {
  $replacements = array(
    '<hr>' => '------------------------------------------------------------------------------',
    '<li>' => '  * ',
    '<h1>' => '===== ',
    '</h1>' => ' =====',
    '<h2>' => '---- ',
    '</h2>' => ' ----',
    '<h3>' => '::: ',
    '</h3>' => ' :::',
    '<br/>' => "\n",
  );
  $text = str_replace(array_keys($replacements), array_values($replacements), $html);
  return html_entity_decode(preg_replace('/ *<[^>]*> */', ' ', $text));
}


/**
 * Print a formatted table.
 *
 * @param $rows
 *   The rows to print.
 * @param $header
 *   If TRUE, the first line will be treated as table header.
 * @param $widths
 *   The widths of each column (in characters) to use - if not specified this
 *   will be determined automatically, based on a "best fit" algorithm.
 * @param $handle
 *    File handle to write to.  NULL will write
 *    to standard output, STDERR will write to the standard
 *    error.  See http://php.net/manual/en/features.commandline.io-streams.php
 * @return $tbl
 *   Use $tbl->getTable() to get the output from the return value.
 */
function drush_print_table($rows, $header = FALSE, $widths = array(), $handle = NULL) {
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
  $output = $tbl->getTable();
  if (!stristr(PHP_OS, 'WIN')) {
    $output = str_replace("\r\n", PHP_EOL, $output);
  }

  // Check if the handle argument is a string to preserve compatability with
  // previous versions that accepted a filename instead.
  if (is_string($handle)) {
    file_put_contents($handle, $output, FILE_APPEND);
  }
  else {
    drush_print($output, 0, $handle);
  }
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
 * Print the contents of a file.
 *
 * @param string $file
 *   Full path to a file.
 */
function drush_print_file($file) {
  // Don't even bother to print the file in --no mode
  if (drush_get_context('DRUSH_NEGATIVE')) {
    return;
  }
  if ((substr($file,-4) == ".htm") || (substr($file,-5) == ".html")) {
    $tmp_file = drush_tempnam(basename($file));
    file_put_contents($tmp_file, drush_html_to_text(file_get_contents($file)));
    $file = $tmp_file;
  }
  // Do not wait for user input in --yes or --pipe modes
  if (drush_get_context('DRUSH_PIPE')) {
    drush_print_pipe(file_get_contents($file));
  }
  elseif (drush_get_context('DRUSH_AFFIRMATIVE')) {
    drush_print(file_get_contents($file));
  }
  elseif (drush_shell_exec_interactive("less %s", $file)) {
    return;
  }
  elseif (drush_shell_exec_interactive("more %s", $file)) {
    return;
  }
  else {
    drush_print(file_get_contents($file));
  }
}

/**
 * Converts a PHP variable into its Javascript equivalent.
 *
 * We provide a copy of D7's drupal_json_encode since this function is
 * unavailable on earlier versions of Drupal.
 *
 * @see drupal_json_decode()
 * @ingroup php_wrappers
 */
function drush_json_encode($var) {
  // json_encode() does not escape <, > and &, so we do it with str_replace().
  return str_replace(array('<', '>', '&'), array('\u003c', '\u003e', '\u0026'), json_encode($var));
}

/**
 * Converts an HTML-safe JSON string into its PHP equivalent.
 *
 * We provide a copy of D7's drupal_json_decode since this function is
 * unavailable on earlier versions of Drupal.
 *
 * @see drupal_json_encode()
 * @ingroup php_wrappers
 */
function drush_json_decode($var) {
  return json_decode($var, TRUE);
}

/**
 * @} End of "defgroup outputfunctions".
 */

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
 * @defgroup commandwrappers Functions to execute commands.
 * @{
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
 * Calls 'system()' function, passing through all arguments unchanged.
 *
 * This should be used when calling possibly mutative or destructive functions
 * (e.g. unlink() and other file system functions) so that can be suppressed
 * if the simulation mode is enabled.
 *
 * @param $exec
 *   The shell command to execute.  Parameters should already be escaped.
 * @return
 *   The result code from system():  0 == success.
 *
 * @see drush_shell_exec()
 */
function drush_op_system($exec) {
  if (drush_get_context('DRUSH_VERBOSE') || drush_get_context('DRUSH_SIMULATE')) {
     drush_print("Calling system($exec);");
  }

  if (drush_get_context('DRUSH_SIMULATE')) {
    return 0;
  }

  // Throw away output.  Use drush_shell_exec() to capture output.
  system($exec, $result_code);

  return $result_code;
}

/**
 * Executes a shell command at a new working directory.
 * The old cwd is restored on exit.
 *
 * @param $effective_wd
 *   The new working directory to execute the shell command at.
 * @param $cmd
 *   The command to execute. May include placeholders used for sprintf.
 * @param ...
 *   Values for the placeholders specified in $cmd. Each of these will be passed through escapeshellarg() to ensure they are safe to use on the command line.
 * @return
 *   TRUE on success, FALSE on failure
 */
function drush_shell_cd_and_exec($effective_wd, $cmd) {
  $args = func_get_args();

  $effective_wd = array_shift($args);
  $cwd = getcwd();
  drush_op('chdir', $effective_wd);
  $result = call_user_func_array('drush_shell_exec', $args);
  drush_op('chdir', $cwd);
  return $result;
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
 *   TRUE on success, FALSE on failure
 */
function drush_shell_exec($cmd) {
  return _drush_shell_exec(func_get_args());
}

/**
 * Executes a command in interactive mode.
 *
 * @see drush_shell_exec.
 */
function drush_shell_exec_interactive($cmd) {
  return _drush_shell_exec(func_get_args(), TRUE);
}

/**
 * Internal function: executes a shell command on the
 * local machine.  This function should not be used
 * in instances where ssh is utilized to execute a
 * command remotely; otherwise, remote operations would
 * fail if executed from a Windows machine to a remote
 * Linux server.
 *
 * @param $args
 *   The command and its arguments.
 * @param $interactive
 *   Whether to run in
 *
 * @return
 *   TRUE on success, FALSE on failure
 *
 * @see drush_shell_exec.
 */
function _drush_shell_exec($args, $interactive = FALSE) {
  //do not change the command itself, just the parameters.
  for ($x = 1; $x < sizeof($args); $x++) {
    $args[$x] = drush_escapeshellarg($args[$x]);
  }
  $command = call_user_func_array('sprintf', $args);

  if (drush_get_context('DRUSH_VERBOSE') || drush_get_context('DRUSH_SIMULATE')) {
    drush_print('Executing: ' . $command);
  }

  if (!drush_get_context('DRUSH_SIMULATE')) {
    if ($interactive) {
      $result = proc_open($command, array(0 => STDIN, 1 => STDOUT, 2 => STDERR), $pipes);
      proc_close($result);
      // proc_open returns FALSE on failure, or a resource on success.
      return ($result === FALSE) ? FALSE : TRUE;
    }
    else {
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
  }
  else {
    return TRUE;
  }
}

/**
 * Determine the appropriate os value for the
 * specified site record
 *
 * @returns
 *   NULL for 'same as local machine', 'Windows' or 'Linux'.
 */
function drush_os($site_record = NULL) {
  // Default to $os = NULL, meaning 'same as local machine'
  $os = NULL;
  // If the site record has an 'os' element, use it
  if (isset($site_record) && array_key_exists('os', $site_record)) {
    $os = $site_record['os'];
  }
  // Otherwise, we will assume that all remote machines are Linux
  // (or whatever value 'remote-os' is set to in drushrc.php).
  elseif (isset($site_record) && array_key_exists('remote-host', $site_record) && !empty($site_record['remote-host'])) {
    $os = drush_get_option('remote-os', 'Linux');
  }

  return $os;
}

/**
 * Platform-independent version of escapeshellarg().
 * This only works for local commands.
 * TODO: Make a unified drush_escapeshellarg
 * that works on Linux and Windows.
 */
function drush_escapeshellarg($arg) {
  if (drush_is_windows()) {
    return _drush_escapeshellarg_windows($arg);
  }
  else {
    return escapeshellarg($arg);
  }
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
 * Windows version of escapeshellarg().
 *
 * @deprecated escapeshellarg needs to be cross-platform,
 * because drush does not always know in advance whether an
 * escaped arg will be used locally or on a remote system.
 * See http://drupal.org/node/766080
 */
function _drush_escapeshellarg_windows($arg) {
  // Double the backslashes before any double quotes. Escape the double quotes.
  // (\" => \\\") && (" => \") =
  // (\" => \\") +
  $arg = preg_replace('/\\\"/', '\\\\\\"', $arg);
  // + (" => \")
  $arg = preg_replace('/"/', '\\"', $arg);

  // The same with single quotes.
  // (\' => \\\') && (' => \') =
  // (\' => \\') +
  $arg = preg_replace('/\\\'/', '\\\\\\\'', $arg);
  // + (' => \')
  $arg = preg_replace('/\'/', '\\\'', $arg);

  // Replace "\t", "\n", "\r", "\0", "\x0B" with a whitespace.
  $arg = str_replace(array("\t", "\n", "\r", "\0", "\x0B"), ' ', $arg);

  // Add surrounding quotes.
  $arg = '"' . $arg . '"';

  return $arg;
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
 * Extract a tarball.
 *
 * @param string $path
 *   The name of the .tar.gz or .tgz file to be extracted.
 * @param string $destination
 *   The destination directory the tarball should be extracted into.
 *   Optional, if ommitted the tarball directory will be used as destination.
 * @param boolean $listing
 *   If TRUE, a listing of the tar contents will be returned on success.
 *
 * @return string
 *   TRUE on success, FALSE on fail. If $listing is TRUE, a file listing of the
 *   tarball is returned if the extraction reported success, instead of TRUE.
 */
function drush_tarball_extract($path, $destination = FALSE, $listing = FALSE) {
  if (!file_exists($path)) {
    return drush_set_error('TARBALL_EXTRACT_NOT_FOUND', dt('Tarball !path could not be found.', array('!path' => $path)));
  }
  $olddir = getcwd();
  if (!$destination) {
    $destination = dirname($path);
  }
  if (!is_writeable($destination)) {
    return drush_set_error('TARBALL_EXTRACT_DESTINATION', dt('Extracting !path failed, as the destination directory !dest was not found or could not be written to.', array('!path' => $path, '!dest' => $dest)));
  }
  // If we are not on Windows, then try to do "tar" in a single operation.
  if ((!drush_is_windows()) && (drush_shell_cd_and_exec(dirname($path), "tar -C %s -xzf %s", $destination, basename($path)))) {
    if ($listing) {
      // We use a separate tar -tf instead of -xvf above because
      // the output is not the same in Mac.
      drush_shell_cd_and_exec(dirname($path), "tar -tf %s", basename($path));
      return drush_shell_exec_output();
    }
    return TRUE;
  }
  // If we could not get the single-op tar to work, do it in three steps.
  // Copy the source tarball to the destination directory.  Rename to a temp name in case the destination directory == dirname($path)
  $paths_basename = basename(basename($path, '.tar.gz'), '.tgz');
  $tarball = drush_tempnam($paths_basename, $destination) . ".tar.gz";
  drush_register_file_for_deletion($tarball);
  drush_copy_dir($path, $tarball);
  $unzipped = $destination . '/' . basename($tarball, ".tar.gz") . ".tar";
  // We used to use gzip --decompress in --stdout > out, but the output redirection sometimes failed on Windows for some binary output
  drush_shell_cd_and_exec(dirname($tarball), "gzip --decompress %s", $tarball);
  if (file_exists($unzipped)) {
    drush_register_file_for_deletion($unzipped);
    if (drush_shell_cd_and_exec(dirname($unzipped), "tar -xf %s", basename($unzipped))) {
      if ($listing) {
        // We use a separate tar -tf instead of -xf above because
        // the output is not the same in Mac.
        drush_shell_cd_and_exec(dirname($unzipped), "tar -tf %s", basename($unzipped));
        return drush_shell_exec_output();
      }
      return TRUE;
    }
    return drush_set_error('TARBALL_EXTRACT_TAR_FAIL', dt('Extracting !path using the tar command failed.', array('!path' => $path)));
  }
  else {
    return drush_set_error('TARBALL_EXTRACT_GZIP_FAIL', dt('Uncompressing !path using  the gzip command failed.', array('!path' => $path)));
  }
}

/**
 * @} End of "defgroup commandwrappers".
 */

/**
 * @defgroup filesystemfunctions Filesystem convenience functions.
 * @{
 */

/**
 * Deletes the provided file or folder and everything inside it.
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
    if (!drush_delete_dir($dir.'/'.$item)) {
      return FALSE;
    }
  }
  return rmdir($dir);
}

/**
 * Copy $src to $dest.
 *
 * @param $src
 *   The directory to copy.
 * @param $dest
 *   The destination to copy the source to, including the new name of
 *   the directory.  To copy directory "a" from "/b" to "/c", then
 *   $src = "/b/a" and $dest = "/c/a".  To copy "a" to "/c" and rename
 *   it to "d", then $dest = "/c/d".
 * @param $overwrite
 *   If TRUE, the destination will be deleted if it exists.
 * @return
 *   TRUE on success, FALSE on failure.
 */
function drush_copy_dir($src, $dest, $overwrite = FALSE) {
  // Preflight based on $overwrite if $dest exists.
  if (file_exists($dest)) {
    if ($overwrite) {
      drush_op('drush_delete_dir', $dest);
    }
    else {
      return drush_set_error('DRUSH_DESTINATION_EXISTS', dt('Destination directory !dest already exists.', array('!dest' => $dest)));
    }
  }
  // $src readable?
  if (!drush_op('is_readable', $src)) {
    return drush_set_error('DRUSH_SOURCE_NOT_EXISTS', dt('Source directory !src is not readable or does not exist.', array('!src' => $src)));
  }
  // $dest writable?
  if (!drush_op('is_writable', dirname($dest))) {
    return drush_set_error('DRUSH_DESTINATION_NOT_WRITABLE', dt('Destination directory !dest is not writable.', array('!dest' => dirname($dest))));
  }
  // Try to do a recursive copy.
  if (@drush_op('_drush_recursive_copy', $src, $dest)) {
    return TRUE;
  }

  return drush_set_error('DRUSH_COPY_DIR_FAILURE', dt('Unable to copy !src to !dest.', array('src' => $src, 'dest' => $dest)));
}

/**
 * Internal function called by drush_copy_dir; do not use directly.
 */
function _drush_recursive_copy($src, $dest) {
  // all subdirectories and contents:
  if(is_dir($src)) {
    drush_mkdir($dest);
    $dir_handle = opendir($src);
    while($file = readdir($dir_handle)) {
      if ($file != "." && $file != "..") {
        if (_drush_recursive_copy("$src/$file", "$dest/$file") !== TRUE) {
          return FALSE;
        }
      }
    }
    closedir($dir_handle);
  }
  elseif (drush_op('copy', $src, $dest) !== TRUE) {
    return FALSE;
  }

  // Preserve permissions
  if (!drush_is_windows()) {
    chmod($dest, intval(fileperms($src), 8));
  }

  return TRUE;
}

/**
 * Move $src to $dest.
 *
 * If the php 'rename' function doesn't work, then we'll do copy & delete.
 *
 * @param $src
 *   The directory to move.
 * @param $dest
 *   The destination to move the source to, including the new name of
 *   the directory.  To move directory "a" from "/b" to "/c", then
 *   $src = "/b/a" and $dest = "/c/a".  To move "a" to "/c" and rename
 *   it to "d", then $dest = "/c/d" (just like php rename function).
 * @param $overwrite
 *   If TRUE, the destination will be deleted if it exists.
 * @return
 *   TRUE on success, FALSE on failure.
 */
function drush_move_dir($src, $dest, $overwrite = FALSE) {
  // Preflight based on $overwrite if $dest exists.
  if (file_exists($dest)) {
    if ($overwrite) {
      drush_op('drush_delete_dir', $dest);
    }
    else {
      return drush_set_error('DRUSH_DESTINATION_EXISTS', dt('Destination directory !dest already exists.', array('!dest' => $dest)));
    }
  }
  // $src readable?
  if (!drush_op('is_readable', $src)) {
    return drush_set_error('DRUSH_SOURCE_NOT_EXISTS', dt('Source directory !src is not readable or does not exist.', array('!src' => $src)));
  }
  // $dest writable?
  if (!drush_op('is_writable', dirname($dest))) {
    return drush_set_error('DRUSH_DESTINATION_NOT_WRITABLE', dt('Destination directory !dest is not writable.', array('!dest' => dirname($dest))));
  }
  // Try rename. It will fail if $src and $dest are not in the same partition.
  if (@drush_op('rename', $src, $dest)) {
    return TRUE;
  }
  // Eventually it will create an empty file in $dest. See
  // http://www.php.net/manual/es/function.rename.php#90025
  elseif (is_file($dest)) {
    drush_op('unlink', $dest);
  }

  // If 'rename' fails, then we will use copy followed
  // by a delete of the source.
  if (drush_copy_dir($src, $dest)) {
    drush_op('drush_delete_dir', $src);
    return TRUE;
  }

  return drush_set_error('DRUSH_MOVE_DIR_FAILURE', dt('Unable to move !src to !dest.', array('!src' => $src, '!dest' => $dest)));
}

/**
 * Cross-platform compatible helper function to recursively create a directory tree.
 * @see http://theserverpages.com/php/manual/en/function.mkdir.php#50383
 */
function drush_mkdir($path) {
  return is_dir($path) || (drush_mkdir(dirname($path)) && drush_shell_exec('mkdir %s', $path));
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
 * Returns the path to a temporary directory.
 *
 * This is a custom version of file_directory_path().
 * We can't directly rely on sys_get_temp_dir() as this
 * path is not valid in some setups for Mac.
 */
function drush_find_tmp() {
  static $temporary_directory = NULL;

  if (is_null($temporary_directory)) {
    $directories = array();

    // Operating system specific dirs.
    if (substr(PHP_OS, 0, 3) == 'WIN') {
      $directories[] = 'c:\\windows\\temp';
      $directories[] = 'c:\\winnt\\temp';
    }
    else {
      $directories[] = '/tmp';
    }
    // This function exists in PHP 5 >= 5.2.1, but drush
    // requires PHP 5 >= 5.2.0, so we check for it.
    if (function_exists('sys_get_temp_dir')) {
      $directories[] = sys_get_temp_dir();
    }

    foreach ($directories as $directory) {
      if (is_dir($directory) && is_writable($directory)) {
        $temporary_directory = $directory;
        break;
      }
    }

    if (empty($temporary_directory)) {
      // If no directory has been found, create one in cwd.
      $temporary_directory = drush_cwd() . '/tmp';
      drush_mkdir($temporary_directory);
      if (!is_dir($directory)) {
        return drush_set_error('DRUSH_UNABLE_TO_CREATE_TMP_DIR', dt("Unable to create a temporary directory."));
      }
      drush_register_file_for_deletion($temporary_directory);
    }
  }

  return $temporary_directory;
}

/**
 * Creates a temporary file, and registers it so that
 * it will be deleted when drush exits.  Whenever possible,
 * drush_save_data_to_temp_file() should be used instead
 * of this function.
 */
function drush_tempnam($pattern, $tmp_dir = NULL) {
  if ($tmp_dir == NULL) {
    $tmp_dir = drush_find_tmp();
  }
  $tmp_file = tempnam($tmp_dir, $pattern);
  drush_register_file_for_deletion($tmp_file);

  return $tmp_file;
}

/**
 * Creates a temporary directory and return its path.
 */
function drush_tempdir() {
  $tmp_dir = rtrim(drush_find_tmp(), DIRECTORY_SEPARATOR);
  $tmp_dir .= '/' . 'drush_tmp_' . time();

  drush_mkdir($tmp_dir);
  drush_register_file_for_deletion($tmp_dir);

  return $tmp_dir;
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
      if (is_dir($file)) {
        drush_delete_dir($file);
      }
      else {
        unlink($file);
      }
    }
  }
}

/**
 * Decide where our backup directory should go
 *
 * @param string $subdir
 *   The name of the desired subdirectory(s) under drush-backups.
 *   Usually a database name.
 */
function drush_preflight_backup_dir($subdir = NULL) {
  $backup_dir = drush_get_context('DRUSH_BACKUP_DIR', drush_get_option('backup-location'));

  if (empty($backup_dir)) {

    // Try to use db name as subdir if none was provided.
    if (empty($subdir)) {
      $subdir = 'unknown';
      if ($creds = drush_get_context('DRUSH_DB_CREDENTIALS')) {
        $subdir = $creds['name'];
      }
    }

    // Save the date to be used in the backup directory's path name.
    $date = gmdate('YmdHis', $_SERVER['REQUEST_TIME']);

    $backup_dir = drush_get_option('backup-dir', drush_server_home() . '/' . 'drush-backups');
    $backup_dir = rtrim($backup_dir, DIRECTORY_SEPARATOR) . '/' . $subdir . '/' . $date;
    drush_set_context('DRUSH_BACKUP_DIR', $backup_dir);
  }
  return $backup_dir;
}

/**
 * Prepare a backup directory
 */
function drush_prepare_backup_dir($subdir = NULL) {
  $backup_dir = drush_preflight_backup_dir($subdir);
  $backup_parent = dirname($backup_dir);

  $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');

  if ((!empty($drupal_root)) && (strpos($backup_parent, $drupal_root) === 0)) {
    return drush_set_error('DRUSH_PM_BACKUP_FAILED', dt('It\'s not allowed to store backups inside the Drupal root directory.'));
  }
  if (!file_exists($backup_parent)) {
    if (!drush_mkdir($backup_parent)) {
      return drush_set_error('DRUSH_PM_BACKUP_FAILED', dt('Unable to create backup directory !dir.', array('!dir' => $backup_parent)));
    }
  }
  if (!is_writable($backup_parent)) {
    return drush_set_error('DRUSH_PM_BACKUP_FAILED', dt('Backup directory !dir is not writable.', array('!dir' => $backup_parent)));
  }

  drush_mkdir($backup_dir);
  return $backup_dir;
}

/**
 * @} End of "defgroup filesystemfunctions".
 */

/**
 * @defgroup dbfunctions Database convenience functions.
 * @{
 */

/**
 * Replace named placeholders in a WHERE snippet.
 *
 * Helper function to allow the usage of Drupal 7 WHERE snippets
 * with named placeholders in code for Drupal 5 and 6.
 *
 * @param $where
 *   String with a WHERE snippet using named placeholders.
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
      // To avoid this we will use a zero padded indexed array of the values of $data.
      $pad_length = strlen((string)count(array_values($data)));
      foreach (array_values($data) as $i => $value) {
        if (!is_numeric($value)) {
          $value = "'".$value."'";
        }
        $new_keys[$key . '_' . str_pad($i, $pad_length, '0', STR_PAD_LEFT)] = $value;
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
 *   Affected rows (except on D7+mysql without a WHERE clause - returns TRUE) or FALSE.
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
      return FALSE;
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
 * @} End of "defgroup dbfunctions".
 */

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
    $site_list = drush_sitealias_resolve_sitespecs($site_list);
    $site_list = drush_sitealias_simplify_names($site_list);
    $args = drush_get_arguments();

    if (!drush_get_context('DRUSH_SIMULATE')) {
      drush_print(dt("You are about to execute '!command' on all of the following targets:", array('!command' => implode(" ", $args))));
      foreach ($site_list as $one_destination => $one_record) {
        drush_print(dt('  !target', array('!target' => $one_destination)));
      }

      if (drush_confirm('Continue? ') === FALSE) {
        drush_user_abort();
        return TRUE;
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

  // decode_entities() only loaded after FULL bootstrap.
  if (function_exists('decode_entities')) {
    $message = decode_entities($message);
  }
  $message = strip_tags($message);

  // Log or print or ignore. Just printing saves memory but thats rarely needed.
  switch (drush_get_option('watchdog', 'log')) {
    case 'log':
      drush_log('WD '. $log_entry['type'] . ': ' . $message, $severity);
      break;
    case 'print':
      // Disable in backend mode since it logs output and the goal is to conserve memory.
      // @see _drush_bootstrap_drush().
      if (ob_get_length() === FALSE) {
        drush_print('WD '. $severity . ' ' . $log_entry['type'] . ': ' . $message);
      }
      break;
    default:
      // Do nothing.
  }
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
 * Test to see if a file exists and is not empty
 */
function drush_file_not_empty($file_to_test) {
  if (file_exists($file_to_test)) {
    $stat = stat($file_to_test);
    if ($stat['size'] > 0) {
      return TRUE;
    }
  }
  return FALSE;
}

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
