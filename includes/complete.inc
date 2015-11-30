<?php

use Drush\Log\LogLevel;

/**
 * @file
 *
 * Provide completion output for shells.
 *
 * This is not called directly, but by shell completion scripts specific to
 * each shell (bash, csh etc). These run whenever the user triggers completion,
 * typically when pressing <tab>. The shell completion scripts should call
 * "drush complete <text>", where <text> is the full command line, which we take
 * as input and use to produce a list of possible completions for the
 * current/next word, separated by newlines. Typically, when multiple
 * completions are returned the shell will display them to the user in a concise
 * format - but when a single completion is returned it will autocomplete.
 *
 * We provide completion for site aliases, commands, shell aliases, options,
 * engines and arguments. Displaying all of these when the last word has no
 * characters yet is not useful, as there are too many items. Instead we filter
 * the possible completions based on position, in a similar way to git.
 * For example:
 * - We only display site aliases and commands if one is not already present.
 * - We only display options if the user has already entered a hyphen.
 * - We only display global options before a command is entered, and we only
 *   display command specific options after the command (Drush itself does not
 *   care about option placement, but this approach keeps things more concise).
 *
 * Below is typical output of complete in different situations. Tokens in square
 * brackets are optional, and [word] will filter available options that start
 * with the same characters, or display all listed options if empty.
 * drush --[word] : Output global options
 * drush [word] : Output site aliases, sites, commands and shell aliases
 * drush [@alias] [word] : Output commands
 * drush [@alias] command [word] : Output command specific arguments
 * drush [@alias] command --[word] : Output command specific options
 *
 * Because the purpose of autocompletion is to make the command line more
 * efficient for users we need to respond quickly with the list of completions.
 * To do this, we call drush_complete() early in the Drush bootstrap, and
 * implement a simple caching system.
 *
 * To generate the list of completions, we set up the Drush environment as if
 * the command was called on it's own, parse the command using the standard
 * Drush functions, bootstrap the site (if any) and collect available
 * completions from various sources. Because this can be somewhat slow, we cache
 * the results. The cache strategy aims to balance accuracy and responsiveness:
 * - We cache per site, if a site is available.
 * - We generate (and cache) everything except arguments at the same time, so
 *   subsequent completions on the site don't need any bootstrap.
 * - We generate and cache arguments on-demand, since these can often be
 *   expensive to generate. Arguments are also cached per-site.
 *
 * For argument completions, commandfiles can implement
 * COMMANDFILE_COMMAND_complete() returning an array containing a key 'values'
 * containing an array of all possible argument completions for that command.
 * For example, return array('values' => array('aardvark', 'aardwolf')) offers
 * the words 'aardvark' and 'aardwolf', or will complete to 'aardwolf' if the
 * letters 'aardw' are already present. Since command arguments are cached,
 * commandfiles can bootstrap a site or perform other somewhat time consuming
 * activities to retrieve the list of possible arguments. Commands can also
 * clear the cache (or just the "arguments" cache for their command) when the
 * completion results have likely changed - see drush_complete_cache_clear().
 *
 * Commandfiles can also return a special optional element in their array with
 * the key 'files' that contains an array of patterns/flags for the glob()
 * function. These are used to produce file and directory completions (the
 * results of these are not cached, since this is a fast operation).
 * See http://php.net/glob for details of valid patterns and flags.
 * For example the following will complete the command arguments on all
 * directories, as well as files ending in tar.gz:
 *   return array(
 *         'files' => array(
 *           'directories' => array(
 *             'pattern' => '*',
 *             'flags' => GLOB_ONLYDIR,
 *           ),
 *           'tar' => array(
 *             'pattern' => '*.tar.gz',
 *           ),
 *         ),
 *       );
 *
 * To check completion results without needing to actually trigger shell
 * completion, you can call this manually using a command like:
 *
 * drush --early=includes/complete.inc [--complete-debug] drush [@alias] [command]...
 *
 * If you want to simulate the results of pressing tab after a space (i.e.
 * and empty last word, include '' on the end of your command:
 *
 * drush --early=includes/complete.inc [--complete-debug] drush ''
 */

