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
    'allow-additional-options' => TRUE,
    'options' => array(
      'sort' => 'Sort commands in alphabetical order. drush waits for full bootstrap before printing any commands when this option is used.',
      'filter' => array(
        'description' => 'Restrict command list to those commands defined in the specified file. Omit value to choose from a list of names.',
        'example-value' => 'category',
        'value' => 'optional',
      ),
      'format' => 'Format to output . Allowed values are: json, export, html.',
      'html' => 'Print help for all commands in HTML format. Deprecated - see --format option.',
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
    ),
    'outputformat' => array(
      'default' => 'key-value',
      'pipe-format' => 'string',
      'label' => 'Drush Version',
      'output-data-type' => 'format-single',
    ),
  );
  $items['core-cron'] = array(
    'description' => 'Run all cron hooks in all active modules for specified site.',
    'aliases' => array('cron'),
    'topics' => array('core-cron'),
  );
  $items['updatedb'] = array(
    'description' => 'Apply any database updates required (as with running update.php).',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
    'aliases' => array('updb'),
  );
  $items['updatedb-status'] = array(
    'description' => 'List any pending database updates.',
    'outputformat' => array(
      'default' => 'table',
      'pipe-format' => 'csv',
      'field-labels' => array('module' => 'Module', 'update_id' => 'Update ID', 'description' => 'Description'),
      'fields-default' => array('module', 'update_id', 'description'),
      'output-data-type' => 'format-table',
    ),
    'aliases' => array('updbst'),
  );
  $items['core-config'] = array(
    'description' => 'Edit drushrc, site alias, and Drupal settings.php files.',
    'bootstrap' => DRUSH_BOOTSTRAP_MAX,
    'arguments' => array(
      'filter' => 'A substring for filtering the list of files. Omit this argument to choose from loaded files.',
    ),
    'options' => array(
      'bg' => 'Run editor in the background. Does not work with editors such as `vi` that run in the terminal.',
    ),
    'examples' => array(
      'drush core-config' => 'Pick from a list of config/alias/settings files. Open selected in editor.',
      'drush --bg core-config' => 'Return to shell prompt as soon as the editor window opens.',
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
      'drush core-status config-active --pipe' => 'Emit just the active Config directory with no label.',
    ),
    'arguments' => array(
      'item' => 'Optional.  The status item line(s) to display.',
    ),
    'options' => array(
      'show-passwords' => 'Show database password.',
      'full' => 'Show all file paths and drush aliases in the report, even if there are a lot.',
      'project' => array(
        'description' => 'One or more projects that should be added to the path list',
        'example-value' => 'foo,bar',
      ),
    ),
    'outputformat' => array(
      'default' => 'key-value',
      'pipe-format' => 'json',
      'field-labels' => array('drupal-version' => 'Drupal version', 'uri' => 'Site URI', 'db-driver' => 'Database driver', 'db-host' => 'Database hostname', 'db-username' => 'Database username', 'db-password' => 'Database password', 'db-name' => 'Database name', 'db-status' => 'Database', 'bootstrap' => 'Drupal bootstrap', 'user' => 'Drupal user', 'theme' => 'Default theme', 'admin-theme' => 'Administration theme', 'php-bin' => 'PHP executable', 'php-conf' => 'PHP configuration', 'php-os' => 'PHP OS', 'drush-version' => 'Drush version', 'drush-conf' => 'Drush configuration', 'drush-alias-files' => 'Drush alias files', 'root' => 'Drupal root', 'site-path' => 'Site path', 'root' => 'Drupal root', 'site' => 'Site path', 'themes' => 'Themes path', 'modules' => 'Modules path', 'files' => 'File directory path', 'private' => 'Private file directory path', 'temp' => 'Temporary file directory path', 'config-active' => 'Active config directory path', 'config-staging' => 'Staging config directory path', 'files-path' => 'File directory path', 'temp-path' => 'Temporary file directory path', '%paths' => 'Other paths'),
      'formatted-filter' => '_drush_core_status_format_table_data',
      'private-fields' => 'db-password',
      'simplify-single' => TRUE,
      'table-metadata' => array(
        'list-separator' => ' ',
      ),
      'output-data-type' => 'format-list',
    ),
    'topics' => array('docs-readme'),
  );

  $items['core-requirements'] = array(
    'description' => 'Provides information about things that may be wrong in your Drupal installation, if any.',
    'aliases' => array('status-report','rq'),
    'examples' => array(
      'drush core-requirements' => 'Show all status lines from the Status Report admin page.',
      'drush core-requirements --severity=2' => 'Show only the red lines from the Status Report admin page.',
      'drush core-requirements --pipe' => 'Print out a short report in JSON format, where severity 2=error, 1=warning, and 0/-1=OK',
    ),
    'options' => array(
      'severity' => array(
        'description' => 'Only show status report messages with a severity greater than or equal to the specified value.',
        'value' => 'required',
        'example-value' => '3',
      ),
      'ignore' => 'Comma-separated list of requirements to remove from output. Run with --pipe to see key values to use.',
    ),
    'outputformat' => array(
      'default' => 'table',
      'pipe-format' => 'json',
      'field-labels' => array('title' => 'Title', 'severity' => 'Severity', 'sid' => 'SID', 'description' => 'Description', 'value' => 'Summary', 'reason' => 'Reason', 'weight' => 'Weight'),
      'fields-default' => array('title', 'severity', 'description'),
      'column-widths' => array('severity' => 8),
      'concatenate-columns' => array('description' => array('value', 'description')),
      'strip-tags' => TRUE,
      'ini-item' => 'sid',
      'key-value-item' => 'severity',
      'list-metadata' => array(
        'list-item' => 'severity',
      ),
      'output-data-type' => 'format-table',
    ),
  );
  $items['php-eval'] = array(
    'description' => 'Evaluate arbitrary php code after bootstrapping Drupal (if available).',
    'examples' => array(
      'drush php-eval "variable_set(\'hello\', \'world\');"' => 'Sets the hello variable using Drupal API.',
    ),
    'arguments' => array(
      'code' => 'PHP code',
    ),
    'required-arguments' => TRUE,
    'allow-additional-options' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_MAX,
    'aliases' => array('eval', 'ev'),
    'outputformat' => array(
      'default' => 'var_export',
    ),
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
      'script-path' => array(
        'description' => "Additional paths to search for scripts, separated by : (Unix-based systems) or ; (Windows).",
        'example-value' => '~/scripts',
      ),
    ),
    'allow-additional-options' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_MAX,
    'aliases' => array('scr'),
    'topics' => array('docs-examplescript', 'docs-scripts'),
  );
  $items['core-execute'] = array(
    'description' => 'Execute a shell command. Usually used with a site alias.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH, // No bootstrap.
    'arguments' => array(
      'command' => 'The shell command to be executed.',
    ),
    'options' => drush_shell_exec_proc_build_options(),
    'required-arguments' => TRUE,
    'allow-additional-options' => TRUE,
    'handle-remote-commands' => TRUE,
    'strict-option-handling' => TRUE,
    'examples' => array(
      'drush core-execute git pull origin rebase' => 'Retrieve latest code from git',
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
      'mode' => 'The unary flags to pass to rsync; --mode=rultz implies rsync -rultz.  Default is -akz.',
      'exclude-conf' => 'Excludes settings.php from being rsynced.  Default.',
      'include-conf' => 'Allow settings.php to be rsynced. Default is to exclude settings.php.',
      'include-vcs' => 'Include special version control directories (e.g. .svn).  Default is to exclude vcs files.',
      'exclude-files' => 'Exclude the files directory.',
      'exclude-sites' => 'Exclude all directories in "sites/" except for "sites/all".',
      'exclude-other-sites' => 'Exclude all directories in "sites/" except for "sites/all" and the site directory for the site being synced.  Note: if the site directory is different between the source and destination, use --exclude-sites followed by "drush rsync @from:%site @to:%site"',
      'exclude-paths' => 'List of paths to exclude, seperated by : (Unix-based systems) or ; (Windows).',
      'include-paths' => 'List of paths to include, seperated by : (Unix-based systems) or ; (Windows).',
      '{rsync-option-name}' => "Replace {rsync-option-name} with the rsync option (or option='value') that you would like to pass through to rsync. Examples include --delete, --exclude=*.sql, --filter='merge /etc/rsync/default.rules', etc. See the rsync documentation for a complete explaination of all the rsync options and values.",

    ),
    'strict-option-handling' => TRUE,
    'examples' => array(
      'drush rsync @dev @stage' => 'Rsync Drupal root from Drush alias dev to the alias stage (one of which must be local).',
      'drush rsync ./ @stage:%files/img' => 'Rsync all files in the current directory to the \'img\' directory in the file storage folder on the Drush alias stage.',
      'drush -s rsync @dev @stage --exclude=*.sql --delete' => "Simulate Rsync Drupal root from the Drush alias dev to the alias stage (one of which must be local), excluding all files that match the filter '*.sql' and delete all files on the destination that are no longer on the source.",
    ),
    'aliases' => array('rsync'),
    'topics' => array('docs-aliases'),
  );
  $items['site-install'] = array(
    'description' => 'Install Drupal along with modules/themes/configuration using the specified install profile.',
    'arguments' => array(
      'profile' => 'the install profile you wish to run. defaults to \'default\' in D6, \'standard\' in D7+',
      'key=value...' => 'any additional settings you wish to pass to the profile. Fully supported on D7+, partially supported on D6 (single step configure forms only). The key is in the form [form name].[parameter name] on D7 or just [parameter name] on D6.',
    ),
    'options' => array(
      'db-url' => array(
        'description' => 'A Drupal 6 style database URL. Only required for initial install - not re-install.',
        'example-value' => 'mysql://root:pass@host/db',
        ),
      'db-prefix' => 'An optional table prefix to use for initial install.  Can be a key-value array of tables/prefixes in a drushrc file (not the command line).',
      'db-su' => array(
        'description' => 'Account to use when creating a new database. Must have Grant permission (mysql only). Optional.',
        'example-value' => 'root',
      ),
      'db-su-pw' => array(
        'description' => 'Password for the "db-su" account. Optional.',
        'example-value' => 'pass',
      ),
      'account-name' => 'uid1 name. Defaults to admin',
      'account-pass' => 'uid1 pass. Defaults to a randomly generated password. If desired, set a fixed password in drushrc.php.',
      'account-mail' => 'uid1 email. Defaults to admin@example.com',
      'locale' => array(
        'description' => 'A short language code. Sets the default site language. Language files must already be present. You may use download command to get them.',
        'example-value' => 'en-GB',
      ),
      'clean-url'=> 'Defaults to 1',
      'site-name' => 'Defaults to Site-Install',
      'site-mail' => 'From: for system mailings. Defaults to admin@example.com',
      'sites-subdir' => array(
        'description' => "Name of directory under 'sites' which should be created. Only needed when the subdirectory does not already exist. Defaults to 'default'",
        'value' => 'required',
        'example-value' => 'directory_name',
      ),
    ),
    'examples' => array(
      'drush site-install expert --locale=uk' => '(Re)install using the expert install profile. Set default language to Ukranian.',
      'drush site-install --db-url=mysql://root:pass@localhost:port/dbname' => 'Install using the specified DB params.',
      'drush site-install --db-url=sqlite://sites/example.com/files/.ht.sqlite' => 'Install using SQLite (D7+ only).',
      'drush site-install --account-name=joe --account-pass=mom' => 'Re-install with specified uid1 credentials.',
      'drush site-install standard install_configure_form.site_default_country=FR my_profile_form.my_settings.key=value' => 'Pass additional arguments to the profile (D7 example shown here - for D6, omit the form id).',
      "drush site-install install_configure_form.update_status_module='array(FALSE,FALSE)'" => 'Disable email notification during install and later. If your server has no smtp, this gets rid of an error during install.',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_ROOT,
    'aliases' => array('si'),
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
      'batch-id' => 'The batch id that will be processed.',
    ),
    'required-arguments' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_LOGIN,
  );

  $items['updatedb-batch-process'] = array(
    'description' => 'Perform update functions',
    'hidden' => TRUE,
    'arguments' => array(
      'batch-id' => 'The batch id that will be processed',
    ),
    'required-arguments' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
  );
  $items['core-global-options'] = array(
    'description' => 'All global options',
    'hidden' => TRUE,
    'topic' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'outputformat' => array(
      'default' => 'table',
      'pipe-format' => 'csv',
      'field-labels' => array('label' => 'Label', 'description' => 'Description'),
      'output-data-type' => 'format-table',
    ),
  );
  $items['core-quick-drupal'] = array(
    'description' => 'Download, install, serve and login to Drupal with minimal configuration and dependencies.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'aliases' => array('qd'),
    'arguments' => array(
      'site' => 'Short name for the site to be created - used as a directory name and as sqlite file name. Optional - if omitted timestamped "quick-drupal" directory will be used instead.',
      'projects' => 'A list of projects to download into the new site. If projects contain extensions (modules or themes) with the same name they will be enabled by default. See --enable option to control this behaviour further.',
    ),
    'examples' => array(
      'drush qd' => 'Download and install stable release of Drupal into a timestamped directory, start server, and open the site logged in as admin.',
      'drush qd --profile=minimal --dev --cache --core=drupal-8.x --yes' => 'Fire up dev release of Drupal site with minimal install profile.',
      'drush qd testsite devel --server=:8081/admin --browser=firefox --cache --yes' => 'Fire up stable release (using the cache) of Drupal site called "testsite", download and enable devel module, start a server on port 8081 and open /admin in firefox.',
      'drush qd commercesite --core=commerce_kickstart --profile=commerce_kickstart --cache --yes --watchdog' => 'Download and install the "Commerce Kickstart" distribution/install profile, display watchdog messages on the server console.',
      'drush qd --makefile=mysite.make' => 'Create and install a site from a makefile.',
    ),
  );
  // Add in options/engines.
  drush_core_quick_drupal_options($items);
  // Add in topics for engines
  $items += drush_get_engine_topics();
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

