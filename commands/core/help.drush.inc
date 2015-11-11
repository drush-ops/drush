<?php

/**
 * Implementation of hook_drush_help().
 *
 * This function is called whenever a drush user calls
 * 'drush help <name-of-your-command>'
 *
 * @param
 *   A string with the help section (prepend with 'drush:')
 *
 * @return
 *   A string with the help text for your command.
 */
function help_drush_help($section) {
  switch ($section) {
    case 'drush:help':
      return dt("Drush provides an extensive help system that describes both drush commands and topics of general interest.  Use `drush help --filter` to present a list of command categories to view, and `drush topic` for a list of topics that go more in-depth on how to use and extend drush.");
  }
}

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
function help_drush_command() {
  $items = array();

  $items['help'] = array(
    'description' => 'Print this help message. See `drush help help` for more options.',
    'bootstrap' => DRUSH_BOOTSTRAP_NONE,
    'allow-additional-options' => TRUE,
    'options' => array(
      'sort' => 'Sort commands in alphabetical order. Drush waits for full bootstrap before printing any commands when this option is used.',
      'filter' => array(
        'description' => 'Restrict command list to those commands defined in the specified file. Omit value to choose from a list of names.',
        'example-value' => 'category',
        'value' => 'optional',
      ),
      'format' => 'Format to output . Allowed values are: json, var_export, html.',
      'pipe' => 'A list of available commands, one per line.',
    ),
    'arguments' => array(
      'command' => 'A command name, or command alias.',
    ),
    'examples' => array(
      'drush' => 'List all commands.',
      'drush --filter=devel_generate' => 'Show only commands defined in devel_generate.drush.inc',
      'drush help pm-download' => 'Show help for one command.',
      'drush help dl' => 'Show help for one command using an alias.',
    ),
    'topics' => array('docs-readme'),
  );
  return $items;
}


/**
 * Command argument complete callback.
 *
 * @return
 *   Array of available command names.
 */
function core_help_complete() {
  return array('values' => array_keys(drush_get_commands()));
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

/**
 * Build a fake command for the purposes of showing examples and options.
 */
function drush_global_options_command($brief = FALSE) {
  $global_options_help = array(
    'description' => 'Execute a drush command. Run `drush help [command]` to view command-specific help.  Run `drush topic` to read even more documentation.',
    'sections' => array(
      'options' => 'Global options (see `drush topic core-global-options` for the full list)',
    ),
    'options' => drush_get_global_options($brief),
    'examples' => array(
      'drush dl cck zen' => 'Download CCK module and Zen theme.',
      'drush --uri=http://example.com status' => 'Show status command for the example.com multi-site.',
    ),
    '#brief' => TRUE,
  );
  $global_options_help += drush_command_defaults('global-options', 'global_options', __FILE__);
  drush_command_invoke_all_ref('drush_help_alter', $global_options_help);
  ksort($global_options_help['options']);

  return $global_options_help;
}

/**
 * Command callback for help command. This is the default command, when none
 * other has been specified.
 */
function drush_core_help() {
  $commands = func_get_args();
  $format = drush_get_option('format');

  if (empty($commands)) {
    // For speed, only bootstrap up to DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION.
    drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION);
    $implemented = drush_get_commands();
    ksort($implemented);
    $command_categories = drush_commands_categorize($implemented);
    $visible = drush_help_visible($command_categories);

    // If the user specified --filter w/out a value, then
    // present a choice list of help categories.
    if (drush_get_option('filter', FALSE) === TRUE) {
      $help_categories = array();
      foreach ($command_categories as $key => $info) {
        $description = $info['title'];
        if (array_key_exists('summary', $info)) {
          $description .= ": " . $info['summary'];
        }
        $help_categories[$key] = $description;
      }
      $result = drush_choice($help_categories, 'Select a help category:');
      if (!$result) {
        return drush_user_abort();
      }
      drush_set_option('filter', $result);
    }
    // Filter out categories that the user does not want to see
    $filter_category = drush_get_option('filter');
    if (!empty($filter_category) && ($filter_category !== TRUE)) {
      if (!array_key_exists($filter_category, $command_categories)) {
        return drush_set_error('DRUSH_NO_CATEGORY', dt("The specified command category !filter does not exist.", array('!filter' => $filter_category)));
      }
      $command_categories = array($filter_category => $command_categories[$filter_category]);
    }

    if ($format == 'html') {
      drush_print(drush_help_html_header());
    }

    // Make a fake command section to hold the global options, then print it.
    $global_options_help = drush_global_options_command(TRUE);
    if (!in_array($format, array('html', 'var_export', 'json')) && !drush_get_option('filter')) {
      drush_print_help($global_options_help);
    }

    if ($format == 'json' || $format == 'var_export') {
      // Print giant array, and set backend_result. We do it this way because this command
      // isn't yet fully compliant with outputformat system. To do so, we need to deal with
      // the fact that this command varies output significantly when showing help for one
      // command versus a listing. We also have oddball HTML format.
      drush_print(drush_format($command_categories, 'commands'));
      drush_backend_set_result($command_categories);
      return NULL;
    }
    else {
      // HTML and CLI command listing (in a table).
      drush_help_listing_print($command_categories, $format);

      if ($format == 'html') {
        // Print global options and long-form help for all commands.
        drush_print(drush_help_html_global_options($global_options_help));
        drush_print(drush_help_html($visible));
      }
    }

    // Newline-delimited list for use by other scripts. Set the --pipe option.
    if (drush_get_option('pipe')) {
      drush_print_pipe(implode("\n", array_keys($visible)));
    }
    return;
  }
  else {
    $result = TRUE;
    while ((count($commands) > 0) && !drush_get_error()) {
      $result =  drush_show_help(array_shift($commands));
    }
    return $result;
  }

  return drush_set_error('DRUSH_COMMAND_NOT_FOUND', dt('Invalid command !command.', array('!command' => implode(" ", $commands))));
}

