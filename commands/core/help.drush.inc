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
 * For now, this can't move to helpsingle since help command is the entry point for both.
 *
 * @return
 *   Array of available command names.
 */
function core_help_complete() {
  return array('values' => array_keys(drush_get_commands()));
}

/**
 * Command callback for help command. This is the default command, when none
 * other has been specified.
 */
function drush_core_help() {
  if ($commands = func_get_args()) {
    // Send help for a single command.
    $options = drush_redispatch_get_options();
    if ($commands[0] != 'help') {
      unset($options['help']);
    }
    $return = drush_invoke_process('@self' ,'helpsingle', $commands, $options);
    drush_backend_set_result($return['object']);
    // drush_print($return['output']);
    return;
  }

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
