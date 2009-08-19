<?php
// $Id$

/**
 * @file
 *   Core drush commands.
 */

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
function core_drush_command() {
  $items = array();

  $items['help'] = array(
    'description' => 'Print this help message. Use --filter to limit command list to one command file (e.g. --filter=pm)',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH, // No bootstrap.
    'options' => drush_get_option_help(),
    'examples' => array(
      'drush dl cck zen' => 'Download CCK module and Zen theme.',
      'drush --uri=http://example.com status' => 'Show status command for the example.com multi-site.',
      'drush help --pipe' => 'A space delimited list of commands',
    ),
  );
  $items['cron'] = array(
    'description' => 'Run all cron hooks.',
  );
  $items['updatedb'] = array(
    'description' => dt('Execute the update.php process from the command line'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
  );
  $items['status'] = array(
    'description' => 'Provides a birds-eye view of the current Drupal installation, if any.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );
  $items['script'] = array(
    'description' => "Run php script(s).",
    'examples' => array(
      'drush script update_variables.php' => 'Run variables.php script',
    ),
    'arguments' => array(
      'path/to/script' => 'One or more file paths. Paths may be absolute or relative to the current working dir.',
    ),
  );
  $items['cache clear'] = array(
    'description' => 'Clear all caches.'
  );
  $items['watchdog show'] = array(
    'description' => 'Shows recent watchdog log messages. Optionally filter for a specific type.',
    'drupal dependencies' => drush_drupal_major_version() >= 6 ? array('dblog') : array('watchdog'),
    'arguments' => array(
      'type' => 'The type of messages to show. Defaults to all.',
    ),
    'options' => array(
      '--limit' => 'The number of messages to show. Defaults to 10.',
    ),
    'examples' => array(
      'watchdog show cron' => 'Show recent cron watchdog messages.',
      'watchdog show --limit 50' => 'Show 50 recent watchdog messages.',
    ),
  );
  $items['watchdog delete'] = array(
    'description' => 'Delete all messages or only those of a specified type.',
    'arguments' => array(
      'type' => 'The type of messages to delete. Use \'all.\' to do a complete wipe.',
    ),
    'drupal dependencies' => drush_drupal_major_version() >= 6 ? array('dblog') : array('watchdog'),
    'examples' => array(
      'watchdog delete all' => 'Delete all watchdog messages.',
      'watchdog delete cron' => 'Delete all cron watchdog messages.',
    ),
    
  );
  $items['sync'] = array(
    'description' => 'Rsync the Drupal tree to/from another server using ssh.',
    'arguments' => array(
      'source' => 'See rsync documentation.',
      'destination' => 'See rsync documentation.',
    ),
    'examples' => array(
      'sync dev:/var/www/files/ stage:/var/www/files/' => 'Rsync \'files\' dir from dev to stage',
    ),
  );
  $items['eval'] = array(
    'description' => 'Evaluate arbitrary php code after bootstrapping Drupal.',
    'examples' => array(
      'drush eval \"variable_set(\'hello\', \'world\');\"' => 'Sets the hello variable using Drupal API.',
    ),
    'arguments' => array(
      'code' => 'PHP code',
    ),
  );
  return $items;
}

function core_drush_engine_drupal() {
  $engines = array();
  $engines['update'] = array();
  $engines['environment'] = array();
  return $engines;
}

/**
 * Command handler. Execute update.php code from drush.
 */
function drush_core_updatedb() {
  drush_include_engine('drupal', 'update', drush_drupal_major_version());
  update_main();
  drush_log(dt('Finished performing updates.'), 'ok');
}


/**
 * This is called if no command or an unknown command is entered.
 */
function drush_core_help() {
  $commands = func_get_args();
  
  if (empty($commands)) {
    drush_show_help(array('help'));
    $phases = _drush_bootstrap_phases();
    // For speed, only bootstrap up to DRUSH_BOOTSTRAP_DRUPAL_SITE.
    $phases = array_slice($phases, 0, DRUSH_BOOTSTRAP_DRUPAL_SITE+1);
    drush_print(dt('Commands: '));

    $printed_rows = array();
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
        foreach($commands as $key => $command) {
          if (!array_key_exists($key, $printed_rows)) {
            $rows[$key] = array($key, $commands[$key]['description']);
            $pipe[] = "\"$key\"";
          }
        }
        drush_print_table($rows, FALSE, array(0 => 20));
        $printed_rows = array_merge($printed_rows, $rows);
      }
      else {
        break; 
      }
    }
    
    // Space delimited list for use by other scripts. Set the --pipe option.
    drush_print_pipe(implode(' ', $pipe));
    return;
  }
  else {
    return drush_show_help($commands);
  }

  drush_set_error('DRUSH_COMMAND_NOT_FOUND', dt('Invalid command !command.', array('!command' => implode(" ", $commands))));
}


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
function core_drush_help($section) {
  switch ($section) {
    case 'drush:help':
      return dt('Execute a drush command. Run `drush help [command]` to view command-specific help.');
    case 'drush:cron':
      return dt("Runs all cron hooks in all active modules for specified site.");
    case 'drush:status':
      return dt("View the Drupal version and DB credentials for the current site.");
    case 'drush:script':
      return dt("Runs the given php script(s) after a full Drupal bootstrap. A useful alternative to eval command when your php is lengthy or you can't be bothered to figure out bash quoting.");
    case 'drush:watchdog show':
      return dt("Show recent watchdog messages.");
    case 'drush:watchdog delete':
      return dt("Delete watchdog messages.");
    case 'drush:updatedb':
      return dt("Run update.php just as a web browser would.");
    case 'drush:sync':
      return dt("Sync the entire drupal directory or a subdirectory to a <destination> using ssh. Excludes .svn directories. Useful for pushing copies of your tree to a staging server, or retrieving a files directory from a remote site. Local paths should be specified relative to Drupal root.");
    case 'drush:eval':
      return dt("Run arbitrary PHP code in the context of Drupal");
    case 'error:DRUSH_DRUPAL_DB_ERROR' : 
      $message = dt("Drush was not able to start (bootstrap) the Drupal database.\n");
      $message .= dt("Hint: This error often occurs when Drush is trying to bootstrap a site that has not been installed or does not have a configured database.\n");
      $message .= dt("\nDrush was attempting to connect to : \n!credentials\n", array('!credentials' => _core_site_credentials()));
      $message .= dt("You can select another site with a working database setup by specifying the URI to use with the --uri parameter on the command line or \$options['uri'] in your drushrc.php file.\n");
      return $message;
    case 'error:DRUSH_DRUPAL_BOOTSTRAP_ERROR' :
      $message = dt("Drush was not able to start (bootstrap) Drupal.\n");
      $message .= dt("Hint: This error can only occur once the database connection has already been succesfully initiated, therefor this error generally points to a site configuration issue, and not a problem connecting to the database.\n");
      $message .= dt("\nDrush was attempting to connect to : \n!credentials\n", array('!credentials' => _core_site_credentials()));
      $message .= dt("You can select another site with a working database setup by specifying the URI to use with the --uri parameter on the command line or \$options['uri'] in your drushrc.php file.\n");
      return $message;
      break;
  }
}

