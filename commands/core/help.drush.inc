<?php

/**
 * Command callback for help command. This is the default command, when none
 * other has been specified.
 */
function drush_core_help() {
  $commands = func_get_args();


  if (empty($commands)) {
    // Build a fake command for the purposes of showing examples and options.
    $fake = array(
      'description' => 'Execute a drush command. Run `drush help [command]` to view command-specific help.  Run `drush topic` to read even more documentation.',
      'sections' => array('options' => dt('Global options (see `drush topic` for the full list)')),
      'options' => drush_get_global_options(TRUE),
      'examples' => array(
        'drush dl cck zen' => 'Download CCK module and Zen theme.',
        'drush --uri=http://example.com status' => 'Show status command for the example.com multi-site.',
      ),
    );
    $fake += drush_command_defaults('fake', 'fake', __FILE__);
    if (!drush_get_option('html')) {
      drush_print_help($fake);
    }

    if (!drush_get_option('html')) {
      drush_print(dt('Commands: '));
    }

    $phases = _drush_bootstrap_phases();
    // For speed, only bootstrap up to DRUSH_BOOTSTRAP_DRUPAL_SITE+1.
    $phases = array_slice($phases, 0, DRUSH_BOOTSTRAP_DRUPAL_SITE+1);

    $printed_rows = $all_rows = array();
    $phase_index = DRUSH_BOOTSTRAP_DRUSH;

    foreach ($phases as $phase_index) {
      if (drush_bootstrap_validate($phase_index)) {
        if ($phase_index > drush_get_context('DRUSH_BOOTSTRAP_PHASE')) {
          drush_bootstrap($phase_index);
        }

        $commands = drush_get_commands();
        // Filter by command file if specified.
        if ($commandfile = drush_get_option('filter')) {
          foreach ($commands as $key => $candidate) {
            if ($candidate['commandfile'] != $commandfile) {
              unset($commands[$key]);
            }
          }
        }

        $rows = array();
        ksort($commands);
        foreach($commands as $key => $command) {
          if (!$command['hidden']) {
            if (!array_key_exists('is_alias', $command) || !$command['is_alias']) {
              if (!array_key_exists($key, $printed_rows)) {
                $name = $command['aliases'] ? $key . ' (' . implode(', ', $command['aliases']) . ')': $key;
                $rows[$key] = array($name, $command['description']);
                $all_rows[$key] = array($name, $command['description']);
                $pipe[] = "\"$key\"";
              }
            }
          }
          else {
            // Remove hidden command.
            unset($commands[$key]);
          }
        }

        // These two options require delayed printing.
        if (!empty($rows)) {
          if (!drush_get_option('sort') && !drush_get_option('html')) {
            drush_print_table($rows, FALSE, array(0 => 20));
          }
          $printed_rows = array_merge($printed_rows, $rows);
        }
      }
      else {
        break;
      }
    }

    // We delayed printing until now.
    ksort($all_rows);
    if (drush_get_option('sort')) {
      drush_print_table($all_rows, FALSE, array(0 => 20));
    }
    elseif (drush_get_option('html')) {
      return drush_print(drush_help_html($commands));
    }

    // Newline-delimited list for use by other scripts. Set the --pipe option.
    if (drush_get_option('pipe') && isset($pipe)) {
      drush_print_pipe($pipe);
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


/**
 * Return an HTML page documenting all available commands and global options.
 */
function drush_help_html($commands) {
  $output =  "<html><head><title>drush help</title><style>dt {font-size: 110%; font-weight: bold}</style></head><body>\n";

  // Command table
  $output .=  "<h3>Command list</h3>\n<table>\n";
  foreach ($commands as $key => $command) {
    $output .=  "  <tr><td><a href=\"#$key\">$key</a></td><td>" . $command['description'] . "</td></tr>\n";
  }
  $output .=  "</table>\n";

  // Global options
  $options = drush_get_global_options(TRUE);
  $output .=  '<h3>Global Options (see `drush topic` for the full list)</h3><table>';
  foreach ($options as $key => $value) {
    $output .=  "<tr><td>$key</td><td>" . $value . "</td></tr>\n";
  }
  $output .=  "</table>\n";

  // Command details
  $output .=  '<h3>Command detail</h3><dl>';
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