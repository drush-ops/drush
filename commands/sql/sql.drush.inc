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
  }
}

/**
 * Implementation of hook_drush_command().
 */
function sql_drush_command() {
  $options['database'] = array(
    'description' => 'The DB connection key if using multiple connections in settings.php.',
    'example-value' => 'extra',
  );
  if (drush_drupal_major_version() >= 7) {
    $options['target'] = 'The name of a target within the specified database.';
  }
  $db_url['db-url'] = array(
    'description' => 'A Drupal 6 style database URL. Only required for initial install - not re-install.',
    'example-value' => 'mysql://root:pass@127.0.0.1/db',
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
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
    'options' => array(
      'all' => 'Show all database connections, instead of just one.',
      'show-passwords' => 'Show database password.',
    ) + $options,
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
      'extra' => 'Add custom options to the mysql command.',
      'db-prefix' => 'Enable replacement of braces in your query.',
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
      'structure-tables-key' => 'A key in the $structure_tables array. @see example.drushrc.php. Optional.',
      'tables-key' => 'A key in the $tables array. Optional.',
      'tables-list' => 'A comma-separated list of tables to transfer. Optional.',
      'cache' => 'Skip dump if result file exists and is less than "cache" hours old. Optional; default is 24 hours.',
      'no-cache' => 'Do not cache the sql-dump file.',
      'no-dump' => 'Do not dump the sql database; always use an existing dump file.',
      'source-db-url' => 'Database specification for source system to dump from.',
      'source-remote-port' => 'Override sql database port number in source-db-url. Optional.',
      'source-remote-host' => 'Remote machine to run sql-dump file on. Optional; default is local machine.',
      'source-dump' => 'Path to dump file. Optional; default is to create a temporary file.',
      'target-database' => 'A key in the $db_url (D6) or $databases (D7+) array which provides the data.',
      'source-target' => 'Oy. A key within the --target_database identifying a particular server in the database group.',
      'target-db-url' => '',
      'target-remote-port' => '',
      'target-remote-host' => '',
      'target-dump' => '',
      'target-database' => 'A key in the $db_url (D6) or $databases (D7+) array which shall receive the data.',
      'target-target' => 'Oy. A key within the --target_database identifying a particular server in the database group.',
      'temp' => 'Use a temporary file to hold dump files.  Implies --no-cache.',
      'dump-dir' => 'Directory to store sql dump files in when --source-dump or --target-dump are not used.  Takes precedence over --temp.',
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
  if (drush_drupal_major_version() >= 7) {

    $items['sql-sync']['options'] += array(
      'source-target' => 'The name of a target within the SOURCE database.',
      'destination-target' => 'The name of a target within the specified DESTINATION database.',
    );
  }
  $items['sql-cli'] = array(
    'description' => "Open a SQL command-line interface using Drupal's credentials.",
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'options' => $options + $db_url,
    'aliases' => array('sqlc'),
  );
  $items['sql-sanitize'] = array(
    'description' => "Run sanitization operations on the current database.",
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'hidden' => TRUE,
    'options' => array(
      'sanitize-password' => 'The password to assign to all accounts in the sanitization operation, or "no" to keep passwords unchanged.  Default is "password".',
      'sanitize-email' => 'The pattern for test email addresses in the sanitization operation, or "no" to keep email addresses unchanged.  May contain replacement patterns %uid, %mail or %name.  Default is "user+%uid@localhost".',
    ) + $db_url,
    'aliases' => array('sqlsan'),
  );
  return $items;
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
  if (!drush_get_option('db-url')) {
    drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION);
  }
}

/**
 * Command callback. Displays the Drupal site's database connection string.
 */
function drush_sql_conf() {
  if (drush_get_option('db-url', FALSE)) {
    $db_spec['db-url'] = $GLOBALS['db_url'];
  }
  elseif (drush_get_option('all', FALSE)) {
    $db_spec = _drush_sql_get_all_db_specs();
  }
  if (!isset($db_spec)) {
    $db_spec = _drush_sql_get_db_spec();
  }
  $return = $db_spec;
  if (!drush_get_option('show-passwords', FALSE)) {
    drush_unset_recursive($db_spec, 'password');
  }
  drush_print_r($db_spec);
  return $return;
}

/**
 * Command callback. Emits a connect string for mysql or pgsql.
 */