/**
 * @defgroup engines Engine types
 * @{
 */

/**
 * Implementation of hook_drush_engine_type_info().
 */
function core_drush_engine_type_info() {
  $info = array();
  $info['drupal'] = array();
  return $info;
}

/**
 * Implements hook_drush_engine_ENGINE_TYPE().
 */
function core_drush_engine_drupal() {
  $engines = array();
  $engines['batch'] = array();
  $engines['update'] = array();
  $engines['environment'] = array();
  $engines['site_install'] = array();
  $engines['user'] = array();
  $engines['pm'] = array();
  return $engines;
}

/**
 * @} End of "Engine types".
 */

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

  // Clear all caches in a new process. We just performed major surgery.
  drush_invoke_process('@self', 'cache-clear', array('all'));

  drush_log(dt('Finished performing updates.'), 'ok');
}

/**
 * Command handler. List pending DB updates.
 */
function drush_core_updatedb_status() {
  require_once DRUSH_DRUPAL_CORE . '/includes/install.inc';
  drupal_load_updates();
  drush_include_engine('drupal', 'update', drush_drupal_major_version());
  list($pending, $start) = updatedb_status();
  if (empty($pending)) {
    drush_log(dt("No database updates required"), 'ok');
  }
  return $pending;
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
      return dt("Return the filesystem path for modules/themes and other key folders.");
    case 'error:DRUSH_DRUPAL_DB_ERROR':
      $message = dt("Drush was not able to start (bootstrap) the Drupal database.\n");
      $message .= dt("Hint: This may occur when Drush is trying to:\n");
      $message .= dt(" * bootstrap a site that has not been installed or does not have a configured database. In this case you can select another site with a working database setup by specifying the URI to use with the --uri parameter on the command line. See `drush topic docs-aliases` for details.\n");
      $message .= dt(" * connect the database through a socket. The socket file may be wrong or the php-cli may have no access to it in a jailed shell. See http://drupal.org/node/1428638 for details.\n");
      $message .= dt("\nDrush was attempting to connect to: \n!credentials\n", array('!credentials' => _core_site_credentials(12)));
      return $message;
    case 'error:DRUSH_DRUPAL_BOOTSTRAP_ERROR':
      $message = dt("Drush was not able to start (bootstrap) Drupal.\n");
      $message .= dt("Hint: This error can only occur once the database connection has already been successfully initiated, therefore this error generally points to a site configuration issue, and not a problem connecting to the database.\n");
      $message .= dt("\nDrush was attempting to connect to: \n!credentials\n", array('!credentials' => _core_site_credentials(12)));
      return $message;
      break;
  }
}

// TODO: consolidate with SQL commands?
function _core_site_credentials($right_margin = 0) {
  // Leave some space on the right so that we can put the result into the
  // drush_log, which will again wordwrap the result.
  $original_columns = drush_get_context('DRUSH_COLUMNS', 80);
  drush_set_context('DRUSH_COLUMNS', $original_columns - $right_margin);
  $status_table = _core_site_status_table();
  $metadata = drush_get_command_format_metadata('core-status');
  $output = drush_format($status_table, $metadata, 'key-value');
  drush_set_context('DRUSH_COLUMNS', $original_columns);
  return $output;
}

function _core_site_credential_table($status_table) {
  $credentials = '';
  foreach ($status_table as $key => $value) {
    $credentials .= sprintf("  %-18s: %s\n", $key, $value);
  }
  return $credentials;
}

function _core_path_aliases($project = '') {
  $paths = array();
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
      if (drush_drupal_major_version() >= 8 && drush_get_context('DRUSH_BOOTSTRAP_PHASE') >= DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION) {
        $paths['%config-active'] = config_get_config_directory(CONFIG_ACTIVE_DIRECTORY);
        $paths['%config-staging'] = config_get_config_directory(CONFIG_STAGING_DIRECTORY);
      }
      if (drush_drupal_major_version() >= 7) {
        if (drush_get_context('DRUSH_BOOTSTRAP_PHASE') >= DRUSH_BOOTSTRAP_DRUPAL_SITE) {
          $paths['%files'] = variable_get('file_public_path', conf_path() . '/files');
          $private_path = variable_get('file_private_path', FALSE);
          if (!empty($private_path)) {
            $paths['%private'] = $private_path;
          }

        }
      }
      elseif (function_exists('file_directory_path')) {
        $paths['%files'] = file_directory_path();
      }
      if (function_exists('file_directory_temp')) {
        $paths['%temp'] = file_directory_temp();
      }
      // If the 'project' parameter was specified, then search
      // for a project (or a few) and add its path to the path list
      if (!empty($project)) {
        drush_include_engine('drupal', 'environment');
        $modules = drush_get_modules();
        foreach(explode(',', $project) as $target) {
          if (array_key_exists($target, $modules)) {
            $paths['%' . $target] = DRUPAL_ROOT . '/' . dirname($modules[$target]->filename);
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
    $status_table['drupal-version'] = drush_drupal_version();
    if ($site_root = drush_get_context('DRUSH_DRUPAL_SITE_ROOT')) {
      $status_table['uri'] = drush_get_context('DRUSH_URI');
      if ($creds = drush_get_context('DRUSH_DB_CREDENTIALS')) {
        $status_table['db-driver'] = $creds['driver'];
        if (!empty($creds['unix_socket'])) {
          $status_table['db-socket'] = $creds['unix_socket'];
        }
        else {
          $status_table['db-hostname'] = $creds['host'];
        }
        $status_table['db-username'] = $creds['user'];
        $status_table['db-name'] = $creds['name'];
        $status_table['db-password'] = $creds['pass'];
        if ($phase > DRUSH_BOOTSTRAP_DRUPAL_DATABASE) {
          $status_table['db-status'] = dt('Connected');
          if ($phase > DRUSH_BOOTSTRAP_DRUPAL_FULL) {
            $status_table['bootstrap'] = dt('Successful');
            if ($phase == DRUSH_BOOTSTRAP_DRUPAL_LOGIN) {
              drush_include_engine('drupal', 'user', drush_drupal_major_version());
              $status_table['user'] = drush_get_user_name();
            }
          }
        }
      }
    }
    $status_table['theme'] = drush_theme_get_default();
    $status_table['admin-theme'] = drush_theme_get_admin();
  }
  if ($php_bin = drush_get_option('php')) {
    $status_table['php-bin'] = $php_bin;
  }
  $status_table['php-os'] = PHP_OS;
  if ($php_ini_files = _drush_core_config_php_ini_files()) {
    $status_table['php-conf'] = $php_ini_files;
  }
  $status_table['drush-version'] = DRUSH_VERSION;
  $status_table['drush-conf'] = drush_flatten_array(drush_get_context_options('context-path', ''));
  $alias_files = _drush_sitealias_find_alias_files();
  $status_table['drush-alias-files'] = $alias_files;

  $paths = _core_path_aliases($project);
  if (!empty($paths)) {
    foreach ($paths as $target => $one_path) {
      $name = $target;
      if (substr($name,0,1) == '%') {
        $name = substr($name,1);
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

// Adjust the status output for any non-pipe output format
function _drush_core_status_format_table_data($output, $metadata) {
  if (drush_get_option('full', FALSE) == FALSE) {
    // Hide any key that begins with a %
    foreach ($output as $key => $value) {
      if ($key[0] == '%') {
        unset($output[$key]);
      }
    }
    // Hide 'Modules path' and 'Themes path' as well
    unset($output['modules']);
    unset($output['themes']);
    // Shorten the list of alias files if there are too many
    if (isset($output['drush-alias-files']) && count($output['drush-alias-files']) > 24) {
      $msg = dt("\nThere are !count more alias files. Run with --full to see the full list.", array('!count' => count($output['drush-alias-files']) - 1));
      $output['drush-alias-files'] = array($output['drush-alias-files'][0] , $msg);
    }
  }
  return $output;
}

/**
 * Command callback. Runs all cron hooks.
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

  $exec = drush_get_editor();
  if (count($all) == 1) {
    $filepath = current($all);
    return drush_shell_exec_interactive($exec, $filepath, $filepath);
  }
  else {
    $choice = drush_choice($all, 'Enter a number to choose which file to edit.', '!key');
    if ($choice !== FALSE) {
      $filepath = $all[$choice];
      drush_shell_exec_interactive($exec, $filepath, $filepath);
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
  $php_header = $php = $rcs_header = $rcs = $aliases_header = $aliases = $drupal_header = $drupal = array();
  $php = _drush_core_config_php_ini_files();
  if (!empty($php)) {
    if ($headers) {
      $php_header = array('phpini' => '-- PHP ini files --');
    }
  }
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
  return array_merge($php_header, $php, $rcs_header, $rcs, $aliases_header, $aliases, $drupal_header, $drupal);
}

function _drush_core_config_php_ini_files() {
  $ini_files = array();
  $ini_files[] = php_ini_loaded_file();
  if ($drush_ini = getenv('DRUSH_INI')) {
    if (file_exists($drush_ini)) {
      $ini_files[] = $drush_ini;
    }
  }
  foreach (array(DRUSH_BASE_PATH, '/etc/drush', drush_server_home() . '/.drush') as $ini_dir) {
    if (file_exists($ini_dir . "/php.ini")) {
      $ini_files[] = realpath($ini_dir . "/php.ini");
    }
    if (file_exists($ini_dir . "/drush.ini")) {
      $ini_files[] = realpath($ini_dir . "/drush.ini");
    }
  }
  return $ini_files;
}

/**
 * Command callback. Provides information from the 'Status Reports' admin page.
 */
function drush_core_requirements() {
  include_once DRUSH_DRUPAL_CORE . '/includes/install.inc';
  $severities = array(
    REQUIREMENT_INFO => t('Info'),
    REQUIREMENT_OK => t('OK'),
    REQUIREMENT_WARNING => t('Warning'),
    REQUIREMENT_ERROR => t('Error'),
  );

  drupal_load_updates();

  $requirements = module_invoke_all('requirements', 'runtime');
  // If a module uses "$requirements[] = " instead of
  // "$requirements['label'] = ", then build a label from
  // the title.
  foreach($requirements as $key => $info) {
    if (is_numeric($key)) {
      unset($requirements[$key]);
      $new_key = strtolower(str_replace(' ', '_', $info['title']));
      $requirements[$new_key] = $info;
    }
  }
  $ignore_requirements = drush_get_option_list('ignore');
  foreach ($ignore_requirements as $ignore) {
    unset($requirements[$ignore]);
  }
  ksort($requirements);

  $min_severity = drush_get_option('severity', -1);
  foreach($requirements as $key => $info) {
    $severity = array_key_exists('severity', $info) ? $info['severity'] : -1;
    $requirements[$key]['sid'] = $severity;
    $requirements[$key]['severity'] = $severities[$severity];
    if ($severity < $min_severity) {
      unset($requirements[$key]);
    }
  }
  return $requirements;
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
    $field_list = $args;
    $metadata = drush_get_command_format_metadata('core-status');
    foreach ($metadata['field-labels'] as $field_key => $field_label) {
      if (_drush_core_is_named_in_array($field_label, $args)) {
        $field_list[] = $field_key;
      }
    }
    foreach ($status_table as $key => $value) {
      if (!_drush_core_is_named_in_array($key, $field_list)) {
        unset($status_table[$key]);
      }
    }
  }
  return $status_table;
}

// Command callback. Show all global options. Exposed via topic command.
function drush_core_global_options() {
  drush_print(dt('These options are applicable to most drush commands.'));
  drush_print();
  $fake = drush_global_options_command(FALSE);
  return drush_format_help_section($fake, 'options');
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
 * Callback for core-quick-drupal command.
 */
function drush_core_quick_drupal() {
  $requests = FALSE;
  $make_projects = array();
  $args = func_get_args();
  $name = drush_get_option('use-name');
  drush_set_option('backend', TRUE);
  drush_set_option('strict', FALSE); // We fail option validation because do so much internal drush_invoke().
  $makefile = drush_get_option('makefile');
  if (drush_get_option('use-existing', FALSE)) {
    $root = drush_get_option('root', FALSE);
    if (!$root) {
      return drush_set_error('QUICK_DRUPAL_NO_ROOT_SPECIFIED', 'Must specify site with --root when using --use-existing.');
    }
    if (empty($name)) {
      $name = basename($root);
    }
    $base = dirname($root);
  }
  else {
    if (!empty($args) && empty($name)) {
      $name = array_shift($args);
    }
    if (empty($name)) {
      $name = 'quick-drupal-' . gmdate('YmdHis', $_SERVER['REQUEST_TIME']);
    }
    $root = drush_get_option('root', FALSE);
    $core = drush_get_option('core', 'drupal');
    $project_rename = $core;
    if ($root) {
      $base = dirname($root);
      $project_rename = basename($root);
    }
    else {
      $base = getcwd() . '/' . $name;
      $root = $base . '/' . $core;
    }
    if (!empty($makefile)) {
      // Invoke 'drush make $makefile'.
      $result = drush_invoke_process('@none', 'make', array($makefile, $root));
      if ($result['error_status'] != 0) {
        return drush_set_error('DRUSH_QD_MAKE', 'Could not make; aborting.');
      }
      $make_projects = array_diff(array_keys($result['object']['projects']), array('drupal'));
    }
    else {
      drush_mkdir($base);
      drush_set_option('destination', $base);
      drush_set_option('drupal-project-rename', $project_rename);
      if (drush_invoke('pm-download', array($core)) === FALSE) {
        return drush_set_error('QUICK_DRUPAL_CORE_DOWNLOAD_FAIL', 'Drupal core download/extract failed.');
      }
    }
  }
  if (!drush_get_option('db-url', FALSE)) {
    drush_set_option('db-url', 'sqlite:' . $base . '/' . $name . '.sqlite');
  }
  drush_set_option('root', $root);
  if (!drush_bootstrap_to_phase(DRUSH_BOOTSTRAP_DRUPAL_ROOT)) {
    return drush_set_error('QUICK_DRUPAL_ROOT_LOCATE_FAIL', 'Unable to locate Drupal root directory.');
  }
  if (!empty($args)) {
    $requests = pm_parse_arguments($args, FALSE);
  }
  if ($requests) {
    // Unset --destination, so that downloads go to the site directories.
    drush_unset_option('destination');
    if (drush_invoke('pm-download', $requests) === FALSE) {
      return drush_set_error('QUICK_DRUPAL_PROJECT_DOWNLOAD_FAIL', 'Project download/extract failed.');
    }
  }
  drush_invoke('site-install', array(drush_get_option('profile')));
  // Log in with the admin user.
  // TODO: If site-install is given a sites-subdir other than 'default',
  // then it will bootstrap to DRUSH_BOOTSTRAP_DRUPAL_SITE get the installer
  // to recognize the desired site directory. This somehow interferes
  // with our desire to bootstrap to DRUSH_BOOTSTRAP_DRUPAL_LOGIN here.
  // We could do the last few steps in a new process if uri is not 'default'.
  drush_set_option('user', '1');
  if (!drush_bootstrap_to_phase(DRUSH_BOOTSTRAP_DRUPAL_LOGIN)) {
    return drush_set_error('QUICK_DRUPAL_INSTALL_FAIL', 'Drupal core install failed.');
  }
  $enable = array_merge(pm_parse_arguments(drush_get_option('enable', $requests)), $make_projects);
  if (!empty($enable)) {
    if (drush_invoke('pm-enable', $enable) === FALSE) {
     return drush_set_error('QUICK_DRUPAL_PROJECT_ENABLE_FAIL', 'Project enable failed.');
    }
  }
  if (!drush_get_option('no-server', FALSE)) {
    if ($server = drush_get_option('server', '/')) {
      // Current CLI user is also the web server user, which is for development
      // only. Hence we can safely make the site directory writable. This makes
      // it easier to delete and edit settings.php.
      @chmod(conf_path(), 0700);
      drush_invoke('runserver', array($server));
    }
  }
  else {
    drush_print(dt('Login URL: ') . drush_invoke('user-login'));
  }
}

/**
 * Include options and engines for core-quick-drupal command, aggregated from
 * other command options that are available. We prefix option descriptons,
 * to make the long list more navigable.
 *
 * @param $items
 *   The core commandfile command array, by reference. Used to include
 *   site-install options and add options and engines for core-quick-drupal.
 */
function drush_core_quick_drupal_options(&$items) {
  $options = array(
    'core' => 'Drupal core to download. Defaults to "drupal" (latest stable version).',
    'use-existing' => 'Use an existing Drupal root, specified with --root. Overrides --core.',
    'profile' => 'The install profile to use. Defaults to standard.',
    'enable' => 'Specific extensions (modules or themes) to enable. By default, extensions with the same name as requested projects will be enabled automatically.',
    'server' => 'Host IP address and port number to bind to and path to open in web browser (hyphen to clear a default path), all elements optional. See runserver examples for shorthand.',
    'no-server' => 'Avoid starting runserver (and browser) for the created Drupal site.',
    'browser' => 'Optional name of a browser to open site in. If omitted the OS default browser will be used. Set --no-browser to disable.',
    'use-name' => array('hidden' => TRUE, 'description' => 'Overrides "name" argument.'),
    'makefile' => array('description' => 'Makefile to use. Makefile must specify which version of Drupal core to build.', 'example-value' => 'mysite.make', 'value' => 'optional'),
    'root' => array('description' => 'Path to Drupal root.', 'example-value' => '/path/to/root', 'value' => 'optional'),
  );
  $pm = pm_drush_command();
  foreach ($pm['pm-download']['options'] as $option => $description) {
    if (is_array($description)) {
      $description = $description['description'];
    }
    $options[$option] = 'Download option: ' . $description;
  }
  // Unset a few options that are not usable here, as we control them ourselves
  // or they are otherwise implied by the environment.
  unset($options['destination']);
  unset($options['drupal-project-rename']);
  unset($options['default-major']);
  unset($options['use-site-dir']);
  foreach ($items['site-install']['options'] as $option => $description) {
    if (is_array($description)) {
      $description = $description['description'];
    }
    $options[$option] = 'Site install option: ' . $description;
  }
  unset($options['sites-subdir']);
  $runserver = runserver_drush_command();
  foreach ($runserver['runserver']['options'] as $option => $description) {
    $options[$option] = 'Runserver option: ' . $description;
  }
  unset($options['user']);
  $items['core-quick-drupal']['options'] = $options;
  $items['core-quick-drupal']['engines'] = $pm['pm-download']['engines'];
}

/**
 * Command callback. Runs "naked" php scripts
 * and drush "shebang" scripts ("#!/usr/bin/env drush").
 *
 * @params
 *   Command arguments, optional. First argument is site name, remaining
 *   argument(s) are contrib modules to install.
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

/**
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
 * Given a target (e.g. @site:%modules), return the evaluated directory path.
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
    if (substr($target, 0, 1) != '@') {
      // drush_sitealias_evaluate_path() requires bootstrap to database.
      if (!drush_bootstrap_to_phase(DRUSH_BOOTSTRAP_DRUPAL_DATABASE)) {
        return drush_set_error('DRUPAL_SITE_NOT_FOUND', dt('You need to specify an alias or run this command within a drupal site.'));
      }
      $normalized_target = '@self:';
      if (substr($target, 0, 1) != '%') {
        $normalized_target .= '%';
      }
      $normalized_target .= $target;
    }
  }
  $additional_options = array();
  $values = drush_sitealias_evaluate_path($normalized_target, $additional_options, $local_only);
  if (isset($values[$component])) {
    // Hurray, we found the destination.
    return $values[$component];
  }
}

/**
 * Command callback.
 */
function drush_core_drupal_directory($target = 'root') {
  $path = _drush_core_directory($target, drush_get_option('component', 'path'), drush_get_option('local', FALSE));

  // If _drush_core_directory is working right, it will turn
  // %blah into the path to the item referred to by the key 'blah'.
  // If there is no such key, then no replacement is done.  In the
  // case of the dd command, we will consider it an error if
  // any keys are -not- replaced in _drush_core_directory.
  if ($path && (strpos($path, '%') === FALSE)) {
    return $path;
  }
  else {
    return drush_set_error('DRUSH_TARGET_NOT_FOUND', dt("Target '!target' not found.", array('!target' => $target)));
  }
}

/**
 * Called for `drush version` or `drush --version`
 */
function drush_core_version() {
  return DRUSH_VERSION;
}

/**
 * Command callback. Execute specified shell code. Often used by shell aliases
 * that start with !.
 */
function drush_core_execute() {
  // Get all of the args and options that appear after the command name.
  $args = drush_get_original_cli_args_and_options();
  for ($x = 0; $x < sizeof($args); $x++) {
    // escape all args except for command separators.
    if (!in_array($args[$x], array('&&', '||', ';'))) {
      $args[$x] = drush_escapeshellarg($args[$x]);
    }
  }
  $cmd = implode(' ', $args);
  if ($alias = drush_get_context('DRUSH_TARGET_SITE_ALIAS')) {
    $site = drush_sitealias_get_record($alias);
    if (!empty($site['remote-host'])) {
      // Remote, so execute an ssh command with a bash fragment at the end.
      $exec = drush_shell_proc_build($site, $cmd, TRUE);
      return drush_shell_proc_open($exec);
    }
  }
  // Must be a local command.
  return drush_shell_proc_open($cmd);
}