// TODO: consolidate with SQL commands?
function _core_site_credentials() {
  $phase = drush_get_context('DRUSH_BOOTSTRAP_PHASE');
  if (function_exists('php_ini_loaded_file')) {
    // Function available on PHP >= 5.2.4, but we use it if available to help
    // users figure out their php.ini issues.
    $credentials = sprintf("  %-18s: %s\n", 'PHP configuration', php_ini_loaded_file());
  }
  if ($drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT')) {
    $credentials .= sprintf("  %-18s: %s\n", 'Drupal Root', $drupal_root);
    $credentials .= sprintf("  %-18s: %s\n", 'Drupal version', drush_drupal_version());
    if ($site_root = drush_get_context('DRUSH_DRUPAL_SITE_ROOT')) {
      $credentials .= sprintf("  %-18s: %s\n", 'Site Path', $site_root);
      $credentials .= sprintf("  %-18s: %s\n", 'Site URI', drush_get_context('DRUSH_URI'));
      if ($creds = drush_get_context('DRUSH_DB_CREDENTIALS')) {
        $credentials .= sprintf("  %-18s: %s\n", 'Database Driver', $creds['driver']);
        $credentials .= sprintf("  %-18s: %s\n", 'Database Hostname', $creds['host']);
        $credentials .= sprintf("  %-18s: %s\n", 'Database Username', $creds['user']);
        $credentials .= sprintf("  %-18s: %s\n", 'Database Name', $creds['name']);
        $credentials .= sprintf("  %-18s: %s\n", 'Database Password', $creds['pass']);
        if ($phase > DRUSH_BOOTSTRAP_DRUPAL_DATABASE) {
          $credentials .= sprintf("  %-18s: %s\n", 'Database', dt('Connected'));
          if ($phase > DRUSH_BOOTSTRAP_DRUPAL_FULL) {
            $credentials .= sprintf("  %-18s: %s\n", 'Drupal Bootstrap', dt('Successful'));
            if ($phase == DRUSH_BOOTSTRAP_DRUPAL_LOGIN) {
              global $user;
              $username =  ($user->uid) ? $user->name : dt('Anonymous');
              $credentials .= sprintf("  %-18s: %s\n", 'Drupal User', $username);
            }
          }
        }
      }
    }
    return $credentials;
  }
  return dt("Could not find a valid Drupal installation\n");
}

/**
 * Command callback. Runs cron hooks.
 *
 * This is where the action takes place.
 *
 * In this function, all of Drupals API is (usually) available, including
 * any functions you have added in your own modules/themes.
 *
 * To print something to the terminal window, use drush_print().
 *
 */
function drush_core_cron() {
  drupal_cron_run();
  drush_print(dt('Cron run successfully.'));
}

/**
 * Command callback. Provides a birds-eye view of the current Drupal
 * installation.
 */