function _drush_sql_connect($db_spec = NULL) {
  switch (_drush_sql_get_scheme($db_spec)) {
    case 'mysql':
      $command = 'mysql';
      break;
    case 'pgsql':
      $command = 'psql';
      break;
    case 'sqlite':
      $command = 'sqlite3';
      break;
    case 'sqlsrv':
      $command = 'sqlcmd';
      break;
    case 'oracle':
      // use rlwrap if available for readline support
      if ($handle = popen('rlwrap -v', 'r')) {
        $command = 'rlwrap sqlplus';
        pclose($handle);
      }
      else {
        $command = 'sqlplus';
      }
      break;
  }
  $command .= _drush_sql_get_credentials($db_spec);
  return $command;
}

function drush_sql_connect() {
  drush_sql_bootstrap_further();
  $connect = _drush_sql_connect();
  drush_print($connect);
  return $connect;
}

/**
 * Command callback. Create a database.
 */
function drush_sql_create() {
  $db_spec = _drush_sql_get_db_spec();

  // Prompt for confirmation.
  if (!drush_get_context('DRUSH_SIMULATE')) {
    $txt_destination = (isset($db_spec['remote-host']) ? $db_spec['remote-host'] . '/' : '') . $db_spec['database'];
    drush_print(dt("Creating database !target. Any possible existing database will be dropped!", array('!target' => $txt_destination)));

    if (!drush_confirm(dt('Do you really want to continue?'))) {
      return drush_user_abort();
    }
  }

  return _drush_sql_create($db_spec);
}

function _drush_sql_create($db_spec) {
    $sql = drush_sql_build_createdb_sql($db_spec, TRUE);
    // Get credentials to connect to the server, but not the database which we
    // are about to DROP. @see _drush_sql_get_credentials().
    $create_db_spec = $db_spec;
    unset($create_db_spec['database']);
    $create_db_su = drush_sql_su($create_db_spec);
    return _drush_sql_query($sql, $create_db_su);
}

/**
 * Command callback. Outputs the entire Drupal database in SQL format using mysqldump.
 */
