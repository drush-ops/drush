<?php

/**
 * @defgroup dispatching Command dispatching functions.
 * @{
 *
 * These functions handle command dispatching, and can
 * be used to programatically invoke drush commands in
 * different ways.
 */

/**
 * Invokes a Drush API call, including all hooks.
 *
 * Executes the specified command with the specified arguments on the currently
 * bootstrapped site using the current option contexts. Note that it will not
 * bootstrap any further than the current command has already bootstrapped;
 * therefore, you should only invoke commands that have the same (or lower)
 * bootstrap requirements.
 *
 * Commands execute with the same options that the user provided on the
 * commandline. If you need to invoke another Drush command with options you
 * specify, use drush_invoke_process() instead.
 *
 * @param string $command
 *   The command to invoke.
 * @param array $arguments
 *   An array of argument to pass into the command.
 *
 * @return mixed|false
 *   The return value from drush_dispatch() or FALSE on error.
 *
 * @see drush_invoke_process()
 */
function drush_invoke($command, $arguments = array()) {
  // Convert a standalone argument to a single-element array.
  if (!is_array($arguments)) {
    $arguments = array($arguments);
  }
  $commands = drush_get_commands();
  if (array_key_exists($command, $commands)) {
    $command = $commands[$command];
    // Drush overloads the 'arguments' element, which contains the help string
    // for the allowed arguments for the command when fetched, and is fixed up
    // by _drush_prepare_command() to contain the actual commandline arguments
    // during dispatching.
    $command['arguments'] = array();
    return drush_dispatch($command, $arguments);
  }
  else {
    return drush_set_error('DRUSH_COMMAND_NOT_FOUND', dt("The drush command '!command' could not be found.", array('!command' => $command)));
  }
}

/**
 * Invoke a command in a new process, targeting the site specified by
 * the provided site alias record.
 *
 * Use this function instead of drush_backend_invoke_sitealias,
 * drush_backend_invoke_args, or drush_backend_invoke_command
 * (all obsolete in drush 5).
 *
 * @param array $site_alias_record
 *  The site record to execute the command on.  Use '@self' to run on the current site.
 * @param string $command_name
 *  The command to invoke.
 * @param array $commandline_args
 *  The arguments to pass to the command.
 * @param array $commandline_options
 *  The options (e.g. --select) to provide to the command.
 * @param $backend_options
 *   TRUE - integrate errors
 *   FALSE - do not integrate errors
 *   array - @see drush_backend_invoke_concurrent
 *     There are also several options that _only_ work when set in
 *     this parameter.  They include:
 *      'invoke-multiple'
 *        If $site_alias_record represents a single site, then 'invoke-multiple'
 *        will cause the _same_ command with the _same_ arguments and options
 *        to be invoked concurrently (e.g. for running concurrent batch processes).
 *      'concurrency'
 *        Limits the number of concurrent processes that will run at the same time.
 *        Defaults to '4'.
 *      'override-simulated'
 *        Forces the command to run, even in 'simulated' mode. Useful for
 *        commands that do not change any state on the machine, e.g. to fetch
 *        database information for sql-sync via sql-conf.
 *      'interactive'
 *        Overrides the backend invoke process to run commands interactively.
 *      'fork'
 *        Overrides the backend invoke process to run non blocking commands in
 *        the background. Forks a new process by adding a '&' at the end of the
 *        command. The calling process does not receive any output from the child
 *        process. The fork option is used to spawn a process that outlives its
 *        parent.
 *
 * @return
 *   If the command could not be completed successfully, FALSE.
 *   If the command was completed, this will return an associative
 *   array containing the results of the API call.
 *   @see drush_backend_get_result()
 *
 * Do not change the signature of this function!  drush_invoke_process
 * is one of the key Drush APIs.  See http://drupal.org/node/1152908
 */
function drush_invoke_process($site_alias_record, $command_name, $commandline_args = array(), $commandline_options = array(), $backend_options = TRUE) {
  if (is_array($site_alias_record) && array_key_exists('site-list', $site_alias_record)) {
    $site_alias_records = drush_sitealias_resolve_sitespecs($site_alias_record['site-list']);
    $site_alias_records = drush_sitealias_simplify_names($site_alias_records);
    foreach ($site_alias_records as $alias_name => $alias_record) {
      $invocations[] = array(
        'site' => $alias_record,
        'command' => $command_name,
        'args' => $commandline_args,
      );
    }
  }
  else {
    $invocations[] = array(
      'site' => $site_alias_record,
      'command' => $command_name,
      'args' => $commandline_args);
    $invoke_multiple = drush_get_option_override($backend_options, 'invoke-multiple', 0);
    if ($invoke_multiple) {
      $invocations = array_fill(0, $invoke_multiple, $invocations[0]);
    }
  }
  return drush_backend_invoke_concurrent($invocations, $commandline_options, $backend_options);
}

/**
 * Given a command record, dispatch it as if it were
 * the original command.  Executes in the currently
 * bootstrapped site using the current option contexts.
 * Note that drush_dispatch will not bootstrap any further than the
 * current command has already bootstrapped; therefore, you should only invoke
 * commands that have the same (or lower) bootstrap requirements.
 *
 * @param command
 *   A full $command such as returned by drush_get_commands(),
 *   or a string containing the name of the command record from
 *   drush_get_commands() to call.
 * @param arguments
 *   An array of argument values.
 *
 * @see drush_topic_docs_topic().
 */
function drush_dispatch($command, $arguments = array()) {
  drush_set_command($command);
  $return = FALSE;

  if ($command) {
    // Add arguments, if this has not already been done.
    // (If the command was fetched from drush_parse_command,
    // then you cannot provide arguments to drush_dispatch.)
    if (empty($command['arguments'])) {
      _drush_prepare_command($command, $arguments);
    }

    // Add command-specific options, if applicable.
    drush_command_default_options($command);

    // Test to see if any of the options in the 'cli' context
    // are not represented in the command structure.
    if ((_drush_verify_cli_options($command) === FALSE) || (_drush_verify_cli_arguments($command) === FALSE)) {
      return FALSE;
    }

    // Include and validate command engines.
    if (_drush_load_command_engines($command) === FALSE) {
      return FALSE;
    }

    // Call the callback function of the active command.
    $return = call_user_func_array($command['callback'], $command['arguments']);
  }

  // Add a final log entry, just so a timestamp appears.
  drush_log(dt('Command dispatch complete'), 'notice');

  return $return;
}

