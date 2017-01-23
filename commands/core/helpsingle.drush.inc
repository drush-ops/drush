<?php

/**
 * Implementation of hook_drush_command().
 *
 * In this hook, you specify which commands your
 * drush module makes available, what it does and
 * description.
 *
 * Notice how this structure closely resembles how
 * you define menu hooks.
 *
 * @return
 *   An associative array describing your command(s).
 */
function helpsingle_drush_command() {
  $items = array();
  $items['helpsingle'] = array(
    'description' => 'Print help for a single command',
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'allow-additional-options' => TRUE,
    'hidden' => TRUE,
    'arguments' => array(
      'command' => 'A command name, or command alias.',
    ),
    'examples' => array(
      'drush help pm-download' => 'Show help for one command.',
      'drush help dl' => 'Show help for one command using an alias.',
    ),
    'topics' => array('docs-readme'),
  );
  return $items;
}

/**
 * Command callback. Show help for a single command.
 */
function drush_core_helpsingle($commandstring) {
  // First check and see if the command can already be found.
  $commands = drush_get_commands();
  if (!array_key_exists($commandstring, $commands)) {
    // If the command cannot be found, then bootstrap so that
    // additional commands will be brought in.
    // TODO: We need to do a full bootstrap in order to find module service
    // commands. We only need to do this for Drupal 8, though; 7 and earlier
    // can stop at DRUSH_BOOTSTRAP_DRUPAL_SITE. Perhaps we could use command
    // caching to avoid bootstrapping, if we have collected the commands for
    // this site once already.
    drush_bootstrap_max();
    $commands = drush_get_commands();
  }
  if (array_key_exists($commandstring, $commands)) {
    $command = $commands[$commandstring];

    annotationcommand_adapter_add_hook_options($command);

    drush_print_help($command);
    return TRUE;
  }
  $shell_aliases = drush_get_context('shell-aliases', array());
  if (array_key_exists($commandstring, $shell_aliases)) {
    $msg = dt("'@alias-name' is a shell alias.  Its value is: !name. See `drush topic docs-shell-aliases` and `drush shell-alias` for more information.", array('@alias-name' => $commandstring, '!name' => $shell_aliases[$commandstring]));
    drush_log($msg, 'ok');
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
  _drush_help_merge_subcommand_information($command);

  if (!$help = drush_command_invoke_all('drush_help', 'drush:'. $command['command'])) {
    $help = array($command['description']);
  }

  if ($command['strict-option-handling']) {
    $command['topics'][] = 'docs-strict-options';
  }

  // Give commandfiles an opportunity to add examples and options to the command.
  drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_SITE);
  drush_engine_add_help_topics($command);
  drush_command_invoke_all_ref('drush_help_alter', $command);

  drush_print(wordwrap(implode("\n", $help), drush_get_context('DRUSH_COLUMNS', 80)));
  drush_print();

  $global_options = drush_get_global_options();
  foreach ($command['global-options'] as $global_option) {
    $command['options'][$global_option] = $global_options[$global_option];
  }

  // Sort command options.
  uksort($command['options'], '_drush_help_sort_command_options');

  // Print command sections help.
  foreach ($command['sections'] as $key => $value) {
    if (!empty($command[$key])) {
      $rows = drush_format_help_section($command, $key);
      if ($rows) {
        drush_print(dt($value) . ':');
        drush_print_table($rows, FALSE, array('label' => 40));
        unset($rows);
        drush_print();
      }
    }
  }

  // Append aliases if any.
  if ($command['aliases']) {
    drush_print(dt("Aliases: ") . implode(', ', $command['aliases']));
  }
}

/**
 * Sort command options alphabetically. Engine options at the end.
 */
function _drush_help_sort_command_options($a, $b) {
  $engine_a = strpos($a, '=');
  $engine_b = strpos($b, '=');
  if ($engine_a && !$engine_b) {
    return 1;
  }
  else if (!$engine_a && $engine_b) {
    return -1;
  }
  elseif ($engine_a && $engine_b) {
    if (substr($a, 0, $engine_a) == substr($b, 0, $engine_b)) {
      return 0;
    }
  }
  return ($a < $b) ? -1 : 1;
}