function drush_sql_dump_execute() {
  drush_sql_bootstrap_further();
  list($exec, $file) = drush_sql_dump();
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

function drush_sql_get_table_selection() {
  // Skip large core tables if instructed.  Also used by 'sql-sync' command.
  $skip_tables = _drush_sql_get_table_list('skip-tables');
  // Skip any structure-tables as well.
  $structure_tables = _drush_sql_get_table_list('structure-tables');
  // Dump only the specified tables.  Takes precedence over skip-tables and structure-tables.
  $tables = _drush_sql_get_table_list('tables');

  return array('skip' => $skip_tables, 'structure' => $structure_tables, 'tables' => $tables);
}

/**
 * Build a mysqldump/pg_dump/sqlite statement.
 *
 * @param db_spec
 *   For /D6, a $db_url. For D7+, a target in the default DB connection.
 * @return array
 *   An array with items.
 *     1. A mysqldump/pg_dump/sqlite statement that is ready for executing.
 *     2. The filepath where the dump will be saved.
 */
function drush_sql_dump($db_spec = NULL) {
  return drush_sql_build_dump_command(drush_sql_get_table_selection(), $db_spec, drush_get_option('result-file', FALSE));
}

/**
 * Build a mysqldump/pg_dump/sqlite statement.
 *
 * @param array $table_selection
 *   Supported keys: 'skip', 'structure', 'tables'.
 * @param db_spec
 *   For D5/D6, a $db_url. For D7, a target in the default DB connection.
 * @param file
 *   Destination for the dump file.
 * @return array
 *   An array with items.
 *     1. A mysqldump/pg_dump/sqlite statement that is ready for executing.
 *     2. The filepath where the dump will be saved.
 */
function drush_sql_build_dump_command($table_selection, $db_spec = NULL, $file = FALSE) {
  $skip_tables = $table_selection['skip'];
  $structure_tables = $table_selection['structure'];
  $tables = $table_selection['tables'];

  $ignores = array();
  $skip_tables  = array_merge($structure_tables, $skip_tables);
  $data_only = drush_get_option('data-only');
  // The ordered-dump option is only supported by MySQL for now.
  // @todo add documention once a hook for drush_get_option_help() is available.
  // @see drush_get_option_help() in drush.inc
  $ordered_dump = drush_get_option('ordered-dump');

  if (is_null($db_spec)) {
    $db_spec = _drush_sql_get_db_spec();
  }
  $database = $db_spec['database'];

  // $file is passed in to us usually via --result-file.  If the user
  // has set $options['result-file'] = TRUE, then we
  // will generate an SQL dump file in the same backup
  // directory that pm-updatecode uses.
  if ($file) {
    if ($file === TRUE) {
      // User did not pass a specific value for --result-file. Make one.
      $backup = drush_include_engine('version_control', 'backup');
      $backup_dir = $backup->prepare_backup_dir($db_spec['database']);
      if (empty($backup_dir)) {
        $backup_dir = "/tmp";
      }
      $file = $backup_dir . '/@DATABASE_@DATE.sql';
    }
    $file = str_replace(array('@DATABASE', '@DATE'), array($database, gmdate('Ymd_his')), $file);
  }

  switch (_drush_sql_get_scheme($db_spec)) {
    case 'mysqli':
    case 'mysql':
      $exec = 'mysqldump';
      if ($file) {
        $exec .= ' --result-file '. $file;
      }
      // mysqldump wants 'databasename' instead of 'database=databasename' for no good reason.
      // We had --skip-add-locks here for a while to help people with insufficient permissions,
      // but removed it because it slows down the import a lot.  See http://drupal.org/node/1283978
      $extra = ' --no-autocommit --single-transaction --opt -Q' . str_replace('--database=', ' ', _drush_sql_get_credentials($db_spec));
      if (isset($data_only)) {
        $extra .= ' --no-create-info';
      }
      if (isset($ordered_dump)) {
       $extra .= ' --skip-extended-insert --order-by-primary';
      }
      $exec .= $extra;

      if (!empty($tables)) {
        $exec .= ' ' . implode(' ', $tables);
      }
      else {
        // Append the ignore-table options.
        foreach ($skip_tables as $table) {
          $ignores[] = "--ignore-table=$database.$table";
        }
        $exec .= ' '. implode(' ', $ignores);

        // Run mysqldump again and append output if we need some structure only tables.
        if (!empty($structure_tables)) {
          $exec .= " && mysqldump --no-data $extra " . implode(' ', $structure_tables);
          if ($file) {
            $exec .= " >> $file";
          }
        }
      }
      break;
    case 'pgsql':
      $create_db = drush_get_option('create-db');
      $exec = 'pg_dump ';
      if ($file) {
        $exec .= ' --file '. $file;
      }
      // Unlike psql, pg_dump does not take a '--dbname=' before the database name.
      $extra = str_replace('--dbname=', ' ', _drush_sql_get_credentials($db_spec));
      if (isset($data_only)) {
        $extra .= ' --data-only';
      }
      $exec .= $extra;
      $exec .= (!isset($create_db) && !isset($data_only) ? ' --clean' : '');

      if (!empty($tables)) {
        foreach ($tables as $table) {
          $exec .= " --table=$table";
        }
      }
      else {
        foreach ($skip_tables as $table) {
          $ignores[] = "--exclude-table=$table";
        }
        $exec .= ' '. implode(' ', $ignores);
        // Run pg_dump again and append output if we need some structure only tables.
        if (!empty($structure_tables)) {
          $schemaonlies = array();
          foreach ($structure_tables as $table) {
            $schemaonlies[] = "--table=$table";
          }
          $exec .= " && pg_dump --schema-only " . implode(' ', $schemaonlies) . $extra;
          if ($file) {
            $exec .= " >> $file";
          }
        }
      }
      break;
    case 'sqlite':
      // Dumping is usually not necessary in SQLite, since all database data
      // is stored in a single file on the filesystem which can be copied just
      // like any other file. But it still has a use in migration purposes and
      // building human-readable diffs and such, so let's do it anyway.
      $exec = _drush_sql_connect($db_spec);
      // SQLite's dump command doesn't support many of the features of its
      // Postgres or MySQL equivalents. We may be able to fake some in the
      // future, but for now, let's just support simple dumps.
      $exec .= ' ".dump"';
      if ($file) {
        $exec .= ' > '. $file;
      }
      break;
    case 'sqlsrv':
      // Change variable '$file' by reference in order to get drush_log() to report.
      if (!$file) {
        $file = $db_spec['database'] . '_' . date('Ymd_His') . '.bak';
      }
      $exec =  "sqlcmd -U \"" . $db_spec['username'] . "\" -P \"" . $db_spec['password'] . "\" -S \"" . $db_spec['host'] . "\" -Q \"BACKUP DATABASE [" . $db_spec['database'] . "] TO DISK='" . $file . "'\"";
      break;
    case 'oracle':
      $create_db = drush_get_option('create-db');
      $exec = 'exp ' . _drush_sql_get_credentials($db_spec);
      // Change variable '$file' by reference in order to get drush_log() to report.
      if (!$file) {
        $file = $db_spec['username'] . '.dmp';
      }
      $exec .= ' file=' . $file;

      if (!empty($tables))
        $exec .= ' tables="(' . implode(',', $tables) . ')"';
      else
        $exec .= ' owner=' . $db_spec['username'];

      break;
  }

  if (drush_get_option('gzip')) {
    if ($file) {
      $escfile = drush_escapeshellarg($file);
      if (drush_get_context('DRUSH_AFFIRMATIVE')) {
        // Gzip the result-file without Gzip confirmation
        $exec .= " && gzip -f $escfile";
        $file .= '.gz';
      }
      else {
        // Gzip the result-file
        $exec .= " && gzip $escfile";
        $file .= '.gz';
      }
    }
    else {
      // gzip via pipe since user has not specified a file.
      $exec .= "| gzip";
    }
  }

  return array($exec, $file);
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
function _drush_sql_get_table_list($option_name) {
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
  return _drush_sql_query($query, NULL, $filename);
}

/*
 * Execute a SQL query.
 *
 * @param string $query
 *   The SQL to be executed. Should be NULL if $file is provided.
 * @param array $db_spec
 *   A database target.
 * @param string $filename
 *   A path to a file containing the SQL to be executed.
 */
function _drush_sql_query($query, $db_spec = NULL, $filename = NULL) {
  $suffix = '';
  $scheme = _drush_sql_get_scheme($db_spec);
  // Inject table prefixes as needed.
  if (drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_DATABASE)) {
    if ($filename) {
      $query = file_get_contents($filename);
    }
    // Enable prefix processing which can be dangerous so off by default. See http://drupal.org/node/1219850.
    if (drush_get_option('db-prefix')) {
      if (drush_drupal_major_version() >= 7) {
        $query = Database::getConnection()->prefixTables($query);
      }
      else {
        $query = db_prefix_tables($query);
      }
    }
  }

  // is this an oracle query
  if ($scheme == 'oracle') {
    $query = drush_sql_format_oracle($query);
    $suffix = '.sql';
  }

  // Convert mysql 'show tables;' query into something pgsql understands
  if (($scheme == 'pgsql') && ($query == 'show tables;')) {
    $query = drush_sql_show_tables_pgsql();
  }

  // Save $query to a tmp file if needed. We will redirect it in.
  if (!$filename) {
    $filename = drush_save_data_to_temp_file($query, $suffix);
  }
  $exec = drush_sql_build_exec($db_spec, $filename);
  if ($output_file = drush_get_option('result-file')) {
    $exec .= ' > '. drush_escapeshellarg($output_file);
  }
  // In --simulate mode, drush_op will show the call to mysql or psql,
  // but the sql query itself is stored in a temp file and not displayed.
  // We will therefore show the query explicitly in the interest of full disclosure.
  if (drush_get_context('DRUSH_SIMULATE')) {
    drush_print('sql-query: ' . $query);
    if (!empty($exec)) {
      drush_print('exec: ' . $exec);
    }
    return TRUE;
  }
  if (empty($scheme)) {
    return drush_set_error('DRUSH_SQL_NO_DATABASE', dt("No database to operate on."));
  }
  if (empty($exec)) {
    return drush_set_error('DRUSH_SQL_NO_QUERY', 'No query provided');
  }
  return (drush_op_system($exec) == 0);
}

function drush_sql_drop() {
  if (!drush_confirm(dt('Do you really want to drop all tables?'))) {
    return drush_user_abort();
  }
  drush_sql_bootstrap_further();
  _drush_sql_drop();
}

// n.b. site-install uses _drush_sql_drop as a fallback technique if
// drop database; create database fails.  If _drush_sql_drop
// is rewritten to also use that technique, it should maintain
// the drop tables code here as a fallback.
function _drush_sql_drop($db_spec = NULL) {
  // TODO: integrate with _drush_sql_get_table_list?

  $suffix = '';
  $scheme = _drush_sql_get_scheme($db_spec);
  switch ($scheme) {
    case 'pgsql':
      $query = drush_sql_show_tables_pgsql();
      break;
    case 'sqlite':
      $query = '.tables';
      break;
    case 'sqlsrv':
      $query = 'SELECT TABLE_NAME FROM information_schema.tables';
      break;
    case 'oracle':
      $query = "SELECT TABLE_NAME FROM USER_TABLES WHERE TABLE_NAME NOT IN ('BLOBS','LONG_IDENTIFIERS')";
      $suffix = '.sql';
      break;
    default:
      $query = 'SHOW TABLES;';
  }

  $filename = drush_save_data_to_temp_file($query, $suffix);
  $exec = drush_sql_build_exec($db_spec, $filename);

  // Actually run this prep query no matter if in SIMULATE.
  $old = drush_get_context('DRUSH_SIMULATE');
  drush_set_context('DRUSH_SIMULATE', FALSE);
  drush_shell_exec($exec);
  drush_set_context('DRUSH_SIMULATE', $old);
  if ($tables = drush_shell_exec_output()) {
    if ($scheme === 'sqlite') {
      // SQLite's '.tables' command always outputs the table names in a column
      // format, like this:
      // table_alpha    table_charlie    table_echo
      // table_bravo    table_delta      table_foxtrot
      // …and there doesn't seem to be a way to fix that. So we need to do some
      // clean-up.
      // Since we're already doing iteration here, might as well build the SQL
      // too, since SQLite only wants one table per DROP TABLE command (so we have
      // to do "DROP TABLE foo; DROP TABLE bar;" instead of
      // "DROP TABLE foo, bar;").
      $sql = '';
      foreach ($tables as $line) {
        preg_match_all('/[^\s]+/', $line, $matches);
        if (!empty($matches[0])) {
          foreach ($matches[0] as $match) {
            $sql .= "DROP TABLE {$match};";
          }
        }
      }
      // We can't use drush_op('db_query', $sql) because it will only perform one
      // SQL command and we're technically performing several.
      $exec = _drush_sql_connect($db_spec);
      $exec .= " '{$sql}'";
      return drush_op_system($exec) == 0;
    }
    elseif ($scheme === 'sqlsrv') {
      // Shift off the header of the column of data returned.
      array_pop($tables);
      array_pop($tables);
      $sql = 'DROP TABLE '. implode(', ', $tables);
      return _drush_sql_query($sql, $db_spec);
    }
    else {
      // Shift off the header of the column of data returned.
      array_shift($tables);
      $sql = 'DROP TABLE '. implode(', ', $tables);
      return _drush_sql_query($sql, $db_spec);
    }
  }
  else {
    drush_log(dt('No tables to drop.'), 'ok');
  }
  return TRUE;
}

function drush_sql_cli() {
  drush_sql_bootstrap_further();
  drush_shell_proc_open(_drush_sql_connect());
}

/**
 * Command callback. Run's the sanitization operations on the current database.
 */
function drush_sql_sanitize() {
  if (!drush_confirm(dt('Do you really want to sanitize the current database?'))) {
    return drush_user_abort();
  }
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

  $sanitize_query = '';
  foreach($options as $id => $data) {
    $sanitize_query .= $data['query'] . " ";
  }
  if ($sanitize_query) {
    if (!drush_get_context('DRUSH_SIMULATE')) {
      drush_sql_query($sanitize_query);
    }
    else {
      drush_print("Executing: $sanitize_query");
    }
  }
}

//////////////////////////////////////////////////////////////////////////////
// SQL SERVICE HELPERS

/**
 * Get a database specification for the active DB connection. Honors the
 * 'database' and 'target command' line options. Honors a --db-url option.
 *
 * @return
 *   An info array describing a database target.
 */
function _drush_sql_get_db_spec() {
  $database = drush_get_option('database', 'default');
  $target = drush_get_option('target', 'default');

  if ($url = drush_get_option('db-url')) {
    $url =  is_array($url) ? $url[$database] : $url;
    $db_spec = drush_convert_db_from_db_url($url);
    $db_spec['db_prefix'] = drush_get_option('db-prefix');
    return $db_spec;
  }
  elseif (($databases = drush_get_option('databases')) && (array_key_exists($database, $databases)) && (array_key_exists($target, $databases[$database]))) {
    return $databases[$database][$target];
  }
  elseif (drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION)) {
    switch (drush_drupal_major_version()) {
      case 6:
        if ($url = isset($GLOBALS['db_url']) ? $GLOBALS['db_url'] : drush_get_option('db-url', NULL)) {
          $url =  is_array($url) ? $url[$database] : $url;
          $db_spec = drush_convert_db_from_db_url($url);
          $db_spec['db_prefix'] = isset($GLOBALS['db_prefix']) ? $GLOBALS['db_prefix'] : drush_get_option('db-prefix', NULL);
          return $db_spec;
        }
        return NULL;
      default:
        // We don't use DB API here `sql-sync` would have to messily addConnection.
        if (!isset($GLOBALS['databases']) || !array_key_exists($database, $GLOBALS['databases']) || !array_key_exists($target, $GLOBALS['databases'][$database])) {
          return NULL;
        }
        return $GLOBALS['databases'][$database][$target];
    }
  }
}

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

