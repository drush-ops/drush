<?php

/**
 * @file Drush sql commands
 */

/**
 * Implementation of hook_drush_help().
 */
function sql_drush_help($section) {
  switch ($section) {
    case 'meta:sql:title':
      return dt('SQL commands');
    case 'meta:sql:summary':
      return dt('Examine and modify your Drupal database.');
    case 'drush:sql-sanitize':
      return dt('Run sanitization operations on the current database. You can add more sanitization to this command by implementing hook_drush_sql_sync_sanitize().');
  }
}

/**
 * Implementation of hook_drush_command().
 */
function sql_drush_command() {
  $options['database'] = array(
    'description' => 'The DB connection key if using multiple connections in settings.php.',
    'example-value' => 'key',
  );
  $db_url['db-url'] = array(
    'description' => 'A Drupal 6 style database URL.',
    'example-value' => 'mysql://root:pass@127.0.0.1/db',
  );
  $options['target'] = array(
    'description' => 'The name of a target within the specified database connection. Defaults to \'default\'.',
    'example-value' => 'key',
    // Gets unhidden in help_alter(). We only want to show this to D7 users but have to
    // declare it here since some commands do not bootstrap fully.
    'hidden' => TRUE,
  );

  $items['sql-drop'] = array(
    'description' => 'Drop all tables in a given database.',
    'arguments' => array(
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'options' => array(
      'yes' => 'Skip confirmation and proceed.',
      'result-file' => array(
        'description' => 'Save to a file. The file should be relative to Drupal root. Recommended.',
        'example-value' => '/path/to/file',
      ),
    ) + $options + $db_url,
    'topics' => array('docs-policy'),
  );
  $items['sql-conf'] = array(
    'description' => 'Print database connection details using print_r().',
    'hidden' => TRUE,
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'options' => array(
      // @todo - no longer supported.
      // 'all' => 'Show all database connections, instead of just one.',
      'show-passwords' => 'Show database password.',
    ) + $options,
    'outputformat' => array(
      'default' => 'print-r',
      'pipe-format' => 'var_export',
      'private-fields' => 'password',
    ),
  );
  $items['sql-connect'] = array(
    'description' => 'A string for connecting to the DB.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'options' => $options + $db_url,
    'examples' => array(
      '`drush sql-connect` < example.sql' => 'Import sql statements from a file into the current database.',
    ),
  );
  $items['sql-create'] = array(
    'description' => 'Create a database.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'examples' => array(
      'drush sql-create' => 'Create the database for the current site.',
      'drush @site.test sql-create' => 'Create the database as specified for @site.test.',
      'drush sql-create --db-su=root --db-su-pw=rootpassword --db-url="mysql://drupal_db_user:drupal_db_password@127.0.0.1/drupal_db"' =>
        'Create the database as specified in the db-url option.'
    ),
    'options' => array(
      'db-su' => 'Account to use when creating a new database. Optional.',
      'db-su-pw' => 'Password for the "db-su" account. Optional.',
    ) + $options + $db_url,
  );
  $items['sql-dump'] = array(
    'callback' => 'drush_sql_dump_execute',
    'description' => 'Exports the Drupal DB as SQL using mysqldump or equivalent.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'examples' => array(
      'drush sql-dump --result-file=../18.sql' => 'Save SQL dump to the directory above Drupal root.',
      'drush sql-dump --skip-tables-key=common' => 'Skip standard tables. @see example.drushrc.php',
    ),
    'options' => array(
      'result-file' => array(
        'description' => 'Save to a file. The file should be relative to Drupal root. If --result-file is provided with no value, then date based filename will be created under ~/drush-backups directory.',
        'example-value' => '/path/to/file',
        'value' => 'optional',
      ),
      'skip-tables-key' => 'A key in the $skip_tables array. @see example.drushrc.php. Optional.',
      'structure-tables-key' => 'A key in the $structure_tables array. @see example.drushrc.php. Optional.',
      'tables-key' => 'A key in the $tables array. Optional.',
      'skip-tables-list' => 'A comma-separated list of tables to exclude completely. Optional.',
      'structure-tables-list' => 'A comma-separated list of tables to include for structure, but not data. Optional.',
      'tables-list' => 'A comma-separated list of tables to transfer. Optional.',
      'ordered-dump' => 'Use this option to output ordered INSERT statements in the sql-dump.Useful when backups are managed in a Version Control System. Optional.',
      'create-db' => array('hidden' => TRUE, 'description' => 'Omit DROP TABLE statements. Postgres and Oracle only.  Used by sql-sync, since including the DROP TABLE statements interfere with the import when the database is created.'),
      'data-only' => 'Dump data without statements to create any of the schema.',
      'ordered-dump' => 'Order by primary key and add line breaks for efficient diff in revision control. Also, faster rsync. Slows down the dump. Mysql only.',
      'gzip' => 'Compress the dump using the gzip program which must be in your $PATH.',
    ) + $options + $db_url,
  );
  $items['sql-query'] = array(
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'description' => 'Execute a query against the site database.',
    'examples' => array(
      'drush sql-query "SELECT * FROM users WHERE uid=1"' => 'Browse user record. Table prefixes, if used, must be added to table names by hand.',
      'drush sql-query --db-prefix "SELECT * FROM {users} WHERE uid=1"' => 'Browse user record. Table prefixes are honored.  Caution: curly-braces will be stripped from all portions of the query.',
      '`drush sql-connect` < example.sql' => 'Import sql statements from a file into the current database.',
      'drush sql-query --file=example.sql' => 'Alternate way to import sql statements from a file.',
    ),
    'arguments' => array(
       'query' => 'An SQL query. Ignored if \'file\' is provided.',
    ),
    'options' => array(
      'result-file' => array(
        'description' => 'Save to a file. The file should be relative to Drupal root. Optional.',
        'example-value' => '/path/to/file',
      ),
      'file' => 'Path to a file containing the SQL to be run.',
      'extra' => array(
        'description' => 'Add custom options to the mysql command.',
        'example-value' => '--skip-column-names',
      ),
      'db-prefix' => 'Enable replacement of braces in your query.',
      'db-spec' => array(
        'description' => 'A database specification',
        'hidden' => TRUE, // Hide since this is only used with --backend calls.
      )
    ) + $options + $db_url,
    'aliases' => array('sqlq'),
  );
  $items['sql-sync'] = array(
    'description' => 'Copy and import source database to target database. Transfers via rsync.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'drush dependencies' => array('core'), // core-rsync.
    'examples' => array(
      'drush sql-sync @prod @dev' => 'Copy the DB defined in sites/prod to the DB in sites/dev.',
    ),
    'arguments' => array(
      'from' => 'Name of subdirectory within /sites or a site-alias.',
      'to' => 'Name of subdirectory within /sites or a site-alias.',
    ),
    'options' => array(
      'skip-tables-key' => 'A key in the $skip_tables array. @see example.drushrc.php. Optional.',
      'skip-tables-list' => 'A comma-separated list of tables to exclude completely. Optional.',
      'structure-tables-key' => 'A key in the $structure_tables array. @see example.drushrc.php. Optional.',
      'structure-tables-list' => 'A comma-separated list of tables to include for structure, but not data. Optional.',
      'tables-key' => 'A key in the $tables array. Optional.',
      'tables-list' => 'A comma-separated list of tables to transfer. Optional.',
      'cache' => 'Skip dump if result file exists and is less than "cache" hours old. Optional; default is 24 hours.',
      'no-cache' => 'Do not cache the sql-dump file.',
      'no-dump' => 'Do not dump the sql database; always use an existing dump file.',
      'source-db-url' => 'Database specification for source system to dump from.',
      'source-remote-port' => 'Override sql database port number in source-db-url. Optional.',
      'source-remote-host' => 'Remote machine to run sql-dump file on. Optional; default is local machine.',
      'source-dump' => 'Path to dump file. Optional; default is to create a temporary file.',
      'source-database' => 'A key in the $db_url (D6) or $databases (D7+) array which provides the data.',
      'source-target' => array(
          'description' => 'A key within the SOURCE database identifying a particular server in the database group.',
          'example-value' => 'key',
          // Gets unhidden in help_alter(). We only want to show to D7 users but have to
          // declare it here since this command does not bootstrap fully.
          'hidden' => TRUE,
      ),
      'target-db-url' => '',
      'target-remote-port' => '',
      'target-remote-host' => '',
      'target-dump' => '',
      'target-database' => 'A key in the $db_url (D6) or $databases (D7+) array which shall receive the data.',
      'target-target' => array(
          'description' => 'Oy. A key within the TARGET database identifying a particular server in the database group.',
          'example-value' => 'key',
          // Gets unhidden in help_alter(). We only want to show to D7 users but have to
          // declare it here since this command does not bootstrap fully.
          'hidden' => TRUE,
      ),
      'temp' => 'Use a temporary file to hold dump files.  Implies --no-cache.',
      'dump-dir' => 'Directory to store sql dump files in when --source-dump or --target-dump are not used.',
      'create-db' => 'Create a new database before importing the database dump on the target machine.',
      'db-su' => array(
        'description' => 'Account to use when creating a new database. Optional.',
        'example-value' => 'root',
      ),
      'db-su-pw' => array(
        'description' => 'Password for the "db-su" account. Optional.',
        'example-value' => 'pass',
      ),
      'no-ordered-dump' => 'Do not pass --ordered-dump to sql-dump.  sql-sync orders the dumpfile by default in order to increase the efficiency of rsync.',
      'sanitize' => 'Obscure email addresses and reset passwords in the user table post-sync. Optional.',
    ),
    'sub-options' => array(
      'sanitize' => array(
        'sanitize-password' => 'The password to assign to all accounts in the sanitization operation, or "no" to keep passwords unchanged.  Default is "password".',
        'sanitize-email' => 'The pattern for test email addresses in the sanitization operation, or "no" to keep email addresses unchanged.  May contain replacement patterns %uid, %mail or %name.  Default is "user+%uid@localhost".',
        'confirm-sanitizations' => 'Prompt yes/no after importing the database, but before running the sanitizations',
      ),
    ),
    'topics' => array('docs-aliases', 'docs-policy', 'docs-example-sync-via-http', 'docs-example-sync-extension'),
  );
  $items['sql-cli'] = array(
    'description' => "Open a SQL command-line interface using Drupal's credentials.",
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'options' => array(
      'A' => 'Skip reading table information. This gives a quicker start of mysql.',
    ) + $options + $db_url,
    'aliases' => array('sqlc'),
    'examples' => array(
      'drush sql-cli' => "Open a SQL command-line interface using Drupal's credentials.",
      'drush sql-cli -A' => "Open a SQL command-line interface using Drupal's credentials and skip reading table information.",
    ),
    'remote-tty' => TRUE,
  );
  $items['sql-sanitize'] = array(
    'description' => "Run sanitization operations on the current database.",
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'options' => array(
      'sanitize-password' => 'The password to assign to all accounts in the sanitization operation, or "no" to keep passwords unchanged.  Default is "password".',
      'sanitize-email' => 'The pattern for test email addresses in the sanitization operation, or "no" to keep email addresses unchanged.  May contain replacement patterns %uid, %mail or %name.  Default is "user+%uid@localhost".',
    ) + $db_url,
    'aliases' => array('sqlsan'),
  );
  return $items;
}