/**
 * Entry point for commands into the drush_invoke() API
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
    if (drush_get_option('show-invoke')) {
      drush_log(dt("Calling global init hook: !func", array('!name' => $name, '!func' => $func . '()')), 'bootstrap');
    }
    call_user_func_array($func, $args);
    _drush_log_drupal_messages();
  }

  if (!drush_get_error()) {
    _drush_invoke_hooks($command['command-hook'], $args, $command['commandfile']);
  }

  if (!drush_get_error()) {
    foreach (drush_command_implements('drush_exit') as $name) {
      $func = $name . '_drush_exit';
      if (drush_get_option('show-invoke')) {
        drush_log(dt("Calling global exit hook: !func", array('!name' => $name, '!func' => $func . '()')), 'bootstrap');
      }
      call_user_func_array($func, $args);
      _drush_log_drupal_messages();
    }
  }
}

/**
 * Invoke Drush API calls, including all hooks.
 *
 * This is an internal function; it is called from drush_dispatch via
 * drush_command, but only if the command does not specify a 'callback'
 * function.  If a callback function is specified, it will be called
 * instead of drush_command + _drush_invoke_hooks.
 *
 * Executes the specified command with the specified arguments on the
 * currently bootstrapped site using the current option contexts.
 * Note that _drush_invoke_hooks will not bootstrap any further than the
 * current command has already bootstrapped; therefore, you should only invoke
 * commands that have the same (or lower) bootstrap requirements.
 *
 * Call the correct hook for all the modules that implement it.
 * Additionally, the ability to rollback when an error has been encountered is also provided.
 * If at any point during execution, the drush_get_error() function returns anything but 0,
 * drush_invoke() will trigger $hook_rollback for each of the hooks that implement it,
 * in reverse order from how they were executed.  Rollbacks are also triggered any
 * time a hook function returns FALSE.
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
 * @param args
 *   An array of arguments to the command OR a single non-array argument.
 * @param defined_in_commandfile
 *   The name of the commandfile that this command exists in.  Will be looked up
 *   if not provided.
 * @return
 *   The return value will be passed along to the caller if --backend option is
 *   present. A boolean FALSE indicates failure and rollback will be intitated.
 *
 * This function should not be called directly.
 * @see drush_invoke() and @see drush_invoke_process()
 */
function _drush_invoke_hooks($command, $args, $defined_in_commandfile = NULL) {
  if ($defined_in_commandfile == NULL) {
    $defined_in_commandfile = drush_get_commandfile_for_command($command);
  }
  // If someone passed a standalone arg, convert it to a single-element array
  if (!is_array($args)) {
    $args = array($args);
  }
  // Include the external command file used by this command, if there is one.
  drush_command_include($command);
  // Generate the base name for the hook by converting all
  // dashes in the command name to underscores.
  $hook = str_replace("-", "_", $command);

  // Call the hook init function, if it exists.
  // If a command needs to bootstrap, it is advisable
  // to do so in _init; otherwise, new commandfiles
  // will miss out on participating in any stage that
  // has passed or started at the time it was discovered.
  $func = 'drush_' . $hook . '_init';
  if (function_exists($func)) {
    drush_log(dt("Calling drush command init function: !func", array('!func' => $func)), 'bootstrap');
    call_user_func_array($func, $args);
    _drush_log_drupal_messages();
    if (drush_get_error()) {
      drush_log(dt('The command @command could not be initialized.', array('@command' => $command)), 'error');
      return FALSE;
    }
  }

  $rollback = FALSE;
  $completed = array();
  $available_rollbacks = array();
  $all_available_hooks = array();

  // Iterate through the different hook variations
  $variations = array($hook . "_pre_validate", $hook . "_validate", "pre_$hook", $hook, "post_$hook");
  foreach ($variations as $var_hook) {
    // Get the list of command files.
    // We re-fetch the list every time through
    // the loop in case one of the hook function
    // does something that will add additional
    // commandfiles to the list (i.e. bootstrapping
    // to a higher phase will do this).
    $list = drush_commandfile_list();

    // Run all of the functions available for this variation
    foreach ($list as $commandfile => $filename) {
      $func = sprintf("drush_%s_%s", $commandfile, $var_hook);
      if (($defined_in_commandfile == $commandfile) && ($commandfile . "_" == substr($var_hook . "_",0,strlen($commandfile)+ 1))) {
        $func = sprintf("drush_%s", $var_hook);
      }
      if (function_exists($func)) {
        $all_available_hooks[] = $func . ' [* Defined in ' . $filename . ']';
        $available_rollbacks[] = $func . '_rollback';
        $completed[] = $func;
        $result = call_user_func_array($func, $args);
        // Only the 'main' callback can send data to backend.
        if ($var_hook == $hook) {
          // If the hook already called drush_backend_set_result,
          // then return that value. If it did not, then the return
          // value from the hook will be the value returned from
          // this routine.
          $return = drush_backend_get_result();
          if (empty($return)) {
            drush_backend_set_result($result);
            $return = $result;
          }
        }
        _drush_log_drupal_messages();
        if (drush_get_error() || ($result === FALSE)) {
          $rollback = TRUE;
          // break out of the foreach variations and foreach list
          break 2;
        }
      }
      else {
        $all_available_hooks[] = $func;
      }
    }
  }

  // If no hook functions were found, print a warning.
  if (empty($completed)) {
    $default_command_hook = sprintf("drush_%s_%s", $defined_in_commandfile, $hook);
    if (($defined_in_commandfile . "_" == substr($hook . "_",0,strlen($defined_in_commandfile)+ 1))) {
      $default_command_hook = sprintf("drush_%s", $hook);
    }
    $dt_args = array(
      '!command' => $command,
      '!default_func' => $default_command_hook,
    );
    $message = "No hook functions were found for !command. The primary hook function is !default_func(). Please implement this function. Run with --show-invoke to see all available hooks.";
    $return = drush_set_error('DRUSH_FUNCTION_NOT_FOUND', dt($message, $dt_args));
  }
  if (drush_get_option('show-invoke')) {
    // We show all available hooks up to and including the one that failed (or all, if there were no failures)
    drush_log(dt("Available drush_invoke() hooks for !command: !available", array('!command' => $command, '!available' => "\n" . implode("\n", $all_available_hooks))), 'ok');
  }
  if (drush_get_option('show-invoke') && !empty($available_rollbacks)) {
    drush_log(dt("Available rollback hooks for !command: !rollback", array('!command' => $command, '!rollback' => "\n" . implode("\n", $available_rollbacks))), 'ok');
  }

  // Something went wrong, we need to undo.
  if ($rollback) {
    if (drush_get_option('confirm-rollback', FALSE)) {
      // Optionally ask for confirmation, --yes and --no are ignored from here on as we are about to finish this process.
      drush_set_context('DRUSH_AFFIRMATIVE', FALSE);
      drush_set_context('DRUSH_NEGATIVE', FALSE);
      $rollback = drush_confirm(dt('Do you want to rollback? (manual cleanup might be required otherwise)'));
    }

    if ($rollback) {
      foreach (array_reverse($completed) as $func) {
        $rb_func = $func . '_rollback';
        if (function_exists($rb_func)) {
          call_user_func_array($rb_func, $args);
          _drush_log_drupal_messages();
          drush_log(dt("Changes made in !func have been rolled back.", array('!func' => $func)), 'rollback');
        }
      }
    }
    $return = FALSE;
  }

  if (isset($return)) {
    return $return;
  }
}

/**
 * Include, instantiate and validate command engines.
 *
 * @return FALSE if a engine doesn't validate.
 */
