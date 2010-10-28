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
    'description' => 'Print this help message. See `drush help help` for more options.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH, // No bootstrap.
    'options' => array(
      '--sort' => 'Sort commands in alphabetical order. drush waits for full bootstrap before printing any commands when this option is used.',
      '--filter' => 'Restrict command list to those commands defined in the specified file.',
      '--html' => 'Print help for all commands in HTML format.',
      '--pipe' => 'A list of available commands, one per line.',
    ),
    'examples' => array(
      'drush' => 'List all commands.',
      'drush --filter=devel_generate' => 'Show only commands defined in devel_generate.drush.inc',
      'drush help pm-download' => 'Show help for one command.',
      'drush help dl' => 'Show help for one command using an alias.',
    ),
    'topics' => array('core-readme'),
  );
  $items['core-cron'] = array(
    'description' => 'Run all cron hooks.',
    'aliases' => array('cron'),
  );
  $items['updatedb'] = array(
    'description' => dt('Execute the update.php process from the command line'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
    'aliases' => array('updb'),
  );
  $items['core-status'] = array(
    'description' => 'Provides a birds-eye view of the current Drupal installation, if any.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'aliases' => array('status', 'st'),
    'examples' => array(
      'drush status version' => 'Show all status lines that contain version information.',
      'drush status --pipe' => 'A list key=value items separated by line breaks.',
      'drush status drush-version --pipe' => 'Emit just the drush version with no label.',
    ),
    'arguments' => array(
      'item' => 'Optional.  The status item line(s) to display.  Any matching line is shown; if only one line matches, then only the value is displayed.  Otherwise, key=value is output.',
    ),
    'options' => array(
      'show-passwords' => 'Show database password.',
    ),
    'topics' => array('core-readme'),
  );
  $items['php-script'] = array(
    'description' => "Run php script(s).",
    'examples' => array(
      'drush php-script scratch' => 'Run scratch.php script. See commands/core directory.',
      'drush php-script example --script-path=/path/to/scripts:/another/path' => 'Run script from specified paths',
      'drush php-script' => 'List all available scripts.',
    ),
    'arguments' => array(
      'filename' => 'Optional. The file you wish to execute (without extension). If omitted, list files ending in .php in the current working directory and specified script-path. Some might not be real drush scripts. Beware.',
    ),
    'options' => array(
      '--script-path' => "Additional paths to search for scripts, separated by : (Unix-based systems) or ; (Windows).",
    ),
    'aliases' => array('scr'),
    'deprecated-aliases' => array('script'),
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
  $items['core-rsync'] = array(
    'description' => 'Rsync the Drupal tree to/from another server using ssh.  Relative paths start from the Drupal root folder if a site alias is used; otherwise they start from the current working directory.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH, // No bootstrap.
    'arguments' => array(
      'source' => 'May be rsync path or site alias. See rsync documentation and example.aliases.drushrc.php.',
      'destination' => 'May be rsync path or site alias. See rsync documentation and example.aliases.drushrc.php.',
    ),
    'options' => array(
      '--mode' => 'The unary flags to pass to rsync; --mode=rultz implies rsync -rultz.  Default is -az.',
      '--RSYNC-FLAG' => 'Most rsync flags passed to drush sync will be passed on to rsync.  See rsync documentation.',
      '--exclude-conf' => 'Excludes settings.php from being rsynced.  Default.',
      '--include-conf' => 'Allow settings.php to be rsynced',
      '--exclude-files' => 'Exclude the files directory.',
      '--exclude-sites' => 'Exclude all directories in "sites/" except for "sites/all".',
      '--exclude-other-sites' => 'Exclude all directories in "sites/" except for "sites/all" and the site directory for the site being synced.  Note: if the site directory is different between the source and destination, use --exclude-sites followed by "drush rsync @from:%site @to:%site"',
      '--exclude-paths' => 'List of paths to exclude, seperated by : (Unix-based systems) or ; (Windows).',
      '--include-paths' => 'List of paths to include, seperated by : (Unix-based systems) or ; (Windows).',
    ),
    'examples' => array(
      'drush rsync @dev @stage' => 'Rsync Drupal root from dev to stage (one of which must be local).',
      'drush rsync ./ @stage:%files/img' => 'Rsync all files in the current directory to the \'img\' directory in the file storage folder on stage.',
    ),
    'aliases' => array('rsync'),
    'deprecated-aliases' => array('sync'),
  );
  $items['php-eval'] = array(
    'description' => 'Evaluate arbitrary php code after bootstrapping Drupal.',
    'examples' => array(
      'drush php-eval "variable_set(\'hello\', \'world\');"' => 'Sets the hello variable using Drupal API.',
    ),
    'arguments' => array(
      'code' => 'PHP code',
    ),
    'deprecated-aliases' => array('eval'),
  );
  $items['site-install'] = array(
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
      'site-name' => 'Defaults to Site-Install',
      'site-mail' => 'From: for system mailings. Defaults to admin@example.com',
      'sites-subdir' => "Name of directory under 'sites' which should be created if needed. Defaults to 'default'",
    ),
    'examples' => array(
      'drush site-install expert --locale=uk' => '(Re)install using the expert install profile. Set default language to Ukranian.',
      'drush site-install --db-url=mysql://root:pass@localhost:port/dbname ' => 'Install using the specified DB params.',
      'drush site-install --account-name=joe --account-pass=mom' => 'Re-install with specified uid1 credentials.',
    ),
    'core' => array(7),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_ROOT,
    'aliases' => array('si'),
    'deprecated-aliases' => array('installsite', 'is'),
  );

  $items['drupal-directory'] = array(
    'description' => dt('Return path to a given module/theme directory. See --help for more details.'),
    'arguments' => array(
      'target' => 'A module/theme name, or special names like root, files, private, or an alias : path alias string such as @alias:%files. Defaults to root.',
    ),
    'examples' => array(
      'cd `drush dd devel`' => 'Navigate into the devel module directory',
      'cd `drush dd` ' => 'Navigate to the root of your Drupal site',
      'cd `drush dd files`' => 'Navigate to the files directory.',
      'drush dd @alias:%files' => 'Print the path to the files directory on the site @alias.',
      'edit `drush dd devel`/devel.module' => "Open devel module in your editor (customize 'edit' for your editor)",
    ),
    'aliases' => array('dd'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );

  $items['core-cli'] = array(
    'description' => dt('Enter a new shell optimized for drush use.'),
    'examples' => array(
      'help' => 'Print available drush commands',
      'cdd' => 'Navigate to the root of your Drupal site',
      'cdd files' => 'Navigate to the files directory.',
      'lsd files' => 'List all files in the Drupal files directory.',
      'on @alias status' => 'Run the command "status" on the site indicated by @alias',
    ),
    'aliases' => array('cli'),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
  );

  $items['batch-process'] = array(
    'description' => dt('Process operations in the specified batch set'),
    'hidden' => TRUE,
      'arguments' => array(
        'batch-id' => 'The batch id that will be processed',
      ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_LOGIN,
  );

  $items['updatedb-batch-process'] = array(
    'description' => dt('Perform update functions'),
    'hidden' => TRUE,
    'arguments' => array(
      'batch-id' => 'The batch id that will be processed',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
  );

  // Topic commands
  $items['core-readme'] = array(
    'description' => dt('README.txt'),
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'callback' => 'drush_print_file',
    'callback arguments' => array(DRUSH_BASE_PATH . '/README.txt'),
  );

  return $items;
}

function core_drush_engine_drupal() {
  $engines = array();
  $engines['batch'] = array();
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

  // Clear all caches. We just performed major surgery.
  drush_drupal_cache_clear_all();

  drush_log(dt('Finished performing updates.'), 'ok');
}

/**
 * This is called for help command, or no command.
 */
function drush_core_help() {
  $commands = func_get_args();


  if (empty($commands)) {
    // Build a fake command for the purposes of showing examples and options.
    $fake = array(
      'description' => 'Execute a drush command. Run `drush help [command]` to view command-specific help.',
      'options' => drush_get_global_options(),
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
        }

        // These two options require delayed printing.
        if (!drush_get_option('sort') && !drush_get_option('html')) {
          drush_print_table($rows, FALSE, array(0 => 20));
        }
        $printed_rows = array_merge($printed_rows, $rows);
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

  drush_set_error('DRUSH_COMMAND_NOT_FOUND', dt('Invalid command !command.', array('!command' => implode(" ", $commands))));
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
  $options = drush_get_global_options();
  $output .=  '<h3>Global Options</h3><table>';
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
    case 'drush:php-script':
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
    case 'drush:php-eval':
      return dt("Run arbitrary PHP code in the context of Drupal");
    case 'drush:site-install':
      return dt("Install Drupal using specified install profile.");
    case 'drush:drupal-directory':
      return dt("Return the filesystem path for projects/themes and other key folders. Used by `cli` command for handy commands `cdd` and `lsd`. If you want to use this command directly, you usually want to prefix the command with cd and enclose the command invocation in backticks. See Examples below.");
    case 'drush:core-cli':
      return dt("Enter a new shell optimized for drush use.  All .bashrc customizations are still available. See help for more details.");
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
  $status_table = _core_site_status_table();
  return _core_site_credential_table($status_table);
}

function _core_site_credential_table($status_table) {
  $credentials = '';
  foreach ($status_table as $key => $value) {
    $credentials .= sprintf("  %-18s: %s\n", $key, $value);
  }
  return $credentials;
}

function _core_site_credential_list($status_table) {
  $credentials = '';
  foreach ($status_table as $key => $value) {
    if (isset($value)) {
      $credentials .= sprintf("%s=%s\n", strtolower(str_replace(' ', '_', $key)), $value);
    }
  }
  return $credentials;
}

function _core_path_aliases($project = '') {
  $paths = array();
  $phase = drush_get_context('DRUSH_BOOTSTRAP_PHASE');
  if ($drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT')) {
    $paths['%root'] = $drupal_root;
    if ($site_root = drush_get_context('DRUSH_DRUPAL_SITE_ROOT')) {
      $paths['%site'] = $site_root;
      if (is_dir($modules_path = conf_path() . '/modules')) {
        $paths['%modules'] = $modules_path;
      }
      else {
        $paths['%modules'] = 'sites' . DIRECTORY_SEPARATOR . 'all' . DIRECTORY_SEPARATOR . 'modules';
      }
      if (is_dir($themes_path = conf_path() . '/themes')) {
        $paths['%themes'] = $themes_path;
      }
      else {
        $paths['%themes'] = 'sites' . DIRECTORY_SEPARATOR . 'all' . DIRECTORY_SEPARATOR . 'themes';
      }
      if (drush_drupal_major_version() >= 7) {
        $paths['%files'] = DrupalPublicStreamWrapper::getDirectoryPath();
        $paths['%private'] = DrupalPrivateStreamWrapper::getDirectoryPath();
      }
      elseif (function_exists('file_directory_path')) {
        $paths['%files'] = file_directory_path();
      }
      // If the 'project' parameter was specified, then search
      // for a project (or a few) and add its path to the path list
      if (!empty($project)) {
        foreach(explode(',', $project) as $target) {
          $path = drush_core_find_project_path($target);
          if(isset($path)) {
            $paths['%' . $target] = $path;
          }
        }
      }
    }
  }

  // Add in all of the global paths from $options['path-aliases']
  $paths = array_merge($paths, drush_get_option('path-aliases', array()));

  return $paths;
}

function _core_site_status_table($project = '') {
  $phase = drush_get_context('DRUSH_BOOTSTRAP_PHASE');
  if ($drupal_root = drush_get_context('DRUSH_DRUPAL_ROOT')) {
    $status_table['Drupal version'] = drush_drupal_version();
    if ($site_root = drush_get_context('DRUSH_DRUPAL_SITE_ROOT')) {
      $status_table['Site URI'] = drush_get_context('DRUSH_URI');
      if ($creds = drush_get_context('DRUSH_DB_CREDENTIALS')) {
        $status_table['Database driver'] = $creds['driver'];
        $status_table['Database hostname'] = $creds['host'];
        $status_table['Database username'] = $creds['user'];
        $status_table['Database name'] = $creds['name'];
        if (drush_get_option('show-passwords', FALSE)) {
          $status_table['Database password'] = $creds['pass'];
        }
        if ($phase > DRUSH_BOOTSTRAP_DRUPAL_DATABASE) {
          $status_table['Database'] = dt('Connected');
          if ($phase > DRUSH_BOOTSTRAP_DRUPAL_FULL) {
            $status_table['Drupal bootstrap'] = dt('Successful');
            if ($phase == DRUSH_BOOTSTRAP_DRUPAL_LOGIN) {
              global $user;
              $username =  ($user->uid) ? $user->name : dt('Anonymous');
              $status_table['Drupal user'] = $username;
            }
          }
        }
      }
    }
    $status_table['Default theme'] = drush_theme_get_default();
    $status_table['Administration theme'] = drush_theme_get_admin();
  }
  if (function_exists('php_ini_loaded_file')) {
    // Function available on PHP >= 5.2.4, but we use it if available to help
    // users figure out their php.ini issues.
    $status_table['PHP configuration'] = php_ini_loaded_file();
  }
  $status_table['Drush version'] = DRUSH_VERSION;
  $status_table['Drush configuration'] = implode(' ', drush_get_context_options('context-path', TRUE));

  // None of the Status keys are in dt(); this helps with machine-parsing of status?
  $path_names['root'] = 'Drupal root';
  $path_names['site'] = 'Site path';
  $path_names['modules'] = 'Modules path';
  $path_names['themes'] = 'Themes path';
  $path_names['files'] = 'File directory path';
  $path_names['private'] = 'Private file directory path';

  $paths = _core_path_aliases($project);
  if (!empty($paths)) {
    foreach ($paths as $target => $one_path) {
      $name = $target;
      if (substr($name,0,1) == '%') {
        $name = substr($name,1);
      }
      if (array_key_exists($name, $path_names)) {
        $name = $path_names[$name];
      }
      $status_table[$name] = $one_path;
    }
  }

  // Store the paths into the '%paths' index; this will be
  // used by other code, but will not be included in the output
  // of the drush status command.
  $status_table['%paths'] = $paths;

  return $status_table;
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
  $status_table = _core_site_status_table(drush_get_option('project',''));
  // If args are specified, filter out any entry that is not named
  // (in other words, only show lines named by one of the arg values)
  $args = func_get_args();
  if (!empty($args)) {
    foreach ($status_table as $key => $value) {
      if (!_drush_core_is_named_in_array($key, $args)) {
        unset($status_table[$key]);
      }
    }
  }
  drush_backend_set_result($status_table);
  unset($status_table['%paths']);
  // Print either an ini-format list or a formatted ASCII table
  if (drush_get_option('pipe')) {
    if (count($status_table) == 1) {
      $first_value = array_shift($status_table);
      drush_print_pipe($first_value);
    }
    else {
      drush_print_pipe(_core_site_credential_list($status_table));
    }
  }
  else {
    unset($status_table['Modules path']);
    unset($status_table['Themes path']);
    drush_print_table(drush_key_value_to_array_table($status_table));
  }
  return;
}

function _drush_core_is_named_in_array($key, $the_array) {
  $is_named = FALSE;

  $simplified_key = str_replace(array(' ', '_', '-'), array('', '', ''), $key);

  foreach ($the_array as $name) {
    if (stristr($simplified_key, str_replace(array(' ', '_', '-'), array('', '', ''), $name))) {
      $is_named = TRUE;
    }
  }

  return $is_named;
}

/**
 * Command callback. Runs "naked" php scripts.
 */
function drush_core_php_script() {
  $args = func_get_args();

  // Array of paths to search for scripts
  $searchpath['DIR'] = dirname(__FILE__);
  $searchpath['cwd'] = drush_cwd();

  // Additional script paths, specified by 'script-path' option
  if ($script_path = drush_get_option('script-path', FALSE)) {
    foreach (explode(PATH_SEPARATOR, $script_path) as $path) {
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
    if (!preg_match('/\.php$/i', $script)) {
      $script .= '.php';
    }
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
      drush_set_error('Script not found.', dt('Unable to find any of the following: @files', array('@files' => implode(', ', $all))));
    }
  }
}

function drush_core_php_eval($command) {
  eval($command . ';');
}

/**
 * Process sets from the specified batch.
 *
 * This is the default batch processor that will be used if the $command parameter
 * to drush_backend_batch_process() has not been specified.
 */
function drush_core_batch_process($id) {
  drush_batch_command($id);
}

/**
 * Process outstanding updates during updatedb.
 *
 * This is a batch processing command that makes use of the drush_backend_invoke
 * api.
 *
 * This command includes the version specific update engine, which correctly
 * initialises the environment to be able to successfully handle minor and major
 * upgrades.
 */
function drush_core_updatedb_batch_process($id) {
  drush_include_engine('drupal', 'update', drush_drupal_major_version());
  _update_batch_command($id);
}

function _drush_core_directory($target = 'root') {
  // Normalize to a sitealias in the target.
  $normalized_target = $target;
  if (strpos($target, ':') === FALSE) {
    if (substr($target,0,1) == '@') {
      $normalized_target = $target; // . ':%site';
    }
    else {
      // @self makes no sense before 'site' level.
      if(!drush_bootstrap(DRUSH_BOOTSTRAP_DRUPAL_SITE)) {
        return FALSE;
      }
      $normalized_target = '@self:';
      if (substr($target,0,1) != '%') {
        $normalized_target .= '%';
      }
      $normalized_target .= $target;
    }
  }

  $additional_options = array();
  $values = drush_sitealias_evaluate_path($normalized_target, $additional_options);

  if (isset($values['path'])) {
    // Hurray, we found the destination
    return $values['path'];
  }
  return NULL;
}

function drush_core_drupal_directory($target = 'root') {
  $path = _drush_core_directory($target);

  if (isset($path)) {
    drush_print($path);
  }
  else {
    return drush_set_error(dt('Core directory path !target not found.', array('!target' => $target)));
  }

  return TRUE;
}

function drush_core_find_project_path($target) {
  $theme_suffix = drush_drupal_major_version() >= 6 ? '.info' : '/style.css';
  $masks = array(
    conf_path() . '/modules' => "/^$target.module/",
    'profiles/default/modules' => "/^$target.module/", // Too early for variable_get('install_profile', 'default'); Just use default.
    'sites/all/modules' => "/^$target.module/", // Add all module paths, even disabled modules.
    conf_path() . '/themes' => "/^$target" . "$theme_suffix/",
    'sites/all/themes' => "/^$target" . "$theme_suffix/",
  );

  $files = array();
  foreach ($masks as $key => $mask) {
    if ($files = drush_scan_directory("$key", $mask, array('..', '.', 'CVS', '.svn', '.bzr'), 0, TRUE, 'name')) {
      // Just use the first match.
      $file = reset($files);
      return drush_get_context('DRUSH_DRUPAL_ROOT') . '/' . dirname($file->filename);
    }
  }

  return NULL;
}

function drush_core_cli() {
  drush_bootstrap_max();

  // Do not allow cli to start recursively, or from backend invoke.
  if (drush_get_option('in-cli',FALSE)) {
    return drush_set_error(dt('Already in drush core-cli; press control-d to exit.'));
  }
  if (drush_get_context('DRUSH_BACKEND')) {
    return drush_set_error(dt('Cannot run drush core-cli from non-interactive mode; aborting.'));
  }

  // Make sure that we call drush the same way that we were called.
  $drush_command = DRUSH_COMMAND.' --in-cli';

  $bashrc_data = implode("/n/n", drush_command_invoke_all('cli_bashrc', $drush_command));

  // Before entering the new bash script, cd to the current site root,
  // if any.  This will make our default site for drush match the
  // currently bootstrapped site (at least until the user cd's away).
  if ($site_root = drush_get_context('DRUSH_DRUPAL_SITE_ROOT')) {
    chdir($site_root);
  }

  // Print our entire bashrc file in verbose mode
  if (drush_get_context('DRUSH_VERBOSE')) {
    drush_print($bashrc_data);
  }

  drush_print("Entering the drush cli.  Use CONTROL-D to exit.");
  drush_print("Type 'help' for help.");

  // Save out bashrc in a temporary file and launch bash.
  // control-d to exit.  The temp file will be deleted after
  // we exit.
  $bashrc = drush_save_data_to_temp_file($bashrc_data);
  drush_op('system', 'bash --rcfile ' . $bashrc . ' > `tty`');
}

// Implement our own hook_cli_bashrc()
function core_cli_bashrc($drush_command) {
  // Set up our default bashrc file for the drush cli
  $bashrc_data = <<<EOD

# source the user's .bashrc file
[ -r ~/.bashrc ] && {
  pushd ~ > /dev/null
  . .bashrc
  popd > /dev/null
}

PS1="drush> "

alias on="$drush_command"
alias drush="$drush_command"
DRUSH_CLI=true


function cdd() {
  TARGET=
  PARAM=\$1
  if [ \$# -gt 1 ] ; then
    TARGET=\$1
    PARAM=\$2
  fi
  DEST=`drush \$TARGET drupal-directory \$PARAM`
  if [ \$? != 0 ] || [ -z "\$DEST" ]
  then
    echo "Target \$1 was not found."
  else
    echo "cd \$DEST"
    cd \$DEST;
  fi
}

function lsd() {
  TARGET=
  PARAM=\$1
  if [ \$# -gt 1 ] ; then
    TARGET=\$1
    PARAM=\$2
  fi
  DEST=`drush \$TARGET drupal-directory \$PARAM`
  if [ \$? != 0 ] || [ -z "\$DEST" ]
  then
    echo "Target \$1 was not found."
  else
    echo "ls \$DEST"
    ls \$DEST;
  fi
}

EOD;

  // Add aliases for all of our commands
  $commands = drush_get_commands();
  foreach ($commands as $key => $command) {
    // Filter out old commands that still have spaces
    if (strpos($key, ' ') === FALSE) {
      $bashrc_data .= "function $key() {\n  $drush_command $key \"\$@\"\n}\n";
    }
  }

  return $bashrc_data;
}