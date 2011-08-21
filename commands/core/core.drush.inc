<?php

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
      'sort' => 'Sort commands in alphabetical order. drush waits for full bootstrap before printing any commands when this option is used.',
      'filter' => 'Restrict command list to those commands defined in the specified file.',
      'html' => 'Print help for all commands in HTML format.',
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
  $items['version'] = array(
    'description' => 'Show drush version.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH, // No bootstrap.
    'options' => array(
      'pipe' => 'Print just the version number, and nothing else.',
      'self-update' => 'Check for pending updates to drush itself. Set to 0 to avoid check.',
    ),
  );
  $items['self-update'] = array(
    'description' => 'Update drush to the latest version, if available.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH, // No bootstrap.
    'options' => array(
      'dev' => 'Allow updates to latest dev release.',
    ),
    'aliases' => array('selfupdate'),
  );
  $items['core-cron'] = array(
    'description' => 'Run all cron hooks in all active modules for specified site.',
    'aliases' => array('cron'),
  );
  $items['updatedb'] = array(
    'description' => 'Apply any database updates required (as with running update.php).',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
    'aliases' => array('updb'),
  );
  $items['core-config'] = array(
    'description' => 'Edit drushrc, site alias, and Drupal settings.php files.',
    'bootstrap' => DRUSH_BOOTSTRAP_MAX,
    'arguments' => array(
      'filter' => 'A substring for filtering the list of files. Omit this argument to choose from loaded files.',
    ),
    'examples' => array(
      'drush core-config' => 'Pick from a list of config/alias/settings files. Open selected in editor.',
      'drush core-config etc' => 'Edit the global configuration file.',
      'drush core-config demo.alia' => 'Edit a particular alias file.',
      'drush core-config sett' => 'Edit settings.php for the current Drupal site.',
      'drush core-config --choice=2' => 'Edit the second file in the choice list.',
    ),
    'aliases' => array('conf', 'config'),
  );
  $items['core-status'] = array(
    'description' => 'Provides a birds-eye view of the current Drupal installation, if any.',
    'bootstrap' => DRUSH_BOOTSTRAP_MAX,
    'aliases' => array('status', 'st'),
    'examples' => array(
      'drush core-status version' => 'Show all status lines that contain version information.',
      'drush core-status --pipe' => 'A list key=value items separated by line breaks.',
      'drush core-status drush-version --pipe' => 'Emit just the drush version with no label.',
    ),
    'arguments' => array(
      'item' => 'Optional.  The status item line(s) to display.',
    ),
    'options' => array(
      'show-passwords' => 'Show database password.',
    ),
    'topics' => array('docs-readme'),
  );
  $items['php-eval'] = array(
    'description' => 'Evaluate arbitrary php code after bootstrapping Drupal (if available).',
    'examples' => array(
      'drush php-eval "variable_set(\'hello\', \'world\');"' => 'Sets the hello variable using Drupal API.',
    ),
    'arguments' => array(
      'code' => 'PHP code',
    ),
    'allow-additional-options' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_MAX,
    'aliases' => array('eval', 'ev'),
  );
  $items['php-script'] = array(
    'description' => "Run php script(s).",
    'examples' => array(
      'drush php-script scratch' => 'Run scratch.php script. See commands/core directory.',
      'drush php-script example --script-path=/path/to/scripts:/another/path' => 'Run script from specified paths',
      'drush php-script' => 'List all available scripts.',
      '' => '',
      "#!/usr/bin/env drush\n<?php\nvariable_set('key', drush_shift());" => "Execute php code with a full Drupal bootstrap directly from a shell script.",
    ),
    'arguments' => array(
      'filename' => 'Optional. The file you wish to execute (without extension). If omitted, list files ending in .php in the current working directory and specified script-path. Some might not be real drush scripts. Beware.',
    ),
    'options' => array(
      'script-path' => "Additional paths to search for scripts, separated by : (Unix-based systems) or ; (Windows).",
    ),
    'allow-additional-options' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_MAX,
    'aliases' => array('scr'),
    'deprecated-aliases' => array('script'),
    'topics' => array('docs-examplescript', 'docs-scripts'),
  );
  $items['search-status'] = array(
    'description' => 'Show how many items remain to be indexed out of the total.',
    'drupal dependencies' => array('search'),
    'options' => array(
      'pipe' => 'Display in the format remaining/total for processing by scripts.',
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
      'immediate' => 'Rebuild the index immediately, instead of waiting for cron.',
    ),
  );
  $items['core-execute'] = array(
    'description' => 'Execute a shell command. Usually used with a site alias.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH, // No bootstrap.
    'arguments' => array(
      'command' => 'The shell command to be executed.',
    ),
    'examples' => array(
      'drush exec \'git pull\'' => 'Retrieve latest code from git',
    ),
    'aliases' => array('exec', 'execute'),
    'topics' => array('docs-aliases'),
  );
  $items['core-rsync'] = array(
    'description' => 'Rsync the Drupal tree to/from another server using ssh.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH, // No bootstrap.
    'arguments' => array(
      'source' => 'May be rsync path or site alias. See rsync documentation and example.aliases.drushrc.php.',
      'destination' => 'May be rsync path or site alias. See rsync documentation and example.aliases.drushrc.php.',
    ),
    'options' => array(
      'mode' => 'The unary flags to pass to rsync; --mode=rultz implies rsync -rultz.  Default is -az.',
      'RSYNC-FLAG' => 'Most rsync flags passed to drush sync will be passed on to rsync.  See rsync documentation.',
      'exclude-conf' => 'Excludes settings.php from being rsynced.  Default.',
      'include-conf' => 'Allow settings.php to be rsynced. Default is to exclude settings.php.',
      'include-vcs' => 'Include special version control directories (e.g. .svn).  Default is to exclude vcs files.',
      'exclude-files' => 'Exclude the files directory.',
      'exclude-sites' => 'Exclude all directories in "sites/" except for "sites/all".',
      'exclude-other-sites' => 'Exclude all directories in "sites/" except for "sites/all" and the site directory for the site being synced.  Note: if the site directory is different between the source and destination, use --exclude-sites followed by "drush rsync @from:%site @to:%site"',
      'exclude-paths' => 'List of paths to exclude, seperated by : (Unix-based systems) or ; (Windows).',
      'include-paths' => 'List of paths to include, seperated by : (Unix-based systems) or ; (Windows).',
    ),
    'allow-additional-options' => TRUE,
    'examples' => array(
      'drush rsync @dev @stage' => 'Rsync Drupal root from dev to stage (one of which must be local).',
      'drush rsync ./ @stage:%files/img' => 'Rsync all files in the current directory to the \'img\' directory in the file storage folder on stage.',
    ),
    'aliases' => array('rsync'),
    'deprecated-aliases' => array('sync'),
    'topics' => array('docs-aliases'),
  );
  $items['site-install'] = array(
    'description' => 'Install Drupal along with modules/themes/configuration using the specified install profile.',
    'arguments' => array(
      'profile' => 'the install profile you wish to run. defaults to \'default\' in D6, \'standard\' in D7+',
      'key=value...' => 'any additional settings you wish to pass to the profile. Only support on D7+. The key is in the form [form name].[parameter name].',
    ),
    'options' => array(
      'db-url' => 'A Drupal 6 style database URL. Only required for initial install - not re-install.',
      'db-prefix' => 'An optional table prefix to use for initial install.  Can be a key-value array of tables/prefixes in a drushrc file (not the command line).',
      'db-su' => 'Account to use when creating a new database. Must have Grant permission (mysql only). Optional.',
      'db-su-pw' => 'Password for the "db-su" account. Optional.',
      'account-name' => 'uid1 name. Defaults to admin',
      'account-pass' => 'uid1 pass. Defaults to a randomly generated password. If desired, set a fixed password in drushrc.php.',
      'account-mail' => 'uid1 email. Defaults to admin@example.com',
      'locale' => 'A short language code. Sets the default site language. Language files must already be present. You may use download command to get them.',
      'clean-url'=> 'Defaults to 1',
      'site-name' => 'Defaults to Site-Install',
      'site-mail' => 'From: for system mailings. Defaults to admin@example.com',
      'sites-subdir' => "Name of directory under 'sites' which should be created. Only needed when the subdirectory does not already exist. Defaults to 'default'",
    ),
    'examples' => array(
      'drush site-install expert --locale=uk' => '(Re)install using the expert install profile. Set default language to Ukranian.',
      'drush site-install --db-url=mysql://root:pass@localhost:port/dbname' => 'Install using the specified DB params.',
      'drush site-install --db-url=sqlite://sites/example.com/files/.ht.sqlite' => 'Install using SQLite (D7+ only).',
      'drush site-install --account-name=joe --account-pass=mom' => 'Re-install with specified uid1 credentials.',
      'drush site-install standard install_configure_form.site_default_country=FR my_profile_form.my_settings.key=value' => 'Pass additional arguments to the profile (D7 only).',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_ROOT,
    'aliases' => array('si'),
    'deprecated-aliases' => array('installsite', 'is'),
  );
  $items['drupal-directory'] = array(
    'description' => 'Return path to a given module/theme directory.',
    'arguments' => array(
      'target' => 'A module/theme name, or special names like root, files, private, or an alias : path alias string such as @alias:%files. Defaults to root.',
    ),
    'options' => array(
      'component' => "The portion of the evaluated path to return.  Defaults to 'path'; 'name' returns the site alias of the target.",
      'local' => "Reject any target that specifies a remote site.",
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

  $items['batch-process'] = array(
    'description' => 'Process operations in the specified batch set',
    'hidden' => TRUE,
      'arguments' => array(
        'batch-id' => 'The batch id that will be processed',
      ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_LOGIN,
  );

  $items['updatedb-batch-process'] = array(
    'description' => 'Perform update functions',
    'hidden' => TRUE,
    'arguments' => array(
      'batch-id' => 'The batch id that will be processed',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
  );
  $items['core-global-options'] = array(
    'description' => 'All global options',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
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
 * Command argument complete callback.
 *
 * @return
 *   Array of available profile names.
 */
function core_site_install_complete() {
  $max = drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_ROOT);
  if ($max >= DRUSH_BOOTSTRAP_DRUPAL_ROOT) {
    return array('values' => array_keys(drush_find_profiles(DRUPAL_ROOT)));
  }
}

/**
 * Command argument complete callback.
 *
 * @return
 *  Array of available site aliases.
 */
function core_core_rsync_complete() {
  return array('values' => array_keys(_drush_sitealias_all_list()));
}

function core_drush_engine_drupal() {
  $engines = array();
  $engines['batch'] = array();
  $engines['update'] = array();
  $engines['environment'] = array();
  $engines['site_install'] = array();
  return $engines;
}

/**
 * Command handler. Execute update.php code from drush.
 */
function drush_core_updatedb() {
  if (drush_get_context('DRUSH_SIMULATE')) {
    drush_log(dt('updatedb command does not support --simulate option.'), 'ok');
    return TRUE;
  }

  drush_include_engine('drupal', 'update', drush_drupal_major_version());
  if (update_main() === FALSE) {
    return FALSE;
  }

  if (drush_drupal_major_version() <= 6) {
    // Clear all caches. We just performed major surgery.
    drush_drupal_cache_clear_all();
  }
  else {
    // Should be unnecessary on D7.
    // On D7 site-upgrade, this cache_clear was leading to:
    // Call to undefined function field_read_fields() in field_sql_storage.install line 17
  }

  drush_log(dt('Finished performing updates.'), 'ok');
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
    case 'meta:core:title':
      return dt("Core drush commands");
    case 'drush:help':
      return dt("Drush provides an extensive help system that describes both drush commands and topics of general interest.  Use `drush help --filter` to present a list of command categories to view, and `drush topic` for a list of topics that go more in-depth on how to use and extend drush.");
    case 'drush:php-script':
      return dt("Runs the given php script(s) after a full Drupal bootstrap. A useful alternative to eval command when your php is lengthy or you can't be bothered to figure out bash quoting. If you plan to share a script with others, consider making a full drush command instead, since that's more self-documenting.  Drush provides commandline options to the script via drush_get_option('option-name'), and commandline arguments can be accessed either via drush_get_arguments(), which returns all arguments in an array, or drush_shift(), which removes the next argument from the list and returns it.");
    case 'drush:rsync':
      return dt("Sync the entire drupal directory or a subdirectory to a <destination> using ssh. Excludes reserved files and directories for supported VCSs. Useful for pushing copies of your tree to a staging server, or retrieving a files directory from a remote site. Relative paths start from the Drupal root directory if a site alias is used; otherwise they start from the current working directory.");
    case 'drush:drupal-directory':
      return dt("Return the filesystem path for modules/themes and other key folders. Used by `cli` command for handy commands `cdd` and `lsd`. If you want to use this command directly, you usually want to prefix the command with cd and enclose the command invocation in backticks. See Examples below.");
    case 'error:DRUSH_DRUPAL_DB_ERROR':
      $message = dt("Drush was not able to start (bootstrap) the Drupal database.\n");
      $message .= dt("Hint: This error often occurs when Drush is trying to bootstrap a site that has not been installed or does not have a configured database.\n");
      $message .= dt("\nDrush was attempting to connect to : \n!credentials\n", array('!credentials' => _core_site_credentials()));
      $message .= dt("You can select another site with a working database setup by specifying the URI to use with the --uri parameter on the command line or \$options['uri'] in your drushrc.php file.\n");
      return $message;
    case 'error:DRUSH_DRUPAL_BOOTSTRAP_ERROR':
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
        $paths['%modules'] = 'sites/all/modules';
      }
      if (is_dir($themes_path = conf_path() . '/themes')) {
        $paths['%themes'] = $themes_path;
      }
      else {
        $paths['%themes'] = 'sites/all/themes';
      }
      if (drush_drupal_major_version() >= 7) {
        if (drush_get_context('DRUSH_BOOTSTRAP_PHASE') >= DRUSH_BOOTSTRAP_DRUPAL_SITE) {
          $paths['%files'] = variable_get('file_public_path', conf_path() . '/files');
          $private_path = variable_get('file_private_path', FALSE);
          if ($private_path !== FALSE) {
            $paths['%private'] = $private_path;
          }
        }
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
  drush_sitealias_load_all();
  $status_table['Drush version'] = DRUSH_VERSION;
  $status_table['Drush configuration'] = implode(' ', drush_get_context_options('context-path', TRUE));
  $status_table['Drush alias files'] = implode(' ', drush_get_context('drush-alias-files'));

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
    drush_log(dt('Cron run successful.'), 'success');
  }
  else {
    return drush_set_error('DRUSH_CRON_FAILED', dt('Cron run failed.'));
  }
}

/**
 * Command callback. Edit drushrc and alias files.
 */
function drush_core_config($filter = NULL) {
  $all = drush_core_config_load();

  // Apply any filter that was supplied.
  if ($filter) {
    foreach ($all as $key => $file) {
      if (strpos($file, $filter) === FALSE) {
        unset($all[$key]);
      }
    }
  }
  $all = drush_map_assoc(array_values($all));

  if (count($all) == 1) {
    return drush_shell_exec_interactive('$EDITOR %s &', current($all));
  }
  else {
    $choice = drush_choice($all, 'Enter a number to choose which file to edit.', '!key');
    if ($choice !== FALSE) {
      return drush_shell_exec_interactive('$EDITOR %s &', $all[$choice]);
    }
  }
}

/**
 * Command argument complete callback.
 *
 * @return
 *   Array of available configuration files for editing.
 */
function core_core_config_complete() {
  return array('values' => drush_core_config_load(FALSE));
}

function drush_core_config_load($headers = TRUE) {
  $rcs_header = $rcs = $aliases_header = $aliases = $drupal_header = $drupal = array();
  drush_sitealias_load_all();
  if ($rcs = drush_get_context_options('context-path', TRUE)) {
    if ($headers) {
      $rcs_header = array('drushrc' => '-- Drushrc --');
    }
  }
  if ($aliases = drush_get_context('drush-alias-files')) {
    if ($headers) {
      $aliases_header = array('aliases' => '-- Aliases --');
    }
  }
  if ($site_root = drush_get_context('DRUSH_DRUPAL_SITE_ROOT')) {
    $drupal = array_merge(array(realpath($site_root . '/settings.php'), realpath(DRUPAL_ROOT . '/.htaccess')));
    if ($headers) {
      $drupal_header = array('drupal' => '-- Drupal --');
    }
  }
  return array_merge($rcs_header, $rcs, $aliases_header, $aliases, $drupal_header, $drupal);
}

/**
 * Command callback. Provides a birds-eye view of the current Drupal
 * installation.
 */
function drush_core_status() {
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
  $return = $status_table;
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
  return $return;
}

// Command callback. Show all global options. Exposed via topic command.
function drush_core_global_options() {
  drush_print(dt('These options are applicable to most drush commands.'));
  drush_print();
  $fake = drush_global_options_command(FALSE);
  $global_option_rows = drush_format_help_section($fake, 'options');
  drush_print_table($global_option_rows);
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
 * Command callback. Runs "naked" php scripts
 * and drush "shebang" scripts ("#!/usr/bin/env drush").
 */
function drush_core_php_script() {
  $found = FALSE;
  $script = NULL;
  if ($args = func_get_args()) {
    $script = $args[0];
  }

  if ($script == '-') {
    eval(stream_get_contents(STDIN));
  }
  elseif (file_exists($script)) {
    $found = $script;
  }
  else {
    // Array of paths to search for scripts
    $searchpath['DIR'] = dirname(__FILE__);
    $searchpath['cwd'] = drush_cwd();

    // Additional script paths, specified by 'script-path' option
    if ($script_path = drush_get_option('script-path', FALSE)) {
      foreach (explode(PATH_SEPARATOR, $script_path) as $path) {
        $searchpath[] = $path;
      }
    }
    drush_log(dt('Searching for scripts in ') . implode(',', $searchpath), 'debug');

    if (!isset($script)) {
      // List all available scripts.
      $all = array();
      foreach($searchpath as $key => $path) {
        $recurse = !(($key == 'cwd') || ($path == '/'));
        $all = array_merge( $all , array_keys(drush_scan_directory($path, '/\.php$/', array('.', '..', 'CVS'), NULL, $recurse)) );
      }
      drush_print(implode("\n", $all));
    }
    else {
      // Execute the specified script.
      foreach($searchpath as $path) {
        $script_filename = $path . '/' . $script;
        if (file_exists($script_filename . '.php')) {
          $script_filename .= '.php';
        }
        if (file_exists($script_filename)) {
          $found = $script_filename;
          break;
        }
        $all[] = $script_filename;
      }
      if (!$found) {
        return drush_set_error('DRUSH_TARGET_NOT_FOUND', dt('Unable to find any of the following: @files', array('@files' => implode(', ', $all))));
      }
    }
  }

  if ($found) {
    // Set the DRUSH_SHIFT_SKIP to two; this will cause
    // drush_shift to skip the next two arguments the next
    // time it is called.  This allows scripts to get all
    // arguments, including the 'php-script' and script
    // pathname, via drush_get_arguments(), or it can process
    // just the arguments that are relevant using drush_shift().
    drush_set_context('DRUSH_SHIFT_SKIP', 2);
    if (_drush_core_eval_shebang_script($found) === FALSE) {
      include($found);
    }
  }
}

function drush_core_php_eval($command) {
  return eval($command . ';');
}

/*
 * Evaluate a script that begins with #!drush php-script
 */
function _drush_core_eval_shebang_script($script_filename) {
  $found = FALSE;
  $fp = fopen($script_filename, "r");
  if ($fp !== FALSE) {
    $line = fgets($fp);
    if (_drush_is_drush_shebang_line($line)) {
      $first_script_line = '';
      while ($line = fgets($fp)) {
        $line = trim($line);
        if ($line == '<?php') {
          $found = TRUE;
          break;
        }
        elseif (!empty($line)) {
          $first_script_line = $line . "\n";
          break;
        }
      }
      $script = stream_get_contents($fp);
      // Pop off the first two arguments, the
      // command (php-script) and the path to
      // the script to execute, as a service
      // to the script.
      eval($first_script_line . $script);
      $found = TRUE;
    }
    fclose($fp);
  }
  return $found;
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

/**
 * Given a target (e.g. @site:%modules), return the evaluated
 * directory path
 *
 * @param $target
 *   The target to evaluate.  Can be @site or /path or @site:path
 *   or @site:%pathalias, etc. (just like rsync parameters)
 * @param $component
 *   The portion of the evaluated path to return.  Possible values:
 *   'path' - the full path to the target (default)
 *   'name' - the name of the site from the path (e.g. @site1)
 *   'user-path' - the part after the ':' (e.g. %modules)
 *   'root' & 'uri' - the Drupal root and URI of the site from the path
 *   'path-component' - The ':' and the path
 */
function _drush_core_directory($target = 'root', $component = 'path', $local_only = FALSE) {
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
  $values = drush_sitealias_evaluate_path($normalized_target, $additional_options, $local_only);
  if (isset($values[$component])) {
    // Hurray, we found the destination
    return $values[$component];
  }
  return NULL;
}

function drush_core_drupal_directory($target = 'root') {
  $path = _drush_core_directory($target, drush_get_option('component', 'path'), drush_get_option('local', FALSE));

  // If _drush_core_directory is working right, it will turn
  // %blah into the path to the item referred to by the key 'blah'.
  // If there is no such key, then no replacement is done.  In the
  // case of the dd command, we will consider it an error if
  // any keys are -not- replaced in _drush_core_directory.
  if ((isset($path)) && (strpos($path, '%') === FALSE)) {
    drush_print($path);
  }
  else {
    return drush_set_error('DRUSH_TARGET_NOT_FOUND', dt("Target '!target' not found.", array('!target' => $target)));
  }

  return TRUE;
}

/**
 * Called for `drush version` or `drush --version`
 */
function drush_core_version() {
  drush_print(dt("drush version !version", array('!version' => DRUSH_VERSION)));
  drush_print_pipe(DRUSH_VERSION);
  // Next check to see if there is a newer drush.
  if (!drush_get_context('DRUSH_PIPE') && drush_get_option('self-update', TRUE)) {
    drush_check_self_update();
  }
}

function drush_core_self_update() {
  drush_set_option('select', TRUE);
  return drush_invoke('pm-download', 'drush');
}

function drush_core_find_project_path($target) {
  $theme_suffix = drush_drupal_major_version() >= 6 ? '.info' : '/style.css';
  $masks = array(
    conf_path() . '/modules' => "/^$target\.module$/",
    'profiles/default/modules' => "/^$target\.module$/", // Too early for variable_get('install_profile', 'default'); Just use default.
    'sites/all/modules' => "/^$target\.module$/", // Add all module paths, even disabled modules.
    conf_path() . '/themes' => "/^$target" . "$theme_suffix/",
    'sites/all/themes' => "/^$target" . "$theme_suffix/",
  );

  $files = array();
  foreach ($masks as $key => $mask) {
    $skip_list = array('.', '..', 'CVS');
    $skip_list = array_merge($skip_list, drush_version_control_reserved_files());
    if ($files = drush_scan_directory("$key", $mask, $skip_list, 0, TRUE, 'name')) {
      // Just use the first match.
      $file = reset($files);
      return drush_get_context('DRUSH_DRUPAL_ROOT') . '/' . dirname($file->filename);
    }
  }

  return NULL;
}

/*
 * Command callback. Execute specified shell code. Often used by shell aliases
 * that start with !.
 */
function drush_core_execute($script) {
  return drush_op_system($script);
}