function _drush_load_command_engines($command) {
  foreach ($command['engines'] as $engine_type => $config) {
    drush_log(dt("Loading !engine engine.", array('!engine' => $engine_type), 'bootstrap'));
    $engine_info = drush_get_engines($engine_type);
    $engines = array_keys($engine_info['engines']);
    $default = isset($config['default'])?$config['default']:current($engines);
    // If the engine provides a command line option to choose between engine
    // implementations, get the user selection, if any.
    if (!empty($config['option'])) {
      $engine = drush_get_option($config['option'], $default);
    }
    // Otherwise the default engine is the only option.
    else {
      $engine = $default;
    }
    if (!in_array($engine, $engines)) {
      return drush_set_error('DRUSH_UNKNOWN_ENGINE_TYPE', dt('Unknown !engine_type engine !engine', array('!engine' => $engine, '!engine_type' => $engine_type)));
    }
    $result = drush_include_engine($engine_type, $engine);
    if ($result === FALSE) {
      return FALSE;
    }
    elseif (is_object($result) && method_exists($result, 'validate')) {
      $result = $result->validate();
    }
    else {
      $function = strtr($engine_type, '-', '_') . '_validate';
      if (function_exists($function)) {
        $result = call_user_func($function);
      }
    }
    if (!$result) {
      return FALSE;
    }
  }
}

/**
 * Add command structure info from each engine back into the command.
 */
function _drush_merge_engine_data(&$command) {
  foreach ($command['engines'] as $engine_type => $config) {
    // Normalize engines structure.
    if (!is_array($config)) {
      unset($command['engines'][$engine_type]);
      $command['engines'][$config] = array();
      $engine_type = $config;
    }

    // Get all implementations for this engine type.
    $engine_info = drush_get_engines($engine_type);
    if ($engine_info === FALSE) {
      return FALSE;
    }

    // Override engine_info with customizations in the command.
    $config = $command['engines'][$engine_type] += $engine_info['info'];

    // Add engine type global options to the command.
    $command['options'] += $config['options'];

    $engine_data = array();

    // If there's a single implementation for this engine type, it will be
    // loaded by default, and makes no sense to provide a command line option
    // to select the only flavor (ie. --release_info=updatexml), so we won't
    // add an option in this case.
    // Additionally, depending on the command, it may be convenient to extend
    // the command with the engine options.
    if (count($engine_info['engines']) == 1) {
      if ($config['add-options-to-command'] !== FALSE) {
        $engine = key($engine_info['engines']);
        $data = $engine_info['engines'][$engine];
        foreach (array('options', 'sub-options') as $key) {
          if (isset($data[$key])) {
            $engine_data[$key] = $data[$key];
          }
        }
      }
    }
    // Otherwise, provide a command option to choose between engines and add
    // the engine options and sub-obtions.
    else {
      // Process engines in order. First the default engine, the rest alphabetically.
      $default = $config['default'];
      $engines = array_keys($engine_info['engines']);
      asort($engines);
      array_unshift($engines, $default);
      $engines = array_unique($engines);

      // Extend the default engine description.
      $desc = $engine_info['engines'][$default]['description'];
      $engine_info['engines'][$default]['description'] = dt('Default type engine.', array('type' => $engine_type)) . ' ' . $desc;

      $engine_data += array(
        'options' => array(),
        'sub-options' => array(),
      );
      foreach ($engines as $engine) {
        $data = $engine_info['engines'][$engine];
        $option = $config['option'] . '=' . $engine;
        $engine_data['options'][$option] = array_key_exists('description', $data) ? $data['description'] : NULL;
        if (isset($data['options'])) {
          $engine_data['sub-options'][$option] = $data['options'];
        }
        if (isset($data['sub-options'])) {
          $engine_data['sub-options'] += $data['sub-options'];
        }
      }
    }
    $command = array_merge_recursive($command, $engine_data);
  }
}

/**
 * Fail with an error if the user specified options on the
 * command line that are not documented in the current command
 * record.
 */
function _drush_verify_cli_options($command) {
  // Start out with just the options in the current command record.
  $options = _drush_get_command_options($command);
  // Skip all tests if the command is marked to allow anything.
  // Also skip backend commands, which may have options on the commandline
  // that were inherited from the calling command.
  if (($command['allow-additional-options'] === TRUE) || (drush_get_option(array('backend', 'invoke'), FALSE))) {
    return TRUE;
  }
  // If 'allow-additional-options' contains a list of command names,
  // then union together all of the options from all of the commands.
  if (is_array($command['allow-additional-options'])) {
    $implemented = drush_get_commands();
    foreach ($command['allow-additional-options'] as $subcommand_name) {
      if (array_key_exists($subcommand_name, $implemented)) {
        $options = array_merge($options, _drush_get_command_options($implemented[$subcommand_name]));
      }
    }
  }
  // Also add in global options
  $options = array_merge($options, drush_get_global_options());

  // Now we will figure out which options in the cli context
  // are not represented in our options list.
  $cli_options = array_keys(drush_get_context('cli'));
  $allowed_options = _drush_flatten_options($options);
  $allowed_options = drush_append_negation_options($allowed_options);
  $disallowed_options = array_diff($cli_options, $allowed_options);
  if (!empty($disallowed_options)) {
    $unknown = count($disallowed_options) > 1 ? dt('Unknown options') : dt('Unknown option');
    $msg = dt("@unknown: --@options.  See `drush help @command` for available options. To suppress this error, add the option --strict=0.", array('@unknown' => $unknown, '@options' => implode(', --', $disallowed_options), '@command' => $command['command']));
    if (drush_get_option('strict', TRUE)) {
      return drush_set_error('DRUSH_UNKNOWN_OPTION', $msg);
    }
  }

  // Next check to see if all required options were specified.
  $missing_required_options = array();
  foreach ($command['options'] as $key => $value) {
    if (is_array($value) && array_key_exists('required', $value)) {
      $option_value = drush_get_option($key, NULL);
      if (!isset($option_value)) {
        $missing_required_options[] = $key;
      }
    }
  }
  if (!empty($missing_required_options)) {
    $missing = count($missing_required_options) > 1 ? dt('Missing required options') : dt('Missing required option');
    return drush_set_error(dt("@missing: --@options.  See `drush help @command` for information on usage.", array('@missing' => $missing, '@options' => implode(', --', $missing_required_options), '@command' => $command['command'])));
  }
  return TRUE;
}

function drush_append_negation_options($allowed_options) {
  $new_allowed = $allowed_options;
  foreach ($allowed_options as $option) {
    $new_allowed[] = 'no-' . $option;
  }
  return $new_allowed;
}

function _drush_verify_cli_arguments($command) {
  // Check to see if all of the required arguments
  // are specified.
  if ($command['required-arguments']) {
    $required_arg_count = $command['required-arguments'];
    if ($required_arg_count === TRUE) {
      $required_arg_count = count($command['argument-description']);
    }
    if ((count($command['arguments'])) < $required_arg_count) {
      $missing = count($required_arg_count) > 1 ? dt('Missing required arguments') : dt('Missing required argument');
      $required = implode("', '", array_keys($command['argument-description']));
      return drush_set_error(dt("@missing: '@required'.  See `drush help @command` for information on usage.", array('@missing' => $missing, '@required' => $required, '@command' => $command['command'])));
    }
  }
  return TRUE;
}

/**
 * Return the list of all of the options for the given
 * command record by merging the 'options' and 'sub-options'
 * records.
 */