function drush_core_status() {
  drush_bootstrap_max();
  print _core_site_credentials();
  return;
}

/**
 * Command callback. Runs "naked" php scripts.
 */
function drush_core_script() {
  $scripts = func_get_args();
  foreach ($scripts as $script) {
    $script_filename = drush_cwd() . '/' . $script;
    if (file_exists($script_filename)) {
      include($script_filename);
    }
  }
}

function drush_core_cache_clear() {
  switch (drush_drupal_major_version()) {
    case 5:
      // clear preprocessor cache
      drupal_clear_css_cache();

      // clear core tables
      $core = array('cache', 'cache_filter', 'cache_menu', 'cache_page');
      $alltables = array_merge($core, module_invoke_all('devel_caches'));
      foreach ($alltables as $table) {
        cache_clear_all('*', $table, TRUE);
      }
      drush_print(dt('Cache cleared.'));
      break;
    case 6:
    case 7:
    default:
      drupal_flush_all_caches();
      drush_print(dt('Cache cleared.'));
      break;
  }
}

/**
 * Push files from or to the local Drupal install using SSH and RSync
 *
 * @return void
 **/
function drush_core_sync($source, $destination) {
  // Local paths are relative to Drupal root
  $drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT');
  
  if (!strstr($source, ':')) {
    $source = $drupal_root. "/$source";
  }
  if (!strstr($destination, ':')) {
    $destination = $drupal_root . "/$destination";
  }

  // Prompt for confirmation. This is destructive.
  if (!drush_get_context('DRUSH_SIMULATE')) {
    drush_print(dt("You will destroy data from !target and replace with data from !source", array('!source' => $source, '!target' => $destination)));
    if (!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_set_error('CORE_SYNC_ABORT', 'Aborting.');
    }
  }

  $options = '-az';
  $exec = "rsync -e ssh $options --exclude \"*.svn*\" $source $destination";
  if (drush_get_context('drush_log')) {
    // the drush_op() will be verbose about the command that gets executed.
    $options .= 'v';
  }

  return drush_op('system', $exec) !== FALSE;
}

/**
 * Displays the most recent watchdog log messages (default: 10 messages).
 */
function drush_core_watchdog_show($type = NULL) {
  $severities = array(WATCHDOG_NOTICE => dt('notice'), WATCHDOG_WARNING => dt('warning'), WATCHDOG_ERROR => dt('error'));
  $limit = drush_get_option('limit') ? drush_get_option('limit') : 10;

  $sql = 'SELECT w.*, u.name, u.uid FROM {watchdog} w INNER JOIN {users} u ON w.uid = u.uid';
  $sort = ' ORDER BY w.wid DESC';
  if (empty($type)) {
    $result = db_query_range($sql . $sort, 0, (int)$limit);
  }
  else {
    switch (drush_drupal_major_version()) {
      case 5:
      case 6:
        $result = db_query_range($sql . " WHERE w.type = '%s'" . $sort, $type, 0, $limit);
        break;
      default:
        $result = db_query_range($sql . " WHERE w.type = :type" . $sort, array(':type' => $type), 0, $limit);
    }
  }

  $rows = array();
  // module_load_include('inc', 'dblog', 'dblog.admin');
  while ($watchdog = db_fetch_object($result)) {
    $rows[] = array(
      format_date($watchdog->timestamp, 'small'),
      $severities[$watchdog->severity],
      dt($watchdog->type),
      core_watchdog_format_message($watchdog),
      theme('username', $watchdog),
    );
  }

  if (count($rows) == 0) {
    return drush_set_error('CORE_WATCHDOG_SHOW_NONE', 'No log messages available.');
  }
  else {
    drush_log(dt('Last !count watchdog log messages:', array('!count' => $limit)));

    array_unshift($rows, array(dt('Date'), dt('Severity'), dt('Type'), dt('Message'), dt('User')));
    drush_print_table($rows, TRUE);
  }
}

function core_watchdog_format_message($watchdog) {
  if (drush_drupal_major_version() == 5) {
    $message = $watchdog->message;
  }
  else {
    $variables = unserialize($watchdog->variables);
    $message = is_array($variables) ? strtr($watchdog->message, $variables) : $watchdog->message;
  }
  return truncate_utf8(decode_entities($message), 68, FALSE, FALSE);
}

/**
 * Deletes all log messages of a certain type from the watchdog log
 * (default: all).
 */
function drush_core_watchdog_delete($type = NULL) {
  if ($type == "all") {
    // D7: ought to be a dynamic query.
    drush_op('db_query', 'DELETE FROM {watchdog}'); // Indiscriminately delete all
    drush_log(dt('Deleted all rows.'), 'ok');
  }
  elseif (!empty($type)) {
    drush_op('db_query', 'DELETE FROM {watchdog} WHERE type = \'%s\'', $type);
    drush_log(dt('Deleted all rows.'), 'ok');
  }
  else {
    drush_set_error(dt('Please specify a message type, or "all" to delete all messages.'));
  }
}

function drush_core_eval($command) {
  eval($command . ';');
}