/**
 * Produce autocomplete output.
 *
 * Determine position (is there a site-alias or command set, and are we trying
 * to complete an option). Then produce a list of completions for the last word
 * and output them separated by newlines.
 */
function drush_early_complete() {
  // We use a distinct --complete-debug option to avoid unwanted debug messages
  // being printed when users use this option for other purposes in the command
  // they are trying to complete.
  drush_set_option(LogLevel::DEBUG, FALSE);
  if (drush_get_option('complete-debug', FALSE)) {
    drush_set_context('DRUSH_DEBUG', TRUE);
  }
  // Set up as if we were running the command, and attempt to parse.
  $argv = drush_complete_process_argv();
  if ($alias = drush_get_context('DRUSH_TARGET_SITE_ALIAS')) {
    $set_sitealias_name = $alias;
    $set_sitealias = drush_sitealias_get_record($alias);
  }

  // Arguments have now had site-aliases and options removed, so we take the
  // first item as our command. We need to know if the command is valid, so that
  // we know if we are supposed to complete an in-progress command name, or
  // arguments for a command. We do this by checking against our per-site cache
  // of command names (which will only bootstrap if the cache needs to be
  // regenerated), rather than drush_parse_command() which always requires a
  // site bootstrap.
  $arguments = drush_get_arguments();
  $set_command_name = NULL;
  if (isset($arguments[0]) && in_array($arguments[0] . ' ', drush_complete_get('command-names'))) {
    $set_command_name = $arguments[0];
  }
  // We unset the command if it is "help" but that is not explicitly found in
  // args, since Drush sets the command to "help" if no command is specified,
  // which prevents completion of global options.
  if ($set_command_name == 'help' && !array_search('help', $argv)) {
    $set_command_name = NULL;
  }

  // Determine the word we are trying to complete, and if it is an option.
  $last_word = end($argv);
  $word_is_option = FALSE;
  if (!empty($last_word) && $last_word[0] == '-') {
    $word_is_option = TRUE;
    $last_word = ltrim($last_word, '-');
  }

  $completions = array();
  if (!$set_command_name) {
    // We have no command yet.
    if ($word_is_option) {
      // Include global option completions.
      $completions += drush_hyphenate_options(drush_complete_match($last_word, drush_complete_get('options')));
    }
    else {
      if (empty($set_sitealias_name)) {
        // Include site alias completions.
        $completions += drush_complete_match($last_word, drush_complete_get('site-aliases'));
      }
      // Include command completions.
      $completions += drush_complete_match($last_word, drush_complete_get('command-names'));
    }
  }
  else {
    if ($last_word == $set_command_name) {
      // The user just typed a valid command name, but we still do command
      // completion, as there may be other commands that start with the detected
      // command (e.g. "make" is a valid command, but so is "make-test").
      // If there is only the single matching command, this will include in the
      // completion list so they get a space inserted, confirming it is valid.
      $completions += drush_complete_match($last_word, drush_complete_get('command-names'));
    }
    else if ($word_is_option) {
      // Include command option completions.
      $completions += drush_hyphenate_options(drush_complete_match($last_word, drush_complete_get('options', $set_command_name)));
    }
    else {
      // Include command argument completions.
      $argument_completion = drush_complete_get('arguments', $set_command_name);
      if (isset($argument_completion['values'])) {
        $completions += drush_complete_match($last_word, $argument_completion['values']);
      }
      if (isset($argument_completion['files'])) {
        $completions += drush_complete_match_file($last_word, $argument_completion['files']);
      }
    }
  }

  if (!empty($completions)) {
    sort($completions);
    return implode("\n", $completions);
  }
  return TRUE;
}