function _drush_sql_get_spec_from_options($prefix, $default_to_self = TRUE) {
  $db_spec = NULL;
  $databases = drush_get_option($prefix . 'databases');
  if (isset($databases) && !empty($databases)) {
    $database = drush_get_option($prefix . 'database', 'default');
    $target = drush_get_option($prefix . 'target', 'default');
    if (array_key_exists($database, $databases) && array_key_exists($target, $databases[$database])) {
      $db_spec = $databases[$database][$target];
    }
  }
  else {
    $db_url = drush_get_option($prefix . 'db-url');
    if (isset($db_url)) {
      $db_spec = drush_convert_db_from_db_url($db_url);
    }
    elseif ($default_to_self) {
      $db_spec = _drush_sql_get_db_spec();
    }
  }

  if (isset($db_spec)) {
    $remote_host = drush_get_option($prefix . 'remote-host');
    if (!drush_is_local_host($remote_host)) {
      $db_spec['remote-host'] = $remote_host;
      $db_spec['port'] = drush_get_option($prefix . 'remote-port', (isset($db_spec['port']) ? $db_spec['port'] : NULL));
    }
  }

  return $db_spec;
}

/**
 * Determine where to store an sql dump file.  This
 * function is called by sql-sync.
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
    if (!isset($dump_dir)) {
      // If this is a remote site, try to find a writable tmpdir.
      if (isset($site_record['remote-host'])) {
        $result = drush_invoke_process($site_record, 'ev', array('drush_print(drush_find_tmp())'), array(), array('integrate' => FALSE, 'override-simulated' => TRUE));
        // If the call to invoke process does not work for some reason
        // (e.g. drush not installed on the target machine),
        // then we will just presume that the tmp dir is '/tmp'.
        if (!$result || empty($result['output'])) {
          $dump_dir = '/tmp';
        }
        else {
          $dump_dir = trim($result['output']);
        }
        $dump_file = $dump_dir . '/' . $filename_pattern . '.sql';
      }
      else {
        $dump_file = drush_tempnam($filename_pattern . '.sql.');
      }
      $site_record['dump-is-temp'] = TRUE;
    }
    else {
      $dump_file = $dump_dir . '/' . $filename_pattern . '.sql';
    }
  }

  return $dump_file;
}

function _drush_sql_get_scheme($db_spec = NULL) {
  if (is_null($db_spec)) {
    $db_spec = _drush_sql_get_db_spec();
  }
  return $db_spec['driver'];
}

/**
 * Build a fragment containing credentials and mysql-connection parameters.
 *
 * @param $db_spec
 * @return string
 */
