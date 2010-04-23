<?php
// $Id$

/**
 * @file Drush sql commands
 */

/**
 * Implementation of hook_drush_help().
 */
function sql_drush_help($section) {
  switch ($section) {
    case 'drush:sql-conf':
      return dt('Show database connection details.');
    case 'drush:sql-connect':
      return dt('A string which connects to the current database.');
    case 'drush:sql-cli':
      return dt('Quickly enter the mysql command line.');
    case 'drush:sql-dump':
      return dt('Prints the whole database to STDOUT or save to a file.');
    case 'drush:sql-query':
      return dt("Usage: drush [options] sql-query <query>...\n<query> is a SQL statement, which can alternatively be passed via STDIN. Any additional arguments are passed to the mysql command directly.");
    case 'drush:sql-sync':
      return dt("Usage: drush [options] sql-sync <source_alias> <target_alias>.\n <source_alias> and <target_alias> are site-aliases, or names of directories under \'sites\'. These determine from where and to where you want your database copied.");
  }
}

/**
 * Implementation of hook_drush_command().
 */
function sql_drush_command() {
  $options['--database'] = 'The DB connection key if using multiple connections in settings.php.';
  if (drush_drupal_major_version() >= 7) {
    $options['--target'] = 'The name of a target within the specified database.';
  }

  $items['sql-conf'] = array(
    'description' => 'Print database connection details using print_r().',
    'arguments' => array(
      'all' => 'Show all database connections, instead of just one.',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
    'options' => $options,
  );
  $items['sql-connect'] = array(
    'description' => 'A string for connecting to the DB.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
    'options' => $options,
    'examples' => array(
      '`drush sql connect` < example.sql' => 'Import sql statements from a file into the current database.',
    ),
  );
  $items['sql-dump'] = array(
    'callback' => 'drush_sql_dump_execute',
    'description' => 'Exports the Drupal DB as SQL using mysqldump.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
    'examples' => array(
      'drush sql-dump --result-file=../18.sql' => 'Save SQL dump to the directory above Drupal root.',
      'drush sql-dump --skip-tables-key=common' => 'Skip standard tables. @see example.drushrc.com',
    ),
    'options' => array(
      '--result-file' => 'Save to a file. The file should be relative to Drupal root. Recommended.',
      '--skip-tables-key' => 'A key in the $skip_tables array. @see example.drushrc.php. Optional.',
      '--structure-tables-key' => 'A key in the $structure_tables array. @see example.drushrc.php. Optional.',
      '--tables-key' => 'A key in the $tables array. Optional.',
      '--tables-list' => 'A comma-separated list of tables to transfer. Optional.',
      '--ordered-dump' => 'Use this option to output ordered INSERT statements in the sql-dump.Useful when backups are managed in a Version Control System. Optional.',
      '--create-db' => 'Wipe existing tables.',
      '--data-only' => 'Omit CREATE TABLE statements. Postgres only.',
      '--ordered-dump' => 'Order by primary key and add line breaks for efficient diff in revision control. Also, faster rsync. Slows down the dump. Mysql only.',
    ) + $options,
  );
  $items['sql-query'] = array(
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_DATABASE,
    'description' => 'Execute a query against the site database.',
    'examples' => array(
      'drush sql-query "SELECT * FROM {users} WHERE uid=1"' => 'Browse user record. Table prefixes are honored.',
    ),
    'arguments' => array(
       'query' => 'A SQL query. Mandatory.',
    ),
    'options' => array(
      '--extra' => 'Add custom options to the mysql command.',
    ) + $options,
    'aliases' => array('sqlq'),
  );
  $items['sql-sync'] = array(
    'description' => 'Copy source database to target database using rsync.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUSH,
    'examples' => array(
      'drush sql-sync @dev @prod' => 'Copy the DB defined in sites/dev to the DB in sites/prod.',
    ),
    'arguments' => array(
      'from' => 'Name of subdirectory within /sites or a site-alias.',
      'to' => 'Name of subdirectory within /sites or a site-alias.',
    ),
    'options' => array(
      '--skip-tables-key' => 'A key in the $skip_tables array. @see example.drushrc.php. Optional.',
      '--structure-tables-key' => 'A key in the $structure_tables array. @see example.drushrc.php. Optional.',
      '--tables-key' => 'A key in the $tables array. Optional.',
      '--tables-list' => 'A comma-separated list of tables to transfer. Optional.',
      '--cache' => 'Skip dump if result file exists and is less than "cache" hours old. Optional; default is 24 hours.',
      '--no-cache' => 'Do not cache the sql-dump file.',
      '--no-dump' => 'Do not dump the sql database; always use an existing dump file.',
      '--source-db-url' => 'Database specification for source system to dump from.',
      '--source-remote-port' => 'Override sql database port number in source-db-url. Optional.',
      '--source-remote-host' => 'Remote machine to run sql-dump file on. Optional; default is local machine.',
      '--source-dump' => 'Path to dump file. Optional; default is to create a temporary file.',
      '--target-db-url' => '',
      '--target-remote-port' => '',
      '--target-remote-host' => '',
      '--target-dump' => '',
      '--temp' => 'Use a temporary file to hold dump files.  Implies --no-cache.',
      '--dump-dir' => 'Directory to store sql dump files in when --source-dump or --target-dump are not used.  Takes precedence over --temp.',
      '--create-db' => 'Create a new database before importing the database dump on the target machine.',
      '--db-su' => 'Account to use when creating a new database. Optional.',
      '--db-su-pw' => 'Password for the "db-su" account. Optional.',
    ),
  );
  if (drush_drupal_major_version() >= 7) {

    $items['sql-sync']['options'] += array(
      '--source-target' => 'The name of a target within the SOURCE database.',
      '--destination-target' => 'The name of a target within the specified DESTINATION database.',
    );
  }
  $items['sql-cli'] = array(
    'description' => "Open a SQL command-line interface using Drupal's credentials.",
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
    'options' => $options,
    'aliases' => array('sqlc'),
  );
  return $items;
}

/**
 * Command callback. Displays the Drupal site's database connection string.
 */
function drush_sql_conf() {
  if (drush_get_option('db-url', FALSE)) {
    $db_spec['db-url'] = $GLOBALS['db_url'];
  }
  elseif (drush_get_option('all', FALSE)) {
    $db_spec = $GLOBALS['databases'];
    if (!isset($db_spec)) {
      $db_spec = array('default' => array('default' => _drush_sql_get_db_spec()));
    }
  }
  if (!isset($db_spec)) {
    $db_spec = _drush_sql_get_db_spec();
  }
  drush_backend_set_result($db_spec);
  if (!drush_get_option('show-passwords', FALSE)) {
    drush_unset_recursive($db_spec, 'password');
  }
  drush_print_r($db_spec);
}

/**
 * Command callback. Emits a connect string for mysql or pgsql.
 */
function _drush_sql_connect($db_spec = NULL) {
  switch (_drush_sql_get_scheme($db_spec)) {
    case 'mysql':
      $command = 'mysql ' . (drush_get_context('DRUSH_DEBUG') ? ' -v' : '');
      $command .= _drush_sql_get_credentials($db_spec);
      break;
    case 'pgsql':
      $command = 'psql';
      $command .= _drush_sql_get_credentials($db_spec);
      break;
  }
  return $command;
}

function drush_sql_connect() {
  drush_print(_drush_sql_connect());
}

/**
 * Command callback. Outputs the entire Drupal database in SQL format using mysqldump.
 */
function drush_sql_dump_execute() {
  $exec = drush_sql_dump();
  // Avoid the php memory of the $output array in drush_shell_exec().
  $return = drush_op('system', $exec);
  return $return;
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
 * Build a mysqldump statement.
 *
 * @param db_spec
 *   For D5/D6, a $db_url. For D7, a target in the default DB connection.
 * @return string
 *   A mysqldump statement that is ready for executing.
 */
function drush_sql_dump($db_spec = NULL) {
  return drush_sql_build_dump_command(drush_sql_get_table_selection(), $db_spec);
}

function drush_sql_build_dump_command($tabel_selection, $db_spec = NULL) {
  $skip_tables = $tabel_selection['skip'];
  $structure_tables = $tabel_selection['structure'];
  $tables = $tabel_selection['tables'];
  
  $ignores = array();
  $skip_tables += $structure_tables;
  $data_only = drush_get_option('data-only');
  // The ordered-dump option is only supported by MySQL for now.
  // @todo add documention once a hook for drush_get_option_help() is available.
  // @see drush_get_option_help() in drush.inc
  $ordered_dump = drush_get_option('ordered-dump');

  if (is_null($db_spec)) {
    $db_spec = _drush_sql_get_db_spec();
  }
  $database = $db_spec['database'];

  switch (_drush_sql_get_scheme($db_spec)) {
    case 'mysql':
      $exec = 'mysqldump' . (drush_get_context('DRUSH_VERBOSE') ? ' -v' : '');
      if ($file = drush_get_option('result-file')) {
        $exec .= ' --result-file '. $file;
      }
      $extra = ' --single-transaction --opt -Q' . _drush_sql_get_credentials($db_spec);
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
          $exec .= "; mysqldump --no-data $extra " . implode(' ', $structure_tables);
          if ($file) {
            $exec .= " >> $file";
          }
        }
      }
      break;
    case 'pgsql':
      $create_db = drush_get_option('create-db');
      $exec = 'pg_dump ' . (drush_get_context('DRUSH_VERBOSE') ? ' -v' : '');
      if ($file = drush_get_option('result-file')) {
        $exec .= ' --file '. $file;
      }
      // Unlike psql, pg_dump does not take a '-d' flag before the database name.
      // We'll put the database name in 'by hand' and then clear the name from
      // the record to prevent _drush_sql_get_credentials from inserting a -d term
      // that pg_dump does not understand.
      $extra = ' ' . $db_spec['database'];
      $db_spec['database'] = null;
      $extra .= _drush_sql_get_credentials($db_spec);
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
          $exec .= "; pg_dump --schema-only " . implode(' ', $schemaonlies) . $extra;
          if ($file) {
            $exec .= " >> $file";
          }
        }
      }
      break;
  }

  return $exec;
}