/**
 * This function resets the raw arguments so that Drush can parse the command as
 * if it was run directly. The shell complete command passes the
 * full command line as an argument, and the --early and --complete-debug
 * options have to come before that, and the "drush" bash script will add a
 * --php option on the end, so we end up with something like this:
 *
 * /path/to/drush.php --early=includes/complete.inc [--complete-debug] drush [@alias] [command]... --php=/usr/bin/php
 *
 * Note that "drush" occurs twice, and also that the second occurrence could be
 * an alias, so we can't easily use it as to detect the start of the actual
 * command. Hence our approach is to remove the initial "drush" and then any
 * options directly following that - what remains is then the command we need
 * to complete - i.e.:
 *
 * drush [@alias] [command]...
 *
 * Note that if completion is initiated following a space an empty argument is
 * added to argv. So in that case argv looks something like this:
 * array (
 *  '0' => '/path/to/drush.php',
 *  '1' => '--early=includes/complete.inc',
 *  '2' => 'drush',
 *  '3' => 'topic',
 *  '4' => '',
 *  '5' => '--php=/usr/bin/php',
 * );
 *
 * @return $args
 *   Array of arguments (argv), excluding the initial command and options
 *   associated with the complete call.
 *   array (
 *    '0' => 'drush',
 *    '1' => 'topic',
 *    '2' => '',
 *   );
 */
function drush_complete_process_argv() {
  $argv = drush_get_context('argv');
  // Remove the first argument, which will be the "drush" command.
  array_shift($argv);
  while (substr($arg = array_shift($argv), 0, 2) == '--') {
    // We remove all options, until we get to a non option, which
    // marks the start of the actual command we are trying to complete.
  }
  // Replace the initial argument.
  array_unshift($argv, $arg);
  // Remove the --php option at the end if exists (added by the "drush" shell
  // script that is called when completion is requested).
  if (substr(end($argv), 0, 6) == '--php=') {
    array_pop($argv);
  }
  drush_set_context('argv', $argv);
  drush_set_command(NULL);
  // Reparse arguments, site alias, and command.
  drush_parse_args();
  // Ensure the base environment is configured, so tests look in the correct
  // places.
  _drush_preflight_base_environment();
  // Check for and record any site alias.
  drush_sitealias_check_arg();
  drush_sitealias_check_site_env();
  // We might have just changed our root--run drush_select_bootstrap_class() again.
  $bootstrap = drush_select_bootstrap_class();

  // Return the new argv for easy reference.
  return $argv;
}

/**
 * Retrieves the appropriate list of candidate completions, then filters this
 * list using the last word that we are trying to complete.
 *
 * @param string $last_word
 *   The last word in the argument list (i.e. the subject of completion).
 * @param array $values
 *   Array of possible completion values to filter.
 *
 * @return array
 *   Array of candidate completions that start with the same characters as the
 *   last word. If the last word is empty, return all candidates.
 */
function drush_complete_match($last_word, $values) {
  // Using preg_grep appears to be faster that strpos with array_filter/loop.
  return preg_grep('/^' . preg_quote($last_word, '/') . '/', $values);
}

/**
 * Retrieves the appropriate list of candidate file/directory completions,
 * filtered by the last word that we are trying to complete.
 *
 * @param string $last_word
 *   The last word in the argument list (i.e. the subject of completion).
 * @param array $files
 *   Array of file specs, each with a pattern and flags subarray.
 *
 * @return array
 *   Array of candidate file/directory completions that start with the same
 *   characters as the last word. If the last word is empty, return all
 *   candidates.
 */
