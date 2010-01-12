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
  $items['hook'] = array(
    'description' => 'List implementations of a given hook and explore source of specified one.',
    'arguments' => array(
      'hook' => 'The name of the hook to explore.'
    )
  );
  $items['updatedb'] = array(
    'description' => dt('Execute the update.php process from the command line'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
    'aliases' => array('updb'),
  );
  $items['status'] = array(
    'description' => 'Provides a birds-eye view of the current Drupal installation, if any.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'aliases' => array('st'),
  );
  $items['script'] = array(
    'description' => "Run php script(s).",
    'examples' => array(
      'drush script scratch.php' => 'Run scratch.php script. See commands/core directory.',
      'drush script example.php --script-path=/path/to/scripts:/another/path' => 'Run script from specified paths',
      'drush script' => 'List all available scripts.',
    ),
    'arguments' => array(
      'filename' => 'Optional. The file you wish to execute. If omitted, list files ending in .php in the current working directory and specified script-path. Some might not be real drush scripts. Beware.',
    ),
    'options' => array(
      '--script-path' => "Additional paths to search for scripts.  Use POSIX path separator (':') for multiple paths.",
    ),
  );
  $items['cache-clear'] = array(
    'description' => 'Clear a specific cache, or all drupal caches.',
    'arguments' => array(
      'type' => 'The particular cache to clear. Omit this argument to choose from available caches.',
    ),
    'aliases' => array('cc'),
  );
  $items['search-status'] = array(
    'description' => 'Show how many items remain to be indexed out of the total.',
    'drupal dependencies' => array('search'),
    'options' => array(
      '--pipe' => 'Display in the format remaining/total for processing by scripts.',
    ),
  );
  $items['search-index'] = array(
    'description' => 'Index the remaining search items without wiping the index.',
    'drupal dependencies' => array('search'),
  );
  $items['search-reindex'] = array(
    'description' => 'Force the search index to be rebuilt.',
    'drupal dependencies' => array('search'),
    'options' => array(
      '--immediate' => 'Rebuild the index immediately, instead of waiting for cron.',
    ),
  );
  $items['rsync'] = array(
    'description' => 'Rsync the Drupal tree to/from another server using ssh.  Relative paths start from the Drupal root folder if a site alias is used; otherwise they start from the current working directory.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
    'arguments' => array(
      'source' => 'May be rsync path or site alias. See rsync documentation and example.drushrc.php.',
      'destination' => 'May be rsync path or site alias. See rsync documentation and example.drushrc.php.',
    ),
    'options' => array(
      '--mode' => 'The unary flags to pass to rsync; --mode=rultz implies rsync -rultz.  Default is -az.',
      '--RSYNC-FLAG' => 'Most rsync flags passed to drush sync will be passed on to rsync.  See rsync documentation.',
      '--all-paths' => 'If <source> and <destination> are site aliases, and neither <source> nor <destination> specifies a path component, then rsync all path aliases that are defined in both the source and destination alias.',
      '--exclude-conf' => 'Implies rsync --exclude="settings.php" --exclude="robots.txt" --exclude=".htaccess".  Default.',
      '--include-conf' => 'Allow settings.php, robots.txt and .htaccess to be rsynced',
      '--exclude-sites' => 'Exclude all directories in "sites/" except for "sites/all".',
    ),
    'examples' => array(
      'rsync dev stage' => 'Rsync Drupal root from dev to stage (one of which must be local).',
      'rsync --all-paths --exclude-conf dev stage' => 'Rsync Drupal root and all other paths from dev to stage for every path alias defined in both dev and stage.',
      'rsync ./ stage:!files/img' => 'Rsync all files in the current directory to the \'img\' directory in the file storage folder on stage.',
    ),
    'deprecated-aliases' => array('sync'),
  );
  $items['eval'] = array(
    'description' => 'Evaluate arbitrary php code after bootstrapping Drupal.',
    'examples' => array(
      'drush eval "variable_set(\'hello\', \'world\');"' => 'Sets the hello variable using Drupal API.',
    ),
    'arguments' => array(
      'code' => 'PHP code',
    ),
  );
  $items['installsite'] = array(
    'description' => 'Install Drupal along with modules/themes/configuration using the specified install profile.',
    'arguments' => array(
      'profile' => 'the install profile you wish to run. defaults to \'default\'',
    ),
    'options' => array(
      'db-url' => 'A Drupal 5/6 style database URL. Only required for initial install - not re-install.',
      'db-prefix' => 'An optional table prefix to use for initial install.',
      'account-name' => 'uid1 name. defaults to admin',
      'account-pass' => 'uid1 pass. defaults to admin',
      'account-mail' => 'uid1 email. defaults to admin@example.com',
      'locale' => 'A short language code. Sets the default site language. Language files must already be present. You may use download command to get them.',
      'clean-url'=> 'Defaults to 1',
      'site-name' => 'Defaults to installsite',
      'site-mail' => 'From: for system mailings. Defaults to admin@example.com',
      'sites-subdir' => "Name of directory under 'sites' which should be created if needed. Defaults to 'default'",
    ),
    'examples' => array(
      'installsite expert --locale=uk' => '(Re)install using the expert install profile. Set default language to Ukranian.',
      'installsite --db-url=mysql://root:pass@localhost:port/dbname ' => 'Install using the specified DB params.',
      'installsite --account-user=joe --account-pass=mom' => 'Re-install with specified uid1 credentials.',
    ),
    'core' => array(7),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_ROOT,
    'aliases' => array('is'),
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
  if (drush_get_context('DRUSH_SIMULATE')) {
    return drush_set_error(dt('updatedb command does not support --simulate option.'));
  }

  drush_include_engine('drupal', 'update', drush_drupal_major_version());
  update_main();
  drush_log(dt('Finished performing updates.'), 'ok');
}

/**
 * Command handler. Show hook implementations
 */
function drush_core_hook($hook) {
  $hook_implementations = module_implements($hook);
  if ($choice = drush_choice(array_combine($hook_implementations, $hook_implementations), 'Enter the number of the hook implementation you wish to view.')) {
    $func = new ReflectionFunction($choice . "_$hook");
    drush_print(dt("// file: !file, lines !startline-!endline", array('!file' => $func->getFileName(), '!startline' => $func->getStartLine(), '!endline' => $func->getEndLine())));
    // TODO: Do this in PHP for better compatibility. Help wanted.
    $exec = sprintf('sed -n %d,%dp %s', $func->getStartLine(), $func->getEndLine(), $func->getFileName());
    $return = drush_op('system', $exec);
    return $return;
  }
}

/**
 * This is called if no command or an unknown command is entered.
 */
function drush_core_help() {
  $commands = func_get_args();

  if (empty($commands)) {
    drush_show_help(array('help'));
    $phases = _drush_bootstrap_phases();
    // For speed, only bootstrap up to DRUSH_BOOTSTRAP_DRUPAL_SITE+1.
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
          if (!array_key_exists('is_alias', $command) || !$command['is_alias']) {
            if (!array_key_exists($key, $printed_rows)) {
              $name = $command['aliases'] ? $key . ' (' . implode(', ', $command['aliases']) . ')': $key;
              $rows[$key] = array($name, $command['description']);
              $pipe[] = "\"$key\"";
            }
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
    case 'drush:hook':
      return dt("List hook implementations for a given hook and get more detail on a given implementation.");
    case 'drush:status':
      return dt("View the Drupal version and DB credentials for the current site.");
    case 'drush:script':
      return dt("Runs the given php script(s) after a full Drupal bootstrap. A useful alternative to eval command when your php is lengthy or you can't be bothered to figure out bash quoting. if you plan to share a script with others, consider making a full drush command instead since thats more self-documenting.");
    case 'drush:cache-clear':
      return dt("Delete a specific drupal cache, or all caches.");
    case 'drush:search-status':
      return dt("Show how many items remain to be indexed for search, and the total number of items.");
    case 'drush:search-index':
      return dt("Index the remaining search items.");
    case 'drush:search-reindex':
      return dt("Force the search index to be rebuilt.");
    case 'drush:updatedb':
      return dt("Run update.php just as a web browser would.");
    case 'drush:rsync':
      return dt("Sync the entire drupal directory or a subdirectory to a <destination> using ssh. Excludes .svn directories. Useful for pushing copies of your tree to a staging server, or retrieving a files directory from a remote site. Local paths should be specified relative to Drupal root.");
    case 'drush:eval':
      return dt("Run arbitrary PHP code in the context of Drupal");
    case 'drush:installsite':
      return dt("Install Drupal using specified install profile.");
    case 'error:DRUSH_DRUPAL_DB_ERROR' :
      $message = dt("Drush was not able to start (bootstrap) the Drupal database.\n");
      $message .= dt("Hint: This error often occurs when Drush is trying to bootstrap a site that has not been installed or does not have a configured database.\n");
      $message .= dt("\nDrush was attempting to connect to : \n!credentials\n", array('!credentials' => _core_site_credentials()));
      $message .= dt("You can select another site with a working database setup by specifying the URI to use with the --uri parameter on the command line or \$options['uri'] in your drushrc.php file.\n");
      return $message;
    case 'error:DRUSH_DRUPAL_BOOTSTRAP_ERROR' :
      $message = dt("Drush was not able to start (bootstrap) Drupal.\n");
      $message .= dt("Hint: This error can only occur once the database connection has already been successfully initiated, therefore this error generally points to a site configuration issue, and not a problem connecting to the database.\n");
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
  if (drupal_cron_run()) {
    drush_log(dt('Cron run successfully.'), 'success');
  }
  else {
    drush_set_error('DRUSH_CRON_FAILED', dt('Cron run failed.'));
  }
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
  $args = func_get_args();

  // Array of paths to search for scripts
  $searchpath['DIR'] = dirname(__FILE__);
  $searchpath['cwd'] = drush_cwd();

  // Additional script paths, specified by 'script-path' option
  if ($script_path = drush_get_option('script-path', FALSE)) {
    foreach (explode(":", $script_path) as $path) {
      $searchpath[] = $path;
    }
  }


  if (empty($args)) {
    // List all available scripts.
    $all = array();
    foreach($searchpath as $key => $path) {
      $recurse = !$key == 'cwd';
      $all = array_merge( $all , array_keys(drush_scan_directory($path, '/\.php$/', array('.', '..', 'CVS'), NULL, $recurse)) );
    }
    drush_print(implode("\n", $all));
  }
  else {
    // Execute the specified script.
    $script = $args[0];
    $found = FALSE;
    foreach($searchpath as $path) {
      $script_filename = $path . '/' . $script;
      if (file_exists($script_filename)) {
        include($script_filename);
        $found = TRUE;
        break;
      }
      $all[] = $script_filename;
    }

    if (!$found) {
      drush_set_error('script not found', dt('Unable to find any of the following: @files', array('@files' => implode(', ', $all))));
    }
  }
}

function drush_core_eval($command) {
  eval($command . ';');
}