/**
 * Implements hook_drush_help_alter().
 */
function sql_drush_help_alter(&$command) {
  // Drupal 7+ only options.
  if (drush_drupal_major_version() >= 7) {
    if ($command['command'] == 'sql-sync') {
      unset($command['options']['source-target']['hidden'], $command['options']['target-target']['hidden']);
    }
    elseif ($command['commandfile'] == 'sql') {
      unset($command['options']['target']['hidden']);
    }
  }
}

/**
 * Command argument complete callback.
 *
 * @return
 *  Array of available site aliases.
 */
function sql_sql_sync_complete() {
  return array('values' => array_keys(_drush_sitealias_all_list()));
}

/**
 * Check whether further bootstrap is needed. If so, do it.
 */
function drush_sql_bootstrap_further() {
  if (!drush_get_option(array('db-url', 'db-spec'))) {
    drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION);
  }
}

/**
 * Command callback. Displays the Drupal site's database connection string.
 */
function drush_sql_conf() {
  // Under Drupal 7, if the database is configured but empty, then
  // DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION will throw an exception.
  // If this happens, we'll just catch it and continue.
  // TODO:  Fix this in the bootstrap, per http://drupal.org/node/1996004
  try {
    drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION);
  }
  catch (Exception $e) {
  }
  // @todo dropped support for --all.
  $sql = drush_sql_get_class();
  return $sql->db_spec;
}

/**
 * Command callback. Emits a connect string.
 */
function drush_sql_connect() {
  drush_sql_bootstrap_further();
  $sql = drush_sql_get_class();
  return $sql->connect();
}

/**
 * Command callback. Create a database.
 */
function drush_sql_create() {
  $sql = drush_sql_get_class();
  // Prompt for confirmation.
  if (!drush_get_context('DRUSH_SIMULATE')) {
    // @todo odd - maybe for sql-sync.
    $txt_destination = (isset($db_spec['remote-host']) ? $db_spec['remote-host'] . '/' : '') . $sql->db_spec['database'];
    drush_print(dt("Creating database !target. Any possible existing database will be dropped!", array('!target' => $txt_destination)));

    if (!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_user_abort();
    }
  }

  return $sql->createdb();
}


/**
 * Command callback. Outputs the entire Drupal database in SQL format using mysqldump or equivalent.
 */
function drush_sql_dump_execute() {
  drush_sql_bootstrap_further();
  $sql = drush_sql_get_class();
  list($exec, $file) = $sql->dump(drush_get_option('result-file', FALSE));

  // Avoid the php memory of the $output array in drush_shell_exec().
  if (!$return = drush_op_system($exec)) {
    if ($file) {
      drush_log(dt('Database dump saved to !path', array('!path' => $file)), 'success');
    }
  }
  else {
    return drush_set_error('DRUSH_SQL_DUMP_FAIL', 'Database dump failed');
  }
}

/**
 * Construct an array that places table names in appropriate
 * buckets based on whether the table is to be skipped, included
 * for structure only, or have structure and data dumped.
 * The keys of the array are:
 * - skip: tables to be skipped completed in the dump
 * - structure: tables to only have their structure i.e. DDL dumped
 * - tables: tables to have structure and data dumped
 *
 * @return array
 *   An array of table names with each table name in the appropriate
 *   element of the array.
 */
function drush_sql_get_table_selection() {
  // Skip large core tables if instructed.  Used by 'sql-drop/sql-dump/sql-sync' commands.
  $skip_tables = _drush_sql_get_raw_table_list('skip-tables');
  // Skip any structure-tables as well.
  $structure_tables = _drush_sql_get_raw_table_list('structure-tables');
  // Dump only the specified tables.  Takes precedence over skip-tables and structure-tables.
  $tables = _drush_sql_get_raw_table_list('tables');

  return array('skip' => $skip_tables, 'structure' => $structure_tables, 'tables' => $tables);
}

/**
 * Get a list of all table names and expand input that may contain
 * wildcards (`*`) if necessary so that the array returned only contains valid
 * table names i.e. actual tables that exist, without a wildcard.
 *
 * @return array
 *   An array of tables with each table name in the appropriate
 *   element of the array.
 */
function drush_sql_get_expanded_table_selection() {
  $table_selection = drush_sql_get_table_selection();
  // Get the existing table names in the specified database.
  $sql = drush_sql_get_class();
  $db_tables = $sql->listTables();
  if (isset($table_selection['skip'])) {
    $table_selection['skip'] = _drush_sql_expand_and_filter_tables($table_selection['skip'], $db_tables);
  }
  if (isset($table_selection['structure'])) {
    $table_selection['structure'] = _drush_sql_expand_and_filter_tables($table_selection['structure'], $db_tables);
  }
  if (isset($table_selection['tables'])) {
    $table_selection['tables'] = _drush_sql_expand_and_filter_tables($table_selection['tables'], $db_tables);
  }
  return $table_selection;
}

/**
 * Expand wildcard tables.
 *
 * @param array $tables
 *   An array of table names, some of which may contain wildcards (`*`).
 * @param array $db_tables
 *   An array with all the existing table names in the current database.
 * @return
 *   $tables array with wildcards resolved to real table names.
 */
function drush_sql_expand_wildcard_tables($tables, $db_tables) {
  // Table name expansion based on `*` wildcard.
  $expanded_db_tables = array();
  foreach ($tables as $k => $table) {
    // Only deal with table names containing a wildcard.
    if (strpos($table, '*') !== FALSE) {
      $pattern = '/^' . str_replace('*', '.*', $table) . '$/i';
      // Merge those existing tables which match the pattern with the rest of
      // the expanded table names.
      $expanded_db_tables += preg_grep($pattern, $db_tables);
    }
  }
  return $expanded_db_tables;
}

/**
 * Filters tables.
 *
 * @param array $tables
 *   An array of table names to filter.
 * @param array $db_tables
 *   An array with all the existing table names in the current database.
 * @return
 *   An array with only valid table names (i.e. all of which actually exist in
 *   the database).
 */
function drush_sql_filter_tables($tables, $db_tables) {
  // Ensure all the tables actually exist in the database.
  foreach ($tables as $k => $table) {
    if (!in_array($table, $db_tables)) {
      unset($tables[$k]);
    }
  }

  return $tables;
}

/**
 * Given the table names in the input array that may contain wildcards (`*`),
 * expand the table names so that the array returned only contains table names
 * that exist in the database.
 *
 * @param array $tables
 *   An array of table names where the table names may contain the
 *   `*` wildcard character.
 * @param array $db_tables
 *   The list of tables present in a database.
  $db_tables = _drush_sql_get_db_table_list($db_spec, $site_record);
 * @return array
 *   An array of tables with non-existant tables removed.
 */
function _drush_sql_expand_and_filter_tables($tables, $db_tables) {
  $expanded_tables = drush_sql_expand_wildcard_tables($tables, $db_tables);
  $tables = drush_sql_filter_tables(array_merge($tables, $expanded_tables), $db_tables);
  $tables = array_unique($tables);
  sort($tables);
  return $tables;
}

/**
 * Consult the specified options and return the list of tables
 * specified.
 *
 * @param option_name
 *   The option name to check: skip-tables, structure-tables
 *   or tables.  This function will check both *-key and *-list,
 *   and, in the case of sql-sync, will also check target-*
 *   and source-*, to see if an alias set one of these options.
 * @returns array
 *   Returns an array of tables based on the first option
 *   found, or an empty array if there were no matches.
 */
function _drush_sql_get_raw_table_list($option_name) {
  foreach(array('' => 'cli', 'target-,,source-' => NULL) as $prefix_list => $context) {
    foreach(explode(',',$prefix_list) as $prefix) {
      $key_list = drush_get_option($prefix . $option_name . '-key', NULL, $context);
      foreach(explode(',', $key_list) as $key) {
        $all_tables = drush_get_option($option_name, array());
        if (array_key_exists($key, $all_tables)) {
          return $all_tables[$key];
        }
        if ($option_name != 'tables') {
          $all_tables = drush_get_option('tables', array());
          if (array_key_exists($key, $all_tables)) {
            return $all_tables[$key];
          }
        }
      }
      $table_list = drush_get_option($prefix . $option_name . '-list', NULL, $context);
      if (isset($table_list)) {
        return empty($table_list) ? array() : explode(',', $table_list);
      }
    }
  }

  return array();
}