function drush_complete_match_file($last_word, $files) {
  $return = array();
  if ($last_word[0] == '~') {
    // Complete does not do tilde expansion, so we do it here.
    // We shell out (unquoted) to expand the tilde.
    drush_shell_exec('echo ' . $last_word);
    return drush_shell_exec_output();
  }

  $dir = '';
  if (substr($last_word, -1) == '/' && is_dir($last_word)) {
    // If we exactly match a trailing directory, then we use that as the base
    // for the listing. We only do this if a trailing slash is present, since at
    // this stage it is still possible there are other directories that start
    // with this string.
    $dir = $last_word;
  }
  else {
    // Otherwise we discard the last part of the path (this is matched against
    // the list later), and use that as our base.
    $dir = dirname($last_word);
    if (empty($dir) || $dir == '.' && $last_word != '.' && substr($last_word, 0, 2) != './') {
      // We are looking at the current working directory, so unless the user is
      // actually specifying a leading dot we leave the path empty.
      $dir = '';
    }
    else {
      // In all other cases we need to add a trailing slash.
      $dir .= '/';
    }
  }

  foreach ($files as $spec) {
    // We always include GLOB_MARK, as an easy way to detect directories.
    $flags = GLOB_MARK;
    if (isset($spec['flags'])) {
      $flags = $spec['flags'] | GLOB_MARK;
    }
    $listing = glob($dir . $spec['pattern'], $flags);
    $return = array_merge($return, drush_complete_match($last_word, $listing));
  }
  // If we are returning a single item (which will become part of the final
  // command), we need to use the full path, and we need to escape it
  // appropriately.
  if (count($return) == 1) {
    // Escape common shell metacharacters (we don't use escapeshellarg as it
    // single quotes everything, even when unnecessary).
    $item = array_pop($return);
    $item = preg_replace('/[ |&;()<>]/', "\\\\$0", $item);
    if (substr($item, -1) !== '/') {
      // Insert a space after files, since the argument is complete.
      $item = $item . ' ';
    }
    $return = array($item);
  }
  else {
    $firstchar = TRUE;
    if ($last_word[0] == '/') {
      // If we are working with absolute paths, we need to check if the first
      // character of all the completions matches. If it does, then we pass a
      // full path for each match, so the shell completes as far as it can,
      // matching the behaviour with relative paths.
      $pos = strlen($last_word);
      foreach ($return as $id => $item) {
        if ($item[$pos] !== $return[0][$pos]) {
          $firstchar = FALSE;
          continue;
        }
      }
    }
    foreach ($return as $id => $item) {
      // For directories we leave the path alone.
      $slash_pos = strpos($last_word, '/');
      if ($slash_pos === 0 && $firstchar) {
        // With absolute paths where completions share initial characters, we
        // pass in a resolved path.
        $return[$id] = realpath($item);
      }
      else if ($slash_pos !== FALSE && $dir != './') {
        // For files, we pass only the file name, ignoring the false match when
        // the user is using a single dot relative path.
        $return[$id] = basename($item);
      }
    }
  }
  return $return;
}

/**
 * Simple helper function to ensure options are properly hyphenated before we
 * return them to the user (we match against the non-hyphenated versions
 * internally).
 *
 * @param array $options
 *   Array of unhyphenated option names.
 *
 * @return array
 *   Array of hyphenated option names.
 */
function drush_hyphenate_options($options) {
  foreach ($options as $key => $option) {
    $options[$key] = '--' . ltrim($option, '--');
  }
  return $options;
}

/**
 * Retrieves from cache, or generates a listing of completion candidates of a
 * specific type (and optionally, command).
 *
 * @param string $type
 *   String indicating type of completions to return.
 *   See drush_complete_rebuild() for possible keys.
 * @param string $command
 *   An optional command name if command specific completion is needed.
 *
 * @return array
 *   List of candidate completions.
 */
function drush_complete_get($type, $command = NULL) {
  static $complete;
  if (empty($command)) {
    // Quick return if we already have a complete static cache.
    if (!empty($complete[$type])) {
      return $complete[$type];
    }
    // Retrieve global items from a non-command specific cache, or rebuild cache
    // if needed.
    $cache = drush_cache_get(drush_complete_cache_cid($type), 'complete');
    if (isset($cache->data)) {
      return $cache->data;
    }
    $complete = drush_complete_rebuild();
    return $complete[$type];
  }
  // Retrieve items from a command specific cache.
  $cache = drush_cache_get(drush_complete_cache_cid($type, $command), 'complete');
  if (isset($cache->data)) {
    return $cache->data;
  }
  // Build argument cache - built only on demand.
  if ($type == 'arguments') {
    return drush_complete_rebuild_arguments($command);
  }
  // Rebuild cache of general command specific items.
  if (empty($complete)) {
    $complete = drush_complete_rebuild();
  }
  if (!empty($complete['commands'][$command][$type])) {
    return $complete['commands'][$command][$type];
  }
  return array();
}

/**
 * Rebuild and cache completions for everything except command arguments.
 *
 * @return array
 *   Structured array of completion types, commands and candidate completions.
 */