function _drush_sql_get_credentials($db_spec = NULL) {
  if (is_null($db_spec)) {
    $db_spec = _drush_sql_get_db_spec();
  }

  // Build an array of key-value pairs for the parameters.
  $parameters = array();

  switch (_drush_sql_get_scheme($db_spec)) {
    case 'mysql':
      // Some drush commands (e.g. site-install) want to connect to the
      // server, but not the database.  Connect to the built-in database.
      $parameters['database'] = empty($db_spec['database']) ? 'information_schema' : $db_spec['database'];

      // Default to unix socket if configured.
      if (!empty($db_spec['unix_socket'])) {
        $parameters['socket'] = $db_spec['unix_socket'];
      }
      // EMPTY host is not the same as NO host, and is valid (see unix_socket).
      elseif (isset($db_spec['host'])) {
        $parameters['host'] = $db_spec['host'];
      }

      if (!empty($db_spec['port'])) {
        $parameters['port'] = $db_spec['port'];
      }

      // User is required. Drupal calls it 'username'. MySQL calls it 'user'.
      $parameters['user'] = $db_spec['username'];

      // EMPTY password is not the same as NO password, and is valid.
      if (isset($db_spec['password'])) {
        $parameters['password'] = $db_spec['password'];
      }
      break;

    case 'pgsql':
      // Some drush commands (e.g. site-install) want to connect to the
      // server, but not the database.  Connect to the built-in database.
      $parameters['dbname'] = empty($db_spec['database']) ? 'template1' : $db_spec['database'];

      // Host and port are optional but have defaults.
      $parameters['host'] = empty($db_spec['host']) ? 'localhost' : $db_spec['host'];
      $parameters['port'] = empty($db_spec['port']) ? '5432' : $db_spec['port'];

      // Username is required.
      $parameters['username'] = $db_spec['username'];

      // Don't set the password.
      // @see http://drupal.org/node/438828
      break;

    case 'sqlite':
      // SQLite doesn't do user management, instead relying on the filesystem
      // for that. So the only info we really need is the path to the database
      // file, and not as a "--key=value" parameter.
      return ' '  .  $db_spec['database'];
      break;

    case 'sqlsrv':
      // Some drush commands (e.g. site-install) want to connect to the
      // server, but not the database.  Connect to the built-in database.
      $database = empty($db_spec['database']) ? 'master' : $db_spec['database'];
      // Host and port are optional but have defaults.
      $host = empty($db_spec['host']) ? '.\SQLEXPRESS' : $db_spec['host'];
      return ' -S ' . $host . ' -d ' . $database . ' -U ' . $db_spec['username'] . ' -P ' . $db_spec['password'];
      break;


    case 'oracle':
      // Return an Oracle connection string
      return ' ' . $db_spec['username'] .'/' . $db_spec['password'] . ($db_spec['host']=='USETNS' ? '@' . $db_spec['database'] : '@//' . $db_spec['host'] . ':' . ($db_spec['port'] ? $db_spec['port'] : '1521') . '/' . $db_spec['database']);
      break;

  }

  // Turn each parameter into a valid parameter string.
  $parameter_strings = array();
  foreach ($parameters as $key => $value) {
    // Only escape the values, not the keys or the rest of the string.
    $value = drush_escapeshellarg($value);
    $parameter_strings[] = "--$key=$value";
  }

  // Join the parameters and return.
  return ' ' . implode(' ', $parameter_strings);
}