function _drush_get_command_options($command) {
  drush_command_invoke_all_ref('drush_help_alter', $command);
  $options = $command['options'];
  foreach ($command['sub-options'] as $group => $suboptions) {
    $options = array_merge($options, $suboptions);
  }
  return $options;
}

/**
 * Return the array keys of $options, plus any 'short-form'
 * representations that may appear in the option's value.
 */
function _drush_flatten_options($options) {
  $flattened_options = array();

  foreach($options as $key => $value) {
    // engine sections start with 'package-handler=git_drupalorg',
    // or something similar.  Get rid of everything from the = onward.
    if (($eq_pos = strpos($key, '=')) !== FALSE) {
      $key = substr($key, 0, $eq_pos);
    }
    $flattened_options[] = $key;
    if (is_array($value)) {
      if (array_key_exists('short-form', $value)) {
        $flattened_options[] = $value['short-form'];
      }
    }
  }
  return $flattened_options;
}

/**
 * Get the options that were passed to the current command.
 *
 * This function returns an array that contains all of the options
 * that are appropriate for forwarding along to drush_invoke_process.
 *
 * @return
 *   An associative array of option key => value pairs.
 */
function drush_redispatch_get_options() {
  // Start off by taking everything from the site alias and command line
  // ('cli' context)
  $cli_context = drush_get_context('cli');
  // local php settings should not override sitealias settings
  unset($cli_context['php']);
  unset($cli_context['php-options']);
  // cli overrides sitealias
  $options = $cli_context + drush_get_context('alias');

  $options = array_diff_key($options, array_flip(drush_sitealias_site_selection_keys()));
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
  // If --bootstrap-to-first-arg is specified, do not
  // pass it along to remote commands.
  unset($options['bootstrap-to-first-arg']);

  return $options;
}

/**
 * @} End of "defgroup dispatching".
 */

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
  $command_args = NULL;
  $global_options = array();
  $target_alias_name = NULL;
  // It would be nice if commandfiles could somehow extend this list,
  // but it is not possible. We need to parse args before we find commandfiles,
  // because the specified options may affect how commandfiles are located.
  // Therefore, commandfiles are loaded too late to affect arg parsing.
  // There are only a limited number of short options anyway; drush reserves
  // all for use by drush core.
  static $arg_opts = array('c', 'u', 'r', 'l', 'i');

  // Check to see if we were executed via a "#!/usr/bin/env drush" script
  drush_adjust_args_if_shebang_script($args);

  // Now process the command line arguments.  We will divide them
  // into options (starting with a '-') and arguments.
  $arguments = $options = array();

  for ($i = 1; $i < count($args); $i++) {
    $opt = $args[$i];
    // We set $command_args to NULL until the first argument that is not
    // an alias is found (the command); we put everything that follows
    // into $command_args.
    if (is_array($command_args)) {
      $command_args[] = $opt;
    }
    // Is the arg an option (starting with '-')?
    if (!empty($opt) && $opt{0} == "-" && strlen($opt) != 1) {
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
          // Raising errors for missing option values should be handled by the
          // bootstrap or specific command, so we no longer do this here.
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
      // Once we find the first argument, record the command args and global options
      if (!is_array($command_args)) {
        // Remember whether we set $target_alias_name on a previous iteration,
        // then record the $target_alias_name iff this arguement references a valid site alias.
        $already_set_target = is_string($target_alias_name);
        if (!$already_set_target && drush_sitealias_valid_alias_format($opt)) {
          $target_alias_name = $opt;
        }
        // If an alias record was set on a previous iteration, then this
        // argument must be the command name.  If we set the target alias
        // record on this iteration, then this is not the command name.
        // If we've found the command name, then save $options in $global_options
        // (all options that came before the command name), and initialize
        // $command_args to an array so that we will begin storing all args
        // and options that follow the command name in $command_args.
        if ($already_set_target || (!is_string($target_alias_name))) {
          $command_args = array();
          $global_options = $options;
        }
      }
    }
  }
  // If no arguments are specified, then the command will
  // be either 'help' or 'version' (the later if --version is specified)
  if (!sizeof($arguments)) {
    if (array_key_exists('version', $options)) {
      $arguments = array('version');
    }
    else {
      $arguments = array('help');
    }
  }
  if (is_array($command_args)) {
    drush_set_context('DRUSH_COMMAND_ARGS', $command_args);
  }
  drush_set_context('DRUSH_GLOBAL_CLI_OPTIONS', $global_options);

  // Handle the "@shift" alias, if present
  drush_process_bootstrap_to_first_arg($arguments);

  drush_set_arguments($arguments);
  drush_set_config_special_contexts($options);
  drush_set_context('cli', $options);
  return $arguments;
}

/**
 * Pop an argument off of drush's argument list
 */
function drush_shift() {
  $arguments = drush_get_arguments();
  $result = NULL;
  if (!empty($arguments)) {
    // The php-script command uses the DRUSH_SHIFT_SKIP
    // context to cause drush_shift to skip the 'php-script'
    // command and the script path argument when it is
    // called from the user script.
    $skip_count = drush_get_context('DRUSH_SHIFT_SKIP');
    if (is_numeric($skip_count)) {
      for ($i = 0; $i < $skip_count; $i++) {
        array_shift($arguments);
      }
      $skip_count = drush_set_context('DRUSH_SHIFT_SKIP', 0);
    }
    $result = array_shift($arguments);
    drush_set_arguments($arguments);
  }
  return $result;
}

/**
 * Special checking for "shebang" script handling.
 *
 * If there is a file 'script.php' that begins like so:
 *   #!/path/to/drush
 * Then $args will be:
 *   /path/to/drush /path/to/script userArg1 userArg2 ...
 * If it instead starts like this:
 *   #!/path/to/drush --flag php-script
 * Then $args will be:
 *   /path/to/drush "--flag php-script" /path/to/script userArg1 userArg2 ...
 * (Note that execve does not split the parameters from
 * the shebang line on whitespace; see http://en.wikipedia.org/wiki/Shebang_%28Unix%29)
 * When drush is called via one of the "shebang" lines above,
 * the first or second parameter will be the full path
 * to the "shebang" script file -- and if the path to the
 * script is in the second position, then we will expect that
 * the argument in the first position must begin with a
 * '@' (alias) or '-' (flag).  Under ordinary circumstances,
 * we do not expect that the drush command must come before
 * any argument that is the full path to a file.  We use
 * this assumption to detect "shebang" script execution.
 */