function drush_complete_rebuild() {
  $complete = array();
  // Bootstrap to the site level (if possible) - commands may need to check
  // the bootstrap level, and perhaps bootstrap higher in extraordinary cases.
  drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION);
  $commands = drush_get_commands();
  foreach ($commands as $command_name => $command) {
    // Add command options and suboptions.
    $options = array_keys($command['options']);
    foreach ($command['sub-options'] as $option => $sub_options) {
      $options = array_merge($options, array_keys($sub_options));
    }
    $complete['commands'][$command_name]['options'] = $options;
  }
  // We treat shell aliases as commands for the purposes of completion.
  $complete['command-names'] = array_merge(array_keys($commands), array_keys(drush_get_context('shell-aliases', array())));
  $site_aliases = _drush_sitealias_all_list();
  // TODO: Figure out where this dummy @0 alias is introduced.
  unset($site_aliases['@0']);
  $complete['site-aliases'] = array_keys($site_aliases);
  $complete['options'] = array_keys(drush_get_global_options());

  // We add a space following all completes. Eventually there may be some
  // items (e.g. options that we know need values) where we don't add a space.
  array_walk_recursive($complete, 'drush_complete_trailing_space');
  drush_complete_cache_set($complete);
  return $complete;
}

/**
 * Helper callback function that adds a trailing space to completes in an array.
 */
function drush_complete_trailing_space(&$item, $key) {
  if (!is_array($item)) {
    $item = (string)$item . ' ';
  }
}

/**
 * Rebuild and cache completions for command arguments.
 *
 * @param string $command
 *   A specific command to retrieve and cache arguments for.
 *
 * @return array
 *   Structured array of candidate completion arguments, keyed by the command.
 */
function drush_complete_rebuild_arguments($command) {
  // Bootstrap to the site level (if possible) - commands may need to check
  // the bootstrap level, and perhaps bootstrap higher in extraordinary cases.
  drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_SITE);
  $commands = drush_get_commands();
  $hook = str_replace("-", "_", $commands[$command]['command-hook']);
  $result = drush_command_invoke_all($hook . '_complete');
  if (isset($result['values'])) {
    // We add a space following all completes. Eventually there may be some
    // items (e.g. comma separated arguments) where we don't add a space.
    array_walk($result['values'], 'drush_complete_trailing_space');
  }

  $complete = array(
    'commands' => array(
      $command => array(
        'arguments' => $result,
      )
    )
  );
  drush_complete_cache_set($complete);
  return $complete['commands'][$command]['arguments'];
}

/**
 * Stores caches for completions.
 *
 * @param $complete
 *   A structured array of completions, keyed by type, including a 'commands'
 *   type that contains all commands with command specific completions keyed by
 *   type. The array does not need to include all types - used by
 *   drush_complete_rebuild_arguments().
 */
function drush_complete_cache_set($complete) {
  foreach ($complete as $type => $values) {
    if ($type == 'commands') {
      foreach ($values as $command_name => $command) {
        foreach ($command as $command_type => $command_values) {
          drush_cache_set(drush_complete_cache_cid($command_type, $command_name), $command_values, 'complete', DRUSH_CACHE_TEMPORARY);
        }
      }
    }
    else {
      drush_cache_set(drush_complete_cache_cid($type), $values, 'complete', DRUSH_CACHE_TEMPORARY);
    }
  }
}

/**
 * Generate a cache id.
 *
 * @param $type
 *   The completion type.
 * @param $command
 *   The command name (optional), if completions are command specific.
 *
 * @return string
 *   Cache id.
 */
function drush_complete_cache_cid($type, $command = NULL) {
  // For per-site caches, we include the site root and uri/path in the cache id
  // hash. These are quick to determine, and prevents a bootstrap to site just
  // to get a validated root and URI. Because these are not validated, there is
  // the possibility of cache misses/ but they should be rare, since sites are
  // normally referred to the same way (e.g. a site alias, or using the current
  // directory), at least within a single command completion session.
  // We also static cache them, since we may get differing results after
  // bootstrap, which prevents the caches from being found on the next call.
  static $root, $site;
  if (empty($root)) {
    $root = drush_get_option(array('r', 'root'), drush_locate_root());
    $site = drush_get_option(array('l', 'uri'), drush_site_path());
  }
  return drush_get_cid('complete', array(), array($type, $command, $root, $site));
}
