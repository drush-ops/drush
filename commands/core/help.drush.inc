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
    'allow-additional-options' => array('helpsingle'),
    'options' => array(
      'sort' => 'Sort commands in alphabetical order. Drush waits for full bootstrap before printing any commands when this option is used.',
      'filter' => array(
        'description' => 'Restrict command list to those commands defined in the specified file. Omit value to choose from a list of names.',
        'example-value' => 'category',
        'value' => 'optional',
      ),
    ),
    'arguments' => array(
      'command' => 'A command name, or command alias.',
    ),
    'examples' => array(
      'drush' => 'List all commands.',
      'drush --filter=devel_generate' => 'Show only commands defined in devel_generate.drush.inc',
      'drush help pm-download' => 'Show help for one command.',
      'drush help dl' => 'Show help for one command using an alias.',
      'drush help --format=html' => 'Show an HTML page detailing all available commands.',
      'drush help --format=json' => 'All available comamnds, in a machine parseable format.',
    ),
    // Use output format system for all formats except the default presentation.
    'outputformat' => array(
      'default' => 'table',
      'field-labels' => array('name' => 'Name', 'description' => 'Description'),
      'output-data-type' => 'format-table',
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
function drush_core_help($name = '') {
  $format = drush_get_option('format', 'table');
  if ($name) {
    // helpsingle command builds output when a command is specified.
    $options = drush_redispatch_get_options();
    if ($name != 'help') {
      unset($options['help']);
    }
    $return = drush_invoke_process('@self' ,'helpsingle', func_get_args(), $options);
    drush_backend_set_result($return['object']);
    return;
  }

  // For speed, only bootstrap up to DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION.
  drush_bootstrap_max();
  drush_get_commands(true);
  $implemented = drush_get_commands();
  ksort($implemented);
  $command_categories = drush_commands_categorize($implemented);
  if ($format != 'table') {
    return $command_categories;
  }
  else {
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

    // Make a fake command section to hold the global options, then print it.
    $global_options_help = drush_global_options_command(TRUE);
    if (!drush_get_option('filter')) {
      drush_print_help($global_options_help);
    }
    drush_help_listing_print($command_categories);
    drush_backend_set_result($command_categories);
    return;
  }
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
 * Print CLI table listing all commands.
 */
function drush_help_listing_print($command_categories) {
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
    drush_print($info['title'] . ": (" . $key . ")");
    drush_print_table($rows, FALSE, array('name' => 20));
  }
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