/**
 * Command callback. Executes the given SQL query on the Drupal database.
 */
function drush_sql_query($query = NULL) {
  drush_sql_bootstrap_further();
  $filename = drush_get_option('file', NULL);
  // Enable prefix processing when db-prefix option is used.
  if (drush_get_option('db-prefix')) {
    drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_DATABASE);
  }
  $sql = drush_sql_get_class(drush_get_option('db-spec'));
  $result = $sql->query($query, $filename);
  if (!$result) {
    return drush_set_error('DRUSH_SQL_NO_QUERY', dt('Query failed.'));
  }
  return TRUE;
}

/**
 * Drops all tables in the database.
 */
function drush_sql_drop() {
  drush_sql_bootstrap_further();
  $sql = drush_sql_get_class();
  if (!drush_confirm(dt('Do you really want to drop all tables in the database !db?', array('!db' => $sql->db_spec['database'])))) {
    return drush_user_abort();
  }
  $tables = $sql->listTables();
  $sql->drop($tables);
}

function drush_sql_cli() {
  drush_sql_bootstrap_further();
  $sql = drush_sql_get_class();
  drush_shell_proc_open($sql->connect());
}

/**
 * Command callback. Run's the sanitization operations on the current database.
 *
 * @see hook_drush_sql_sync_sanitize() for adding custom sanitize routines.
 */
function drush_sql_sanitize() {
  drush_sql_bootstrap_further();
  drush_include(DRUSH_BASE_PATH . '/commands/sql', 'sync.sql');
  drush_command_invoke_all('drush_sql_sync_sanitize', 'default');
  $options = drush_get_context('post-sync-ops');
  if (!empty($options)) {
    if (!drush_get_context('DRUSH_SIMULATE')) {
      $messages = _drush_sql_get_post_sync_messages();
      if ($messages) {
        drush_print();
        drush_print($messages);
      }
    }
  }
  if (!drush_confirm(dt('Do you really want to sanitize the current database?'))) {
    return drush_user_abort();
  }

  $sanitize_query = '';
  foreach($options as $id => $data) {
    $sanitize_query .= $data['query'] . " ";
  }
  if ($sanitize_query) {
    $sql = drush_sql_get_class();
    $result = $sql->query($query, $filename);
    // @todo should method set an error?
    if (!$result) {
      return drush_set_error('DRUSH_SQL_NO_QUERY', dt('Query failed.'));
    }
  }
}

//////////////////////////////////////////////////////////////////////////////
// SQL SERVICE HELPERS

function _drush_sql_get_all_db_specs() {
  switch (drush_drupal_major_version()) {
    case 6:
      if (!isset($GLOBALS['db_url'])) {
        return NULL;
      }
      return drush_sitealias_convert_db_from_db_url($GLOBALS['db_url']);

    default:
      if (!isset($GLOBALS['databases'])) {
        return NULL;
      }
      return $GLOBALS['databases'];
  }
}

/**
 * Determine where to store an sql dump file.  This
 * function is called by sql-sync.
 *
 * @param array $site_record
 *
 * @return string Full path to the sql-dump file.
 */