function drush_adjust_args_if_shebang_script(&$args) {
  if (drush_has_bash()) {
    if (_drush_is_drush_shebang_script($args[1])) {
      // If $args[1] is a drush "shebang" script, we will insert
      // the option "--bootstrap-to-first-arg" and the command
      // "php-script" at the beginning of  @args, so the command
      // line args become:
      //   /path/to/drush --bootstrap-to-first-arg php-script /path/to/script userArg1 userArg2 ...
      drush_set_option('bootstrap-to-first-arg', TRUE);
      array_splice($args, 1, 0, array('php-script'));
      drush_set_context('DRUSH_SHEBANG_SCRIPT', TRUE);
    }
    elseif (((strpos($args[1], ' ') !== FALSE) || (!ctype_alnum($args[1][0]))) && (_drush_is_drush_shebang_script($args[2]))) {
      // If $args[2] is a drush "shebang" script, we will insert
      // the space-exploded $arg[1] in place of $arg[1], so the
      // command line args become:
      //   /path/to/drush scriptArg1 scriptArg2 ... /path/to/script userArg1 userArg2 ...
      // If none of the script arguments look like a drush command,
      // then we will insert "php-script" as the default command to
      // execute.
      $script_args = explode(' ', $args[1]);
      $has_command = FALSE;
      foreach ($script_args as $script_arg) {
        if (preg_match("/^[a-z][a-z0-9-]*$/",$script_arg)) {
          $has_command = TRUE;
        }
      }
      if (!$has_command) {
        $script_args[] = 'php-script';
      }
      array_splice($args, 1, 1, $script_args);
      drush_set_context('DRUSH_SHEBANG_SCRIPT', TRUE);
    }
  }
}

/**
 * Process the --bootstrap-to-first-arg option, if it is present.
 *
 * This option checks to see if the first user-provided argument is an alias
 * or site specification; if it is, it will be shifted into the first argument
 * position, where it will specify the site to bootstrap. The result of this
 * is that if your shebang line looks like this:
 *
 * #!/path/to/drush --bootstrap-to-first-arg php-script
 *
 * Then when you run that script, you can optionally provide an alias such
 * as @dev as the first argument (e.g. $ ./mydrushscript.php @dev scriptarg1
 * scriptarg2). Since this is the behavior that one would usually want,
 * it is default behavior for a canonical script. That is, a script
 * with a simple shebang line, like so:
 *
 * #!/path/to/drush
 *
 * will implicitly have "--bootstrap-to-first-arg" and "php-script" prepended, and will therefore
 * behave exactly like the first example. To write a script that does not
 * use --bootstrap-to-first-arg, then the drush command or at least one flag must be explicitly
 * included, like so:
 *
 * #!/path/to/drush php-script
 */
function drush_process_bootstrap_to_first_arg(&$arguments) {
  if (drush_get_option('bootstrap-to-first-arg', FALSE)) {
    $shift_alias_pos = 1 + (drush_get_context('DRUSH_SHEBANG_SCRIPT') === TRUE);
    if (sizeof($arguments) >= $shift_alias_pos) {
      $shifted_alias = $arguments[$shift_alias_pos];
      $alias_record = drush_sitealias_get_record($shifted_alias);
      if (!empty($alias_record)) {
        // Move the alias we shifted from its current position
        // in the argument list to the front of the list
        array_splice($arguments, $shift_alias_pos, 1);
        array_unshift($arguments, $shifted_alias);
      }
    }
  }
}

/**
 * Get the short commandfile name that matches the
 * command.
 *
 * @param $command
 *   The name of the command (e.g. search-index)
 * @return String
 *   The short commandfile name where that command was
 *   defined (e.g. search, not search.drush.inc)
 */
function drush_get_commandfile_for_command($command) {
  $commandfile = FALSE;

  $commands = drush_get_commands();
  if (array_key_exists($command, $commands)) {
    $commandfile = $commands[$command]['commandfile'];
  }

  return $commandfile;
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
        // Add some defaults and normalize the command descriptor.
        $command += drush_command_defaults($key, $commandfile, $path);

        // Add engine data.
        _drush_merge_engine_data($command);

        // Translate command.
        drush_command_translate($command);

        // If command callback function name begins with "drush_$commandfile_",
        // then fix up the command entry so that drush_invoke will be
        // called by way of drush_command.  This will cause all
        // of the applicable hook functions to be called for the
        // command when it is invoked.  If the callback function does
        // -not- begin with its commandfile name, then it will be
        // called directly by drush_dispatch, and no hook functions
        // will be called (e.g. you cannot hook drush_print_file).
        if ($command['callback'] != 'drush_command') {
          $required_command_prefix = 'drush_' . $commandfile . '_';
          if ((substr($command['callback'], 0, strlen($required_command_prefix)) == $required_command_prefix)) {
            $command['command-hook'] = substr($command['callback'], strlen('drush_'));
            $command['callback'] = 'drush_command';
          }
        }

        $commands[$key] = $command;
        // For every alias, make a copy of the command and store it in the command list
        // using the alias as a key
        if (isset($command['aliases']) && count($command['aliases'])) {
          foreach ($command['aliases'] as $alias) {
            $commands[$alias] = $command;
            $commands[$alias]['is_alias'] = TRUE;
          }
        }
      }
    }
  }

  return drush_set_context('DRUSH_COMMANDS', $commands);
}

function drush_command_defaults($key, $commandfile, $path) {
  return array(
    'command' => $key,
    'command-hook' => $key,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_LOGIN,
    'callback arguments' => array(),
    'commandfile' => $commandfile,
    'path' => dirname($path),
    'engines' => array(), // Helpful for drush_show_help().
    'callback' => 'drush_command',
    'description' => NULL,
    'sections' => array(
      'examples' => 'Examples',
      'arguments' => 'Arguments',
      'options' => 'Options',
    ),
    'arguments' => array(),
    'required-arguments' => FALSE,
    'options' => array(),
    'sub-options' => array(),
    'allow-additional-options' => FALSE,
    'examples' => array(),
    'aliases' => array(),
    'core' => array(),
    'scope' => 'site',
    'drupal dependencies' => array(),
    'drush dependencies' => array(),
    'handle-remote-commands' => FALSE,
    'strict-option-handling' => FALSE,
    'bootstrap_errors' => array(),
    'topics' => array(),
    'hidden' => FALSE,
  );
}

/**
 * Translates description and other keys of a command definition.
 *
 * @param $command
 *   A command definition.
 */
function drush_command_translate(&$command) {
  $command['description'] = _drush_command_translate($command['description']);
  $keys = array('arguments', 'options', 'examples', 'sections');
  foreach ($keys as $key) {
    foreach ($command[$key] as $k => $v) {
      if (is_array($v)) {
        $v['description'] = _drush_command_translate($v['description']);
      }
      else {
        $v = _drush_command_translate($v);
      }
      $command[$key][$k] = $v;
    }
  }
}

/**
 * Helper function for drush_command_translate().
 *
 * @param $source
 *   String or array.
 */