// Uncategorize the list of commands. Hiddens have been removed and
// filtering performed.
function drush_help_visible($command_categories) {
  $all = array();
  foreach ($command_categories as $category => $info) {
    $all = array_merge($all,  $info['commands']);
  }
  return $all;
}

/**
 * Print CLI table or HTML table listing all commands.
 */
function drush_help_listing_print($command_categories, $format) {
  $all_commands = array();
  foreach ($command_categories as $key => $info) {
    // Get the commands in this category.
    $commands = $info['commands'];

    // Build rows for drush_print_table().
    $rows = array();
    foreach($commands as $cmd => $command) {
      $name = $command['aliases'] ? $cmd . ' (' . implode(', ', $command['aliases']) . ')': $cmd;
      $rows[$cmd] = array('name' => $name, 'description' => $command['description']);
    }

    // Vary the output by mode: CLI or HTML
    if ($format == 'html') {
      drush_print("<h3>" . $info['title'] . "</h3>");
      drush_print(drush_help_html_command_list($commands));
    }
    else {
      drush_print($info['title'] . ": (" . $key . ")");
      drush_print_table($rows, FALSE, array('name' => 20));
    }
  }
}

/**
 * Return an HTML page header.
 */
function drush_help_html_header() {
  return "<html><head><title>Drush help</title><style>dt {font-size: 110%; font-weight: bold}</style></head><body>\n";
}

function drush_help_html_global_options($global_options_help) {
  // Global options
  $global_option_rows = drush_format_help_section($global_options_help, 'options');
  $output =  '<h3>Global Options (see `drush topic core-global-options` for the full list)</h3><table>';
  foreach ($global_option_rows as $row) {
    $output .= "<tr>";
    foreach ($row as $value) {
      $output .=  "<td>" . htmlspecialchars($value) . "</td>\n";
    }
    $output .= "</tr>";
  }
  $output .=  "</table>\n";

  return $output;
}

function drush_help_html_command_list($commands) {
  // Command table
  $output =  "<table>\n";
  foreach ($commands as $key => $command) {
    $output .=  "  <tr><td><a href=\"#$key\">$key</a></td><td>" . $command['description'] . "</td></tr>\n";
  }
  $output .=  "</table>\n";

  return $output;
}

/**
 * Return an HTML page documenting all available commands and global options.
 */
function drush_help_html($commands) {
  // Command details
  $output =  '<h3>Command detail</h3><dl>';
  foreach ($commands as $key => $command) {
    $output .=  "\n<a name=\"$key\"></a><dt>$key</dt><dd><pre>\n";
    ob_start();
    drush_show_help($key);
    $output .= ob_get_clean();
    $output .=  "</pre></dd>\n";
  }

  $output .=  "</body></html>\n";

  return $output;
}