function drush_sql_dump_file(&$site_record) {
  $site_record['dump-is-temp'] = FALSE;
  // If the user has set the --{prefix}-dump option, then
  // use the exact name provided.
  $dump_file = drush_sitealias_get_path_option($site_record, 'dump');
  if (!isset($dump_file)) {
    $databases = sitealias_get_databases_from_record($site_record);
    if (isset($databases)) {
      $db_spec = $databases['default']['default'];
      // Make a base filename pattern to use to name the dump file
      $filename_pattern = $db_spec['database'];
      if (isset($db_spec['remote-host'])) {
        $filename_pattern = $db_spec['remote-host'] . '_' . $filename_pattern;
      }
    }
    // If the user has set the --dump-dir option, then
    // store persistant sql dump files there.
    $dump_dir = drush_sitealias_get_path_option($site_record, 'dump-dir');
    $use_temp_file = drush_sitealias_get_path_option($site_record, 'temp') || !$dump_dir;
    $remote = isset($site_record['remote-host']);
    // If this is a remote site, try to find a writable tmpdir.
    if (!isset($dump_dir) && $remote) {
      // If the alias is remote, we'll add on the 'sql' suffix.
      // As mentioned in drush_tempnam, providing a suffix removes
      // the guarentee of tempnam to return a unique filename;
      // however, when the file is going to be used on a remote
      // system, there is no such guarentee anyway, so we might
      // as well include it.
      $suffix = '.sql';
      $remote_site = $site_record;
      unset($remote_site['root']);
      unset($remote_site['uri']);
      $result = drush_invoke_process($site_record, 'php-eval', array('return drush_find_tmp();'), array(), array('integrate' => FALSE, 'override-simulated' => TRUE));
      // If the call to invoke process does not work for some reason
      // (e.g. drush not installed on the target machine),
      // then we will just presume that the tmp dir is '/tmp'.
      if (!$result || !empty($result['error_status']) || empty($result['object'])) {
        $dump_dir = '/tmp';
      }
      else {
        $dump_dir = $result['object'];
      }
    }
    if ($use_temp_file) {
      $dump_file = drush_tempnam($filename_pattern, $dump_dir, $remote ? '.sql' : '');
      // If $dump_dir does not exist, tempname will use the system
      // directory instead.  That is the behavior we want on the local
      // system, but on remote systems, force the result back to the
      // specified directory.
      if ($remote) {
        $dump_file = $dump_dir . '/' . basename($dump_file);
      }
      $site_record['dump-is-temp'] = TRUE;
    }
    else {
      $dump_file = $dump_dir . '/' . $filename_pattern . '.sql';
    }
  }

  return $dump_file;
}

/**
 * Call from a pre-sql-sync hook to register an sql
 * query to be executed in the post-sql-sync hook.
 * @see drush_sql_pre_sql_sync() and @see drush_sql_post_sql_sync().
 *
 * @param $id
 *   String containing an identifier representing this
 *   operation.  This id is not actually used at the
 *   moment, it is just used to fufill the contract
 *   of drush contexts.
 * @param $message
 *   String with the confirmation message that describes
 *   to the user what the post-sync operation is going
 *   to do.  This confirmation message is printed out
 *   just before the user is asked whether or not the
 *   sql-sync operation should be continued.
 * @param $query
 *   String containing the sql query to execute.  If no
 *   query is provided, then the confirmation message will
 *   be displayed to the user, but no action will be taken
 *   in the post-sync hook.  This is useful for drush modules
 *   that wish to provide their own post-sync hooks to fix
 *   up the target database in other ways (e.g. through
 *   Drupal APIs).
 */
function drush_sql_register_post_sync_op($id, $message, $query = NULL) {
  $options = drush_get_context('post-sync-ops');

  $options[$id] = array('message' => $message, 'query' => $query);

  drush_set_context('post-sync-ops', $options);
}

/**
 * Builds a confirmation message for all post-sync operations.
 *
 * @return string
 *   All post-sync operation messages concatenated together.
 */
function _drush_sql_get_post_sync_messages() {
  $messages = FALSE;

  $options = drush_get_context('post-sync-ops');
  if (!empty($options)) {
    $messages = dt('The following post-sync operations will be done on the destination:') . "\n";

    foreach($options as $id => $data) {
      $messages .= "  * " . $data['message'] . "\n";
    }
  }

  return $messages;
}

/**
 * Wrapper for drush_get_class; instantiates an instance of the version-specific
 * Sql class suitable for the current version of Drupal and DB driver.
 *
 * @return Drush\Sql\SqlBase
 */
function drush_sql_get_class($db_spec = NULL) {
  $sql = new Drush\Sql\SQLBase($db_spec);
  $sql_driver_specific = drush_get_class('Drush\Sql\Sql', func_get_args(), array($sql->scheme(), drush_drupal_major_version()));
  return $sql_driver_specific;
}