/**
 * Consult the specified options and return the list of tables
 * specified.
 *
 * @param option_name
 *   The option name to check: skip-tables, structure-tables 
 *   or tables.  This funciton will check both *-key and *-list,
 *   and, in the case of sql-sync, will also check target-*
 *   and source-*, to see if an alias set one of these options.
 * @returns array
 *   Returns an array of tables based on the first option
 *   found, or an empty array if there were no matches.
 */
function _drush_sql_get_table_list($option_name) {
  foreach(array('' => 'options', 'target-,,source-' => NULL) as $prefix_list => $context) {
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
function drush_sql_query($query) {
  return _drush_sql_query($query);
}

function _drush_sql_query($query, $db_spec = NULL) {
  $scheme = _drush_sql_get_scheme($db_spec);

  // Inject table prefixes as needed.
  if (drush_drupal_major_version() >= 7) {
    $query = Database::getConnection()->prefixTables($query);
  }
  else {
    $query = db_prefix_tables($query);
  }

  // Convert mysql 'show tables;' query into something pgsql understands
  if (($scheme == 'pgsql') && ($query == 'show tables;')) {
    $query = "select tablename from pg_tables where schemaname='public';";
  }

  // Save $query to a tmp file. We will redirect it in.
  if ($file = drush_save_data_to_temp_file($query)) {
    switch ($scheme) {
      case 'mysql':
        $exec = 'mysql' . (drush_get_context('DRUSH_VERBOSE') ? ' -v' : '');
        $exec .= _drush_sql_get_credentials($db_spec);
        $exec .= ' ' . drush_get_option('extra');
        $exec .= " < $file";

        break;
      case 'pgsql':
        $exec = 'psql';
        $exec .= _drush_sql_get_credentials($db_spec);
        $exec .= (drush_get_context('DRUSH_VERBOSE') ? '' : ' -q');
        $exec .= ' ' . (drush_get_option('extra') ? drush_get_option('extra') : "--no-align --field-separator=$'\t' --pset footer=off");
        $exec .= " --file $file";
        break;
    }

    $return = drush_op('system', $exec) !== FALSE;
    return $return;
  }
}

function drush_sql_cli() {
  switch (_drush_sql_get_scheme()) {
    case 'mysql':
      $command = 'mysql ' . (drush_get_context('DRUSH_VERBOSE') ? ' -v' : '');
      $command .= _drush_sql_get_credentials();
      break;
    case 'pgsql':
      $command = ' psql';
      $command .= _drush_sql_get_credentials();
      break;
  }
  proc_close(proc_open($command, array(0 => STDIN, 1 => STDOUT, 2 => STDERR), $pipes));
}


//////////////////////////////////////////////////////////////////////////////
// SQL SERVICE HELPERS

/**
 * Get a database specification for the active DB connection. Honors the
 * 'database' and 'target command' line options.
 *
 * @return
 *   An info array describing a database target.
 */
function _drush_sql_get_db_spec() {
  $database = drush_get_option('database', 'default');
  $target = drush_get_option('target', 'default');

  switch (drush_drupal_major_version()) {
    case 5:
    case 6:
      $url = $GLOBALS['db_url'];
      // TODO: array version not working?
      $url =  is_array($url) ? $url[$database] : $url;
      return drush_convert_db_from_db_url($url);

    default:
      // We don't use DB API here `sql-sync` would have to messily addConnection.
      if (!isset($GLOBALS['databases']) || !array_key_exists($database, $GLOBALS['databases']) || !array_key_exists($target, $GLOBALS['databases'][$database])) {
        return NULL;
      }
      return $GLOBALS['databases'][$database][$target];
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
    $db_spec['remote-host'] = drush_get_option($prefix . 'remote-host');
    $db_spec['port'] = drush_get_option($prefix . 'remote-port', $db_spec['port']);
  }

  return $db_spec;
}

/**
 * Determine where to store an sql dump file.  This
 * function is called by sql-sync if the caller did
 * not explicitly specify a dump file to use.
 *
 * @param db_spec
 *   Information about the database being dumped; used
 *   to generate the filename.
 * @return string
 *   The path to the dump file
 */
function drush_sql_dump_file(&$db_spec) {
  // Make a base filename pattern to use to name the dump file
  $filename_pattern = $db_spec['database'];
  if (isset($db_spec['remote-host'])) {
    $filename_pattern = $db_spec['remote-host'] . '_' . $filename_pattern;
  }
  // If the user has set the --dump-dir option, then
  // store persistant sql dump files there.
  $dump_dir = drush_get_option(array('source-dump-dir', 'dump-dir'));
  if (isset($dump_dir)) {
    $dump_file = $dump_dir . '/' . $filename_pattern . '.sql';
  }
  // If the --dump-dir option is not set, then store
  // the sql dump in a temporary file.
  else {
    $dump_file = drush_tempnam($filename_pattern . '.sql.');
    $db_spec['dump-is-temp'] = TRUE;
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

  switch (_drush_sql_get_scheme($db_spec)) {
    case 'mysql':
      $cred = ' -h' . $db_spec['host'] .
         (empty($db_spec['port']) ? '' : ' -P' . $db_spec['port']) .
         ' -u' . $db_spec['username'] .
         (empty($db_spec['password']) ? '' : ' -p' . $db_spec['password']) . ' ' . $db_spec['database'];
      break;
  case 'pgsql':
      $cred = (isset($db_spec['database']) ? ' -d ' . (empty($db_spec['database']) ? 'template1' :  $db_spec['database']) : '') .
         (empty($db_spec['host'])  ? ' -h localhost ' : ' -h ' . $db_spec['host']) .
         (empty($db_spec['port']) ? ' -p 5432 ' : ' -p ' . $db_spec['port']);
      // Adding '-U' will cause Postgres to prompt for a password, so disable this for now.
      // Use "sudo -u postgres drush sql ..." to access the database without a password,
      // presuming that "postgres" is the database superuser and you have
      // "local all all ident sameuser" in your Postgres pg_hba.conf file.
      // See: http://drupal.org/node/438828
      $cred .= ' -U ' . $db_spec['username'] . ' ';
      break;
  }
  return escapeshellcmd($cred);
}

function _drush_sql_get_invalid_url_msg($db_spec = NULL) {
  if (is_null($db_spec)) {
    $db_spec = _drush_sql_get_db_spec();
  }
  switch (drush_drupal_major_version()) {
    case 5:
    case 6:
      return dt('Unable to parse DB connection string');
   default:
     return dt('Unable to parse DB connection array');
  }
}
