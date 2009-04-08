<?php
// $Id$

/**
 * @file
 *   Core drush commands. See 'Drush Extras' project on drupal.org for more.
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
    'description' => 'Print this help message.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH, // No bootstrap.
  );
  $items['cron'] = array(
    'description' => 'Run all cron hooks.',
  );
  $items['status'] = array(
    'description' => 'Provides a birds-eye view of the current Drupal installation, if any.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
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
  $items['dl'] = array(
    'description' => 'Download core Drupal and modules like Drush Extras.',
    'examples' => array(
      'drush dl drupal' => 'Download latest version of Drupal core.', 
      'drush dl drupal-5.x' => 'Download latest version of Drupal core for 5.x release', 
      'drush dl diff-6.x-2.0' => 'Download a specfic version of diff module.',
      'drush dl views' => 'Download latest versions of Views for my version of Drupal.',
    ),
    'arguments' => array(
      'project' => '\'drupal\', or module name, with optional version.',
    ),
    'options' => array(
      '--destination' => 'Path to which the project will be copied.',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH, // No bootstrap at all.
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


/**
 * This is called if no command or an unknown command is entered.
 */
function core_help() {
  $commands = func_get_args();
  drush_set_context('DRUSH_SHOWING_HELP', TRUE);
  
  if (empty($commands)) {
    $phases = _drush_bootstrap_phases(); 
    drush_print(dt('Usage: drush.php [options] <command> <command> ...'));
    drush_print();
    drush_print('Run "drush help [command]" to view command-specific help.');
    drush_print(dt('Options: '));
    foreach (drush_get_option_help() as $option => $description) {
      $rows[] = array($option, $description);
    }

    drush_print_table($rows, 2);
    drush_print();
    drush_print('Commands: ');

    $printed_rows = array();
    $phase_index = DRUSH_BOOTSTRAP_DRUSH;

    foreach ($phases as $phase_index) {
      if ($phase_index > drush_get_context('DRUSH_BOOTSTRAP_PHASE')) {
        drush_bootstrap($phase_index);
      }
      if (!drush_get_error()) {
        $commands = drush_get_commands();
        $rows = array();
        foreach($commands as $key => $command) {
          if (!array_key_exists($key, $printed_rows)) {
            $rows[$key] = array(sprintf("%-20s", $key), $commands[$key]['description']);
          }
        }
        drush_print_table($rows, 2);
        $printed_rows = array_merge($printed_rows, $rows);
      }
      else {
        break; 
      }
    }
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
    case 'drush:cron':
      return dt("Runs all cron hooks in all active modules for specified site.");
    case 'drush:status':
      return dt("View the Drupal version and DB credentials for the current site.");
    case 'drush:script':
      return dt("Runs the given php script(s) against the current Drupal site.");
    case 'drush:watchdog show':
      return dt("Show recent watchdog messages.");
    case 'drush:watchdog delete':
      return dt("Delete watchdog messages.");
    case 'drush:sync':
      return dt("Sync the entire drupal directory or a subdirectory to a <destination> using ssh. Excludes .svn directories. Useful for pushing copies of your tree to a staging server, or retrieving a files directory from a remote site. Local paths should be specified relative to Drupal root.");
    case 'drush:eval':
      return dt("Run arbitrary PHP code in the context of Drupal");
    case 'drush:dl':
      return dt("Quickly download projects from drupal.org. Automatically figures out which module version you want based on its latest release, or you may specify a particular version. Downloads drupal core as well.  If no destination is provided, defaults to a site specific modules directory if available, then to sites/all/modules if available, then to the current working directory.");
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
  if ($drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT')) {
    $credentials .= sprintf("  %-18s: %s\n", 'Drupal Root', $drupal_root);
    $credentials .= sprintf("  %-18s: %s\n", 'Drupal version', drush_drupal_major_version());
    if ($site_root = drush_get_context('DRUSH_DRUPAL_SITE_ROOT')) {
      $credentials .= sprintf("  %-18s: %s\n", 'Site Path', $site_root);
      $credentials .= sprintf("  %-18s: %s\n", 'Site URI', drush_get_context('DRUSH_URI'));
      $db_driver = 'not defined';
      $db_host = 'not defined';
      $db_user = 'not defined';
      $db_name = 'not defined';
      switch (drush_drupal_major_version()) {
         case 5:
         case 6:
           if (isset($GLOBALS['db_url'])) {
             $parts = parse_url($GLOBALS['db_url']);
             $db_driver = $parts['scheme'];
             $db_user = $parts['user'];
             $db_host = $parts['host'];
             $db_name = trim($parts['path'], '/');
           }
           break;
         case 7:
           if (isset($GLOBALS['databases']['default'])) {
             $conn = $GLOBALS['databases']['default'];
             $db_driver = $conn['driver'];
             $db_user = $conn['username'];
             $db_host = $conn['host'];
             $db_name = $conn['database'];
           }
           break;
      }
      $credentials .= sprintf("  %-18s: %s\n", 'Database Driver', $db_driver);
      $credentials .= sprintf("  %-18s: %s\n", 'Database Hostname', $db_host);
      $credentials .= sprintf("  %-18s: %s\n", 'Database Username', $db_user);
      $credentials .= sprintf("  %-18s: %s\n", 'Database Name', $db_name);
      return $credentials;
    }
  }
  return null;
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
function core_cron() {
  drupal_cron_run();
  drush_print(dt('Cron run successfully.'));
}

/**
 * Command callback. Provides a birds-eye view of the current Drupal
 * installation.
 */
function core_status() {
  if ($drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT')) {
    drush_print(dt("Drupal !version is installed at !DRUSH_DRUPAL_ROOT.", array('!version' => drush_drupal_version(), '!DRUSH_DRUPAL_ROOT' => $drupal_root)));
    if ($drush_uri = drush_get_context('DRUSH_URI')) {
      drush_print(dt("Current site: !DRUSH_URI", array('!DRUSH_URI' => $drush_uri)));
    }
    else {
      drush_print(dt("No site uri was determined. Using default site path."));
    }
    if (drush_get_context('DRUSH_BOOTSTRAP_PHASE') >= DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION) {
      switch (drush_drupal_major_version()) {
        case 5:
        case 6:
          drush_print(dt("Current database: !db_url", array('!db_url' => $GLOBALS['db_url'])));
          break;
        case 7:
          $db_conn = Database::getConnectionInfo();
          drush_print(dt("Current database:"));
          foreach($db_conn['default'] as $key => $value) {
            drush_print(dt("  !key => !value", array('!key' => $key, '!value' => $value)));
          }
          break;
        default:
          drush_print(dt("No database information available."));
          break;
      }
    }
    else {
      drush_print(dt("Site not found or not working."));
    }
  }
  else {
    drush_print(dt("No Drupal installation was found."));
  }
}

/**
 * Command callback. Runs "naked" php scripts.
 */
function core_script() {
  $scripts = func_get_args();
  foreach ($scripts as $script) {
    $script_filename = drush_cwd() . '/' . $script;
    if (file_exists($script_filename)) {
      include($script_filename);
    }
  }
}

function core_cache_clear() {
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
function core_sync($source, $destination) {
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
  if (drush_get_context('DRUSH_VERBOSE')) {
    // the drush_op() will be verbose about the command that gets executed.
    $options .= 'v';
  }

  return drush_op('system', $exec) !== FALSE;
}

/**
 * Displays the most recent watchdog log messages (default: 10 messages).
 */
function core_watchdog_show($type = NULL) {
  $severities = array(WATCHDOG_NOTICE => dt('notice'), WATCHDOG_WARNING => dt('warning'), WATCHDOG_ERROR => dt('error'));
  $limit = drush_get_option('limit') ? drush_get_option('limit') : 10;

  $sql = 'SELECT w.*, u.name, u.uid FROM {watchdog} w INNER JOIN {users} u ON w.uid = u.uid';
  $sort = ' ORDER BY w.wid DESC';
  if (empty($type)) {
    $result = db_query_range($sql, 0, (int)$limit);
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
    drush_verbose(dt('Last !count watchdog log messages:', array('!count' => $limit)));

    array_unshift($rows, array(dt('Date'), dt('Severity'), dt('Type'), dt('Message'), dt('User')));
    drush_print_table($rows, 2, TRUE);
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
function core_watchdog_delete($type = NULL) {
  if ($type == "all") {
    // D7: ought to be a dynamic query.
    drush_op('db_query', 'DELETE FROM {watchdog}'); // indiscriminately delete all
    drush_log(dt('Deleted !n rows.', array('!n' => db_affected_rows())), 'ok');
  }
  elseif (!empty($type)) {
    drush_op('db_query', 'DELETE FROM {watchdog} WHERE type = \'%s\'', $type);
    drush_log(dt('Deleted !n rows.', array('!n' => db_affected_rows())), 'ok');
  }
  else {
    drush_set_error(dt('Please specify a message type, or "all" to delete all messages.'));
  }
}

function core_eval($command) {
  eval($command);
}