function _drush_sql_get_invalid_url_msg($db_spec = NULL) {
  if (is_null($db_spec)) {
    $db_spec = _drush_sql_get_db_spec();
  }
  switch (drush_drupal_major_version()) {
    case 6:
      return dt('Unable to parse DB connection string');
   default:
     return dt('Unable to parse DB connection array');
  }
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

// Convert mysql 'show tables;' query into something pgsql understands.
function drush_sql_show_tables_pgsql() {
  return "select tablename from pg_tables where schemaname='public';";
}

// Format queries to work with Oracle and SqlPlus
function drush_sql_format_oracle($query) {

  // remove trailing semicolon from query if we have it
  $query = preg_replace('/\;$/', '', $query);

  // some sqlplus settings
  $settings[] = "set TRIM ON";
  $settings[] = "set FEEDBACK OFF";
  $settings[] = "set UNDERLINE OFF";
  $settings[] = "set PAGES 0";
  $settings[] = "set PAGESIZE 50000";

  // are we doing a describe ?
  if (!preg_match('/^ *desc/i', $query)) {
    $settings[] = "set LINESIZE 32767";
  }

  // are we doing a show tables ?
  if (preg_match('/^ *show tables/i', $query)) {
    $settings[] = "set HEADING OFF";
    $query = "select object_name from user_objects where object_type='TABLE' order by object_name asc";
  }

  // create settings string
  $sqlp_settings = implode("\n", $settings)."\n";

  // important for sqlplus to exit correctly
  return "${sqlp_settings}${query};\nexit;\n";
}


/*
 * Drop all tables (if DB exists) or CREATE target database.
 *
 * return boolean
 *   TRUE or FALSE depending on success.
 */
function drush_sql_empty_db($db_spec = NULL) {
  if (is_null($db_spec)) {
    $db_spec = _drush_sql_get_db_spec();
  }
  if (drush_sql_db_exists($db_spec)) {
    _drush_sql_drop($db_spec);
  }
  else {
    _drush_sql_create($db_spec);
  }
}

/*
 * Build DB connection array with superuser credentials if provided.
 *
 * The options 'db-su' and 'db-su-pw' will be retreived from the
 * specified site alias record, if it exists and contains those items.
 * If it does not, they will be fetched via drush_get_option.
 *
 * Note that in the context of sql-sync, the site alias record will
 * be taken from the target alias (e.g. `drush sql-sync @source @target`),
 * which will be overlayed with any options that begin with 'target-';
 * therefore, the commandline options 'target-db-su' and 'target-db-su-pw'
 * may also affect the operation of this function.
 */
function drush_sql_su($db_spec, $site_alias_record = NULL) {
  $create_db_target = $db_spec;
  $create_db_target['database'] = '';
  $db_superuser = drush_sitealias_get_option($site_alias_record, 'db-su');
  if (isset($db_superuser)) {
    $create_db_target['username'] = $db_superuser;
  }
  $db_su_pw = drush_sitealias_get_option($site_alias_record, 'db-su-pw');
  // If --db-su-pw is not provided and --db-su is, default to empty password.
  // This way db cli command will take password from .my.cnf or .pgpass.
  if (!empty($db_su_pw)) {
    $create_db_target['password'] = $db_su_pw;
  }
  elseif (isset($db_superuser)) {
    unset($create_db_target['password']);
  }
  return $create_db_target;
}

/*
 * Build a SQL string for dropping and creating a database.
 *
 * @param array $db_spec
 *   A database specification array.
 *
 * @param boolean $quoted
 *   Quote the database name. Mysql uses backticks to quote which can cause problems
 *   in a Windows shell. Set TRUE if the CREATE is not running on the bash command line.
 */
function drush_sql_build_createdb_sql($db_spec, $quoted = FALSE) {
  $sql = array();
  switch (_drush_sql_get_scheme($db_spec)) {
    case 'mysql':
      $dbname = $quoted ? '`' . $db_spec['database'] . '`' : $db_spec['database'];
      $sql[] = sprintf('DROP DATABASE IF EXISTS %s; ', $dbname);
      $sql[] = sprintf('CREATE DATABASE %s /*!40100 DEFAULT CHARACTER SET utf8 */;', $dbname);
      $sql[] = sprintf('GRANT ALL PRIVILEGES ON %s.* TO \'%s\'@\'%s\'', $dbname, $db_spec['username'], $db_spec['host']);
      $sql[] = sprintf("IDENTIFIED BY '%s';", $db_spec['password']);
      $sql[] = 'FLUSH PRIVILEGES;';
      break;
    case 'pgsql':
      $dbname = $quoted ? '"' . $db_spec['database'] . '"' : $db_spec['database'];
      $sql[] = sprintf('drop database if exists %s;', $dbname);
      $sql[] = sprintf("create database %s ENCODING 'UTF8';", $dbname);
      break;
  }
  return implode(' ', $sql);
}

/*
 * Does specified database exist on target server
 *
 * @return boolean
 */
function drush_sql_db_exists($db_spec) {
  if  ($db_spec['driver'] == 'sqlite') {
    return file_exists($db_spec['database']);
  }

  $connect_yes_db = _drush_sql_connect($db_spec);
  $database = $db_spec['database'];
  unset($db_spec['database']);
  $connect_no_db = _drush_sql_connect($db_spec);
  // We need the output back so we can't use drush_sql_query().
  switch ($db_spec['driver']) {
    case 'mysql':
      $sql = "SELECT 1;";
      // Suppress ugly output. Redirect STDERR and STDOUT. We just need exit code.
      $bit_bucket = drush_bit_bucket();
      return drush_shell_exec("$connect_yes_db -e \"$sql\" 2> $bit_bucket > $bit_bucket");
    case 'pgsql':
      $sql = "SELECT 1 AS result FROM pg_database WHERE datname='$database'";
      drush_shell_exec("$connect_no_db -t -c \"$sql\"");
      $output = drush_shell_exec_output();
      return (bool)$output[0];
    case 'sqlsrv':
      // TODO: untested, but the gist is here.
      $sql = "if db_id('$database') IS NOT NULL print 1";
      drush_shell_exec("$connect_no_db -Q \"$sql\"");
      $output = drush_shell_exec_output();
      return $output[0] == 1;
  }
}

function drush_sql_build_exec($db_spec, $filepath) {
  $scheme = _drush_sql_get_scheme($db_spec);
  $exec = '';
  switch ($scheme) {
    case 'mysql':
      $exec = 'mysql';
      $exec .= _drush_sql_get_credentials($db_spec);
      $exec .= ' ' . drush_get_option('extra');
      $exec .= " < " . drush_escapeshellarg($filepath);

      break;
    case 'pgsql':
      $exec = 'psql -q ';
      $exec .= _drush_sql_get_credentials($db_spec);
      $exec .= ' ' . (drush_get_option('extra') ? drush_get_option('extra') : "--no-align --field-separator='\t' --pset footer=off");
      $exec .= " --file " . drush_escapeshellarg($filepath);
      break;
    case 'sqlite':
      $exec = 'sqlite3';
      $exec .= ' ' . drush_get_option('extra');
      $exec .= _drush_sql_get_credentials($db_spec);
      $exec .= " < " . drush_escapeshellarg($filepath);
      break;
    case 'sqlsrv':
      $exec = 'sqlcmd';
      $exec .= ' ' . drush_get_option('extra');
      $exec .= _drush_sql_get_credentials($db_spec);
      $exec .= ' -h -1 -i "' . $filepath . '"';
      break;
    case 'oracle':
      $exec = 'sqlplus';
      $exec .= ' ' . drush_get_option('extra');
      $exec .= _drush_sql_get_credentials($db_spec);
      $exec .= " @" . drush_escapeshellarg($filepath);
      break;
  }
  return $exec;
}