/**
 * Check to see if the specified command contains an 'allow-additional-options'
 * record.  If it does, find the additional options that are allowed, and
 * add in the help text for the options of all of the sub-commands.
 */
function _drush_help_merge_subcommand_information(&$command) {
  // 'allow-additional-options' will either be FALSE (default),
  // TRUE ("allow anything"), or an array that lists subcommands
  // that are or may be called via drush_invoke by this command.
  if (is_array($command['allow-additional-options'])) {
    $implemented = drush_get_commands();
    foreach ($command['allow-additional-options'] as $subcommand_name) {
      if (array_key_exists($subcommand_name, $implemented)) {
        $command['options'] += $implemented[$subcommand_name]['options'];
        $command['sub-options'] = array_merge_recursive($command['sub-options'], $implemented[$subcommand_name]['sub-options']);
        if (empty($command['arguments'])) {
          $command['arguments'] = $implemented[$subcommand_name]['arguments'];
        }
        $command['topics'] = array_merge($command['topics'], $implemented[$subcommand_name]['topics']);
      }
    }
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
 *   Formatted rows, suitable for printing via drush_print_table. The returned
 *   array can be empty.
 */
function drush_format_help_section($command, $section) {
  $rows = array();
  $formatter = (function_exists('drush_help_section_formatter_' . $section)) ? 'drush_help_section_formatter_' . $section : 'drush_help_section_default_formatter';
  foreach ($command[$section] as $name => $help_attributes) {
    if (!is_array($help_attributes)) {
      $help_attributes = array('description' => $help_attributes);
    }
    $help_attributes['label'] = $name;
    call_user_func_array($formatter, array($command, &$help_attributes));
    if (empty($help_attributes['hidden'])) {
      $rows[] = array('label' => $help_attributes['label'], 'description' => $help_attributes['description']);
      // Process the subsections too, if any
      if (!empty($command['sub-' . $section]) && array_key_exists($name, $command['sub-' . $section])) {
        $rows = array_merge($rows, _drush_format_help_subsection($command, $section, $name, $formatter));
      }
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
  $rows = array();
  foreach ($command['sub-' . $section][$subsection] as $name => $help_attributes) {
    if (!is_array($help_attributes)) {
      $help_attributes = array('description' => $help_attributes);
    }
    $help_attributes['label'] = $name;
    call_user_func_array($formatter, array($command, &$help_attributes));
    if (!array_key_exists('hidden', $help_attributes)) {
      $rows[] = array('label' => $prefix . $help_attributes['label'], 'description' => $help_attributes['description']);
      // Process the subsections too, if any
      if (!empty($command['sub-' . $section]) && array_key_exists($name, $command['sub-' . $section])) {
        $rows = array_merge($rows, _drush_format_help_subsection($command, $section, $name, $formatter, $prefix . '  '));
      }
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
    drush_log(dt("Option '!option' of command !command should instead be declared as '!fixed'", array('!option' => $help_attributes['label'], '!command' => $command['command'], '!fixed' => preg_replace('/^--*/', '', $help_attributes['label']))), 'debug');
  }
  else {
    $help_attributes['label'] = '--' . $help_attributes['label'];
  }
  if (!empty($help_attributes['required'])) {
    $help_attributes['description'] .= " " . dt("Required.");
  }

  $prefix = '<';
  $suffix = '>';
  if (array_key_exists('example-value', $help_attributes)) {
    if (isset($help_attributes['value']) && $help_attributes['value'] == 'optional') {
      $prefix = '[';
      $suffix = ']';
    }
    $help_attributes['label'] .= '=' . $prefix . $help_attributes['example-value'] . $suffix;

    if (array_key_exists('short-form', $help_attributes)) {
      $help_attributes['short-form'] .= " $prefix" . $help_attributes['example-value'] . $suffix;
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