function _drush_command_translate($source) {
  return is_array($source) ? call_user_func_array('dt', $source) : dt($source);
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
 * is an associative array. Some of its entries are:
 * - callback arguments: an array of arguments to pass to the calback.
 * - description: description of the command.
 * - arguments: an array of arguments that are understood by the command. for help texts.
 * - required-arguments: The minimum number of arguments that are required, or TRUE if all are required.
 * - options: an array of options that are understood by the command. for help texts.
 * - examples: an array of examples that are understood by the command. for help texts.
 * - scope: one of 'system', 'project', 'site'.
 * - bootstrap: drupal bootstrap level (depends on Drupal major version). -1=no_bootstrap.
 * - core: Drupal major version required.
 * - drupal dependencies: drupal modules required for this command.
 * - drush dependencies: other drush command files required for this command.
 * - handle-remote-commands: set to TRUE if `drush @remote mycommand` should be executed
 *   locally rather than remotely dispatched.  When this mode is set, the target site
 *   can be obtained via:
 *     drush_get_context('DRUSH_TARGET_SITE_ALIAS')
 * - strict-option-handling: set to TRUE if drush should strictly separate local command
 *   cli options from the global options.  Usually, drush allows global cli options and
 *   command cli options to be interspersed freely on the commandline.  For commands where
 *   this flag is set, options are separated, with global options comming before the
 *   command names, and command options coming after, like so:
 *     drush --global-options command --command-options
 *   In this mode, the command options are no longer available via drush_get_option();
 *   instead, they can be retrieved via:
 *     $args = drush_get_original_cli_args_and_options();
 *     $args = drush_get_context('DRUSH_COMMAND_ARGS', array());
 *   In this case, $args will contain the command args and options literally, exactly as they
 *   were entered on the command line, and in the same order as they appeared.
 */
function drush_parse_command() {
  $args = drush_get_arguments();
  $command = FALSE;

  // Get a list of all implemented commands.
  $implemented = drush_get_commands();
  if (isset($implemented[$args[0]])) {
    $command = $implemented[$args[0]];
    $arguments = array_slice($args, 1);
  }

  // We have found a command that matches. Set the appropriate values.
  if ($command) {
    // Special case. Force help command if --help option was specified.
    if (drush_get_option('help')) {
      $arguments = array($command['command']);
      $command = $implemented['help'];
      $command['arguments'] = $arguments;
    }
    else {
      _drush_prepare_command($command, $arguments);
    }
    drush_set_command($command);
  }
  return $command;
}

/*
 * Called by drush_parse_command.  If a command is dispatched
 * directly by drush_dispatch, then drush_dispatch will call
 * this function.
 */
function _drush_prepare_command(&$command, $arguments = array()) {
  // Drush overloads $command['arguments']; save the argument description
  if (!isset($command['argument-description'])) {
    $command['argument-description'] = $command['arguments'];
  }
  // Merge specified callback arguments, which precede the arguments passed on the command line.
  if (isset($command['callback arguments']) && is_array($command['callback arguments'])) {
    $arguments = array_merge($command['callback arguments'], $arguments);
  }
  $command['arguments'] = $arguments;
}

/**
 * Invoke a hook in all available command files that implement it.
 *
 * @see drush_command_invoke_all_ref()
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
  if (count($args) == 1) {
    $args[] = NULL;
  }
  $reference_value = $args[1];
  $args[1] = &$reference_value;

  return call_user_func_array('drush_command_invoke_all_ref', $args);
}

/**
 * A drush_command_invoke_all() that wants the first parameter to be passed by reference.
 *
 * @see drush_command_invoke_all()
 */
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
 * - sites/all/drush in current Drupal site.
 * - Folders belonging to enabled modules in the current Drupal site.
 *
 * Commands implementing hook_drush_load() in MODULE.drush.load.inc with
 * a return value FALSE will not be loaded.
 *
 * A Drush command file is a file that matches "*.drush.inc".
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

function _drush_find_commandfiles($phase, $phase_max = FALSE) {
  if (!$phase_max) {
    $phase_max = $phase;
  }

  $searchpath = array();
  switch ($phase) {
    case DRUSH_BOOTSTRAP_DRUSH:
      // Core commands shipping with drush
      $searchpath[] = realpath(dirname(__FILE__) . '/../commands/');

      // User commands, specified by 'include' option
      if ($include = drush_get_context('DRUSH_INCLUDE', FALSE)) {
        foreach ($include as $path) {
          if (is_dir($path)) {
            drush_log('Include ' . $path, 'notice');
            $searchpath[] = $path;
          }
        }
      }

      // System commands, residing in $SHARE_PREFIX/share/drush/commands
      $share_path = drush_get_context('DRUSH_SITE_WIDE_COMMANDFILES');
      if (is_dir($share_path)) {
        $searchpath[] = $share_path;
      }

      // User commands, residing in ~/.drush
      $per_user_config_dir = drush_get_context('DRUSH_PER_USER_CONFIGURATION');
      if (!empty($per_user_config_dir)) {
        $searchpath[] = $per_user_config_dir;
      }

      // Include commandfiles located in Drupal's sites/all/drush even before root is bootstrapped.
      if ($drupal_root = drush_get_context('DRUSH_SELECTED_DRUPAL_ROOT')) {
        $searchpath[] = $drupal_root . '/sites/all/drush';
      }
      break;
    case DRUSH_BOOTSTRAP_DRUPAL_SITE:
      // If we are going to stop bootstrapping at the site, then
      // we will quickly add all commandfiles that we can find for
      // any module associated with the site, whether it is enabled
      // or not.  If we are, however, going to continue on to bootstrap
      // all the way to DRUSH_BOOTSTRAP_DRUPAL_FULL, then we will
      // instead wait for that phase, which will more carefully add
      // only those Drush command files that are associated with
      // enabled modules.
      if ($phase_max < DRUSH_BOOTSTRAP_DRUPAL_FULL) {
        $searchpath[] = conf_path() . '/modules';
        // Add all module paths, even disabled modules. Prefer speed over accuracy.
        $searchpath[] = 'sites/all/modules';
      }
      if ($phase_max < DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION) {
        // Too early for variable_get('install_profile', 'default'); look in all profiles.
        // If we're definitely going to get to DRUPAL_CONFIGURATION, then wait and
        // see if we have 'install_profile' available there.
        $searchpath[] = "profiles";
      }
      // Planning ahead for Drush scripts in core Drupal.
      if (drush_drupal_major_version() >=8) {
        $searchpath[] = 'modules';
      }
      $searchpath[] = 'sites/all/themes';
      $searchpath[] = conf_path() . '/themes';
      break;
    case DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION:
      // See comment above  regarding this if() condition.
      if ($phase_max < DRUSH_BOOTSTRAP_DRUPAL_FULL) {
        // You must define your install_profile in settings.php. The DB is not sufficient.
        // Drupal core does not yet do that. See http://drupal.org/node/545452.
        if ($profile = variable_get('install_profile', NULL)) {
          $searchpath[] = "profiles/$profile/modules";
        }
        else {
          // If 'install_profile' is not available, check all profiles.
          $searchpath[] = "profiles";
        }
      }
      break;
    case DRUSH_BOOTSTRAP_DRUPAL_FULL:
      // Add enabled module paths. Since we are bootstrapped,
      // we can use the Drupal API.
      $ignored_modules = drush_get_option_list('ignored-modules', array());
      foreach (array_diff(module_list(), $ignored_modules) as $module) {
        $filename = drupal_get_filename('module', $module);
        $searchpath[] = dirname($filename);
      }
      break;
  }

  _drush_add_commandfiles($searchpath, $phase);
}

function _drush_add_commandfiles($searchpath, $phase = NULL, $reset = FALSE) {
  static $evaluated = array();
  static $deferred = array();

  $cache =& drush_get_context('DRUSH_COMMAND_FILES', array());

  if (sizeof($searchpath)) {
    if (!$reset) {
      // Assemble a cid specific to the bootstrap phase and searchpaths.
      $cid = drush_get_cid('commandfiles-' . $phase, array(), $searchpath);
      $command_cache = drush_cache_get($cid);
      if (isset($command_cache->data)) {
        $cached_list = $command_cache->data;
        // Take drush_make out of the cache to help people transitioning
        // from using drush make as a contrib module to drush make in core.
        // See: http://drupal.org/node/1366746
        unset($cached_list['drush_make']);
        // If we want to temporarily ignore modules via 'ignored-modules',
        // then we need to take these out of the cache as well.
        foreach (drush_get_option_list('ignored-modules') as $ignored) {
          unset($cached_list[$ignored]);
        }
      }
    }

    // Build a list of all of the modules to attempt to load.
    // Start with any modules deferred from a previous phase.
    $list = $deferred;
    if (isset($cached_list)) {
      $list = array_merge($list, $cached_list);
    }
    else {
      // Scan for drush command files; add to list for consideration if found.
      foreach (array_unique($searchpath) as $path) {
        if (is_dir($path)) {
          $nomask = array_merge(drush_filename_blacklist(), drush_get_option_list('ignored-modules'));
          $dmv = DRUSH_MAJOR_VERSION;
          $files = drush_scan_directory($path, "/\.drush($dmv|)\.inc$/", $nomask);
          foreach ($files as $filename => $info) {
            $module = basename($filename);
            $module = str_replace(array('.drush.inc', ".drush$dmv.inc"), '', $module);
            // Only try to bootstrap modules that we have never seen before, or that we
            // have tried to load but did not due to an unmet _drush_load() requirement.
            if (!array_key_exists($module, $evaluated) && file_exists($filename)) {
              $evaluated[$module] = TRUE;
              $list[$module] = $filename;
            }
          }
        }
      }
      if (isset($cid)) {
        drush_cache_set($cid, $list);
      }
    }

    // Check each file in the consideration list; if there is
    // a modulename_drush_load() function in modulename.drush.load.inc,
    // then call it to determine if this file should be loaded.
    foreach ($list as $module => $filename) {
      $load_command = TRUE;
      $load_test_inc = dirname($filename) . "/" . $module . ".drush.load.inc";
      if (file_exists($load_test_inc)) {
        include_once($load_test_inc);
        $load_test_func = $module . "_drush_load";
        if (function_exists($load_test_func)) {
          $load_command = $load_test_func($phase);
        }
      }
      if ($load_command) {
        // Only try to require if the file exists. If not, a file from the
        // command file cache may not be available anymore, in which case
        // we rebuild the cache for this phase.
        if ($filepath = realpath($filename)) {
          require_once $filepath;
          unset($deferred[$module]);
        }
        elseif (!$reset) {
          _drush_add_commandfiles($searchpath, $phase, TRUE);
        }
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
 * Substrings to ignore during commandfile searching.
 */
function drush_filename_blacklist() {
  return array_diff(array('.', '..', 'drush_make', 'examples', 'tests', 'disabled', 'gitcache', 'cache', 'drush4', 'drush5', 'drush6', 'drush7'), array('drush' . DRUSH_MAJOR_VERSION));
}

/**
 * Conditionally include files based on the command used.
 *
 * Steps through each of the currently loaded commandfiles and
 * loads an optional commandfile based on the key.
 *
 * When a command such as 'pm-enable' is called, this
 * function will find all 'enable.pm.inc' files that
 * are present in each of the commandfile directories.
 */
function drush_command_include($command) {
  $include_files = drush_command_get_includes($command);
  foreach($include_files as $filename => $commandfile) {
    drush_log(dt('Including !filename', array('!filename' => $filename)), 'bootstrap');
    include_once($filename);
  }
}

function drush_command_get_includes($command) {
  $include_files = array();
  $parts = explode('-', $command);
  $command = implode(".", array_reverse($parts));

  $commandfiles = drush_commandfile_list();
  $options = array();
  foreach ($commandfiles as $commandfile => $file) {
    $filename = sprintf("%s/%s.inc", dirname($file), $command);
    if (file_exists($filename)) {
      $include_files[$filename] = $commandfile;
    }
  }
  return $include_files;
}

/**
 * Conditionally include default options based on the command used.
 */
function drush_command_default_options($command = NULL) {
  $command_default_options = drush_get_context('command-specific');
  return drush_command_set_command_specific($command_default_options);
}

function drush_sitealias_command_default_options($site_record, $prefix, $command = NULL) {
  if (isset($site_record) && array_key_exists($prefix . 'command-specific', $site_record)) {
    return drush_command_set_command_specific($site_record[$prefix . 'command-specific'], $command);
  }
  return FALSE;
}

function drush_command_set_command_specific($command_default_options, $command = NULL) {
  if (!$command) {
    $command = drush_get_command();
  }
  if ($command) {
    // Look for command-specific options for this command
    // keyed both on the command's primary name, and on each
    // of its aliases.
    $options_were_set = _drush_command_set_default_options($command_default_options, $command['command']);
    if (isset($command['aliases']) && count($command['aliases'])) {
      foreach ($command['aliases'] as $alias) {
        $options_were_set += _drush_command_set_default_options($command_default_options, $alias);
      }
    }
    // If we set or cleared any options, go back and re-bootstrap any global
    // options such as -y and -v.
    if (!empty($options_were_set)) {
      _drush_bootstrap_global_options();
    }
    // If the command uses strict option handling, back out any global
    // options that were set.
    if ($command['strict-option-handling']) {
      $global_options = drush_get_global_options();
      foreach ($options_were_set as $key) {
        if (array_key_exists($key, $global_options)) {
          if (!array_key_exists('context', $global_options[$key])) {
            $strict_options_warning =& drush_get_context('DRUSH_STRICT_OPTIONS_WARNING', array());
            if (!array_key_exists($key, $strict_options_warning)) {
              drush_log(dt("Global option --!option not supported in command-specific options for command !command due to a limitation in strict option handling.", array('!option' => $key, '!command' => $command['command'])), 'warning');
              $strict_options_warning[$key] = TRUE;
            }
          }
          drush_unset_option($key, 'specific');
        }
      }
    }
  }
}

function _drush_command_set_default_options($command_default_options, $command) {
  $options_were_set = array();
  if (array_key_exists($command, $command_default_options)) {
    foreach ($command_default_options[$command] as $key => $value) {
      // We set command-specific options in their own context
      // that is higher precedence than the various config file
      // context, but lower than command-line options.
      if (!drush_get_option('no-' . $key, FALSE)) {
        drush_set_option($key, $value, 'specific');
        $options_were_set[] = $key;
      }
    }
  }
  return $options_were_set;
}

/**
 * Return the original cli args and options, exactly as they
 * appeared on the command line, and in the same order.
 * Any command-specific options that were set will also
 * appear in this list, appended at the very end.
 *
 * The args and options returned are raw, and must be
 * escaped as necessary before use.
 */
function drush_get_original_cli_args_and_options($command = NULL) {
  $args = drush_get_context('DRUSH_COMMAND_ARGS', array());
  $command_specific_options = drush_get_context('specific');
  if ($command == NULL) {
    $command = drush_get_command();
  }
  $command_options = ($command == NULL) ? array() : _drush_get_command_options($command);
  foreach ($command_specific_options as $key => $value) {
    if (!array_key_exists($key, $command_options)) {
      if (($value === TRUE) || (!isset($value))) {
        $args[] = "--$key";
      }
      else {
        $args[] = "--$key=$value";
      }
    }
  }
  return $args;
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
  return function_exists($commandfile . '_' . $hook);
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
  $command['bootstrap_errors']['DRUSH_COMMAND_INSUFFICIENT_BOOTSTRAP'] = dt('Command !command needs a higher bootstrap level to run - you will need to invoke drush from a more functional Drupal environment to run this command.', array('!command' => $command['command']));
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
  // If the command bootstrap is DRUSH_BOOTSTRAP_MAX, then we will
  // allow the requirements to pass if we have not successfully
  // bootstrapped Drupal.  The combination of DRUSH_BOOTSTRAP_MAX
  // and 'drupal dependencies' indicates that the drush command
  // will use the dependent modules only if they are available.
  if ($command['bootstrap'] == DRUSH_BOOTSTRAP_MAX) {
    // If we have not bootstrapped, then let the dependencies pass;
    // if we have bootstrapped, then enforce them.
    if (drush_get_context('DRUSH_BOOTSTRAP_PHASE') < DRUSH_BOOTSTRAP_DRUPAL_FULL) {
      return TRUE;
    }
  }
  // If there are no drupal dependencies, then do nothing
  if (!empty($command['drupal dependencies'])) {
    foreach ($command['drupal dependencies'] as $dependency) {
      if(!function_exists('module_exists') || !module_exists($dependency)) {
        $command['bootstrap_errors']['DRUSH_COMMAND_DEPENDENCY_ERROR'] = dt('Command !command needs the following modules installed/enabled to run: !dependencies.', array('!command' => $command['command'], '!dependencies' => implode(', ', $command['drupal dependencies'])));
        return FALSE;
      }
    }
  }
  return TRUE;
}

/**
 * Check that a command has its declared drush dependencies available or have no
 * dependencies. Drush dependencies are helpful when a command is invoking
 * another command, or implementing its API.
 *
 * @param $command
 *   Command to check. Any errors  will be added to the 'bootstrap_errors' element.
 * @return
 *   TRUE if dependencies are met.
 */
function drush_enforce_requirement_drush_dependencies(&$command) {
  // If there are no drush dependencies, then do nothing.
  if (!empty($command['drush dependencies'])) {
    $commandfiles = drush_commandfile_list();
    foreach ($command['drush dependencies'] as $dependency) {
      if (!isset($commandfiles[$dependency])) {
        $dt_args = array(
          '!command' => $command['command'],
          '!dependency' => "$dependency.drush.inc",
        );
        $command['bootstrap_errors']['DRUSH_COMMANDFILE_DEPENDENCY_ERROR'] = dt('Command !command needs the following drush command file to run: !dependency.', $dt_args);
        return FALSE;
      }
    }
  }
  return TRUE;
}

/**
 * Check that a command is valid for the current major version of core. Handles
 * explicit version numbers and 'plus' numbers like 7+ (compatible with 7,8 ...).
 *
 * @param $command
 *   Command to check. Any errors  will be added to the 'bootstrap_errors' element.
 *
 * @return
 *   TRUE if command is valid.
 */
function drush_enforce_requirement_core(&$command) {
  $major = drush_drupal_major_version();
  if (!$core = $command['core']) {
    return TRUE;
  }
  foreach ($core as $compat) {
    if ($compat == $major) {
      return TRUE;
    }
    elseif (substr($compat, -1) == '+' && $major >= substr($compat, 0, strlen($compat)-1)) {
      return TRUE;
    }
  }
  $versions = array_pop($core);
  if (!empty($core)) {
    $versions = implode(', ', $core) . dt(' or ') . $versions;
  }
  $command['bootstrap_errors']['DRUSH_COMMAND_CORE_VERSION_ERROR'] = dt('Command !command requires Drupal core version !versions to run.', array('!command' => $command['command'], '!versions' => $versions));
}

/*
 * Check if a shell alias exists for current request. If so, re-route to
 * core-execute like the and pass alias value along with rest of CLI arguments.
 */
function drush_shell_alias_replace() {
  $args = drush_get_arguments();
  $argv = drush_get_context('argv');
  $first = current($args);
  // @todo drush_get_option is awkward here.
  $shell_aliases = drush_get_context('shell-aliases', array());
  if (isset($shell_aliases[$first])) {
    // Shell alias found for first argument in the request.
    $alias_value = $shell_aliases[$first];
    if (!is_array($alias_value)) {
      // Shell aliases can have embedded variables such as {{@target}} and {{%root}}
      // that are replaced with the name of the target site alias, or the value of a
      // path alias defined in the target site alias record.  We only support replacements
      // when the alias value is a string; if it is already broken out into an array,
      // then the values therein are used literally.
      $alias_variables = array( '{{@target}}' => '@none' );
      $target_site_alias = drush_get_context('DRUSH_TARGET_SITE_ALIAS', FALSE);
      if ($target_site_alias) {
        $alias_variables = array( '{{@target}}' => $target_site_alias );
        $target = drush_sitealias_get_record($target_site_alias);
        foreach ($target as $key => $value) {
          if (!is_array($value)) {
            $alias_variables['{{' . $key . '}}'] = $value;
          }
        }
        if (array_key_exists('path-aliases', $target)) {
          foreach ($target['path-aliases'] as $key => $value) {
            // n.b. $key will contain something like "%root" or "%files".
            $alias_variables['{{' . $key . '}}'] = $value;
          }
        }
      }
      $alias_value = str_replace(array_keys($alias_variables), array_values($alias_variables), $alias_value);
      // Check for unmatched replacements
      $matches = array();
      $match_result = preg_match('/{{[%@#]*[a-z0-9.]*}}/', $alias_value, $matches);
      if ($match_result) {
        $unmatched_replacements = implode(', ', $matches);
        $unmatched_replacements = preg_replace('/[{}]/', '', $unmatched_replacements);
        return drush_set_error('DRUSH_SHELL_ALIAS_UNMATCHED_REPLACEMENTS', dt('The shell alias @alias-name uses replacements "@unmatched". You must use this command with a site alias (e.g. `drush @myalias @alias-name ...`) that defines all of these variables.', array('@alias-name' => $first, '@unmatched' => $unmatched_replacements)));
      }
      $alias_value = explode(' ', $alias_value);
    }
    drush_log(dt('Shell alias found: !key => !value', array('!key' => $first, '!value' => implode(' ', $alias_value))), 'debug');
    $replacement = $alias_value;
    if (substr($alias_value[0], 0, 1) == '!') {
      $replacement[0] = ltrim($replacement[0], '!');
      array_unshift($replacement, 'core-execute');
    }
    $pos = array_search($first, $argv);
    array_splice($argv, $pos, 1, $replacement);
    drush_set_context('argv', $argv);
    drush_parse_args();
    _drush_bootstrap_global_options();
  }
}
