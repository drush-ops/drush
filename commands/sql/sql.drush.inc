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
    case 'drush:sql conf':
      return dt('Show database connection details.');
    case 'drush:sql connect':
      return dt('A string which connects to the current database.');
    case 'drush:sql cli':
      return dt('Quickly enter the mysql command line.');
    case 'drush:sql dump':
      return dt('Prints the whole database to STDOUT or save to a file.');
    case 'drush:sql query':
      return dt("Usage: drush [options] sql query <query>...\n<query> is a SQL statement, which can alternatively be passed via STDIN. Any additional arguments are passed to the mysql command directly.");
    case 'drush:sql load':
      return dt("Usage: drush [options] sql load <source_dir> <target_dir>.\n <source_dir> and <target_dir> are names of directories under \'sites\'. These determine from where and to where you want your database copied. Optional: specify \'common\' for --skip if you wish to omit disposable tables like cache*, search*, etc. Your skip lists are specified in your drushrc.php file. Any additional arguments are passed to the mysqldump command directly.");
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
  
  $items['sql conf'] = array(
    'callback' => 'drush_sql_conf',
    'description' => 'Print database connection details.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
    'options' => $options,
  );
  $items['sql connect'] = array(
    'callback' => 'drush_sql_connect',
    'description' => 'A string for connecting to the DB.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
    'options' => $options,
  );
  $items['sql dump'] = array(
    'callback' => 'drush_sql_dump_execute',
    'description' => 'Exports the Drupal DB as SQL using mysqldump.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
    'examples' => array(
      'drush sql dump --result-file=../18.sql' => 'Save SQL dump to the directory above Drupal root.',
      'drush sql dump --skip-tables-key=common' => 'Skip standard tables. @see example.drushrc.com',
    ),
    'options' => array(
      '--result-file' => 'Save to a file. The file should be relative to Drupal root. Recommended.',
      '--skip-tables-key' => 'A key in the $skip_tables array. @see example.drushrc.php. Optional.',
      '--structure-tables-key' => 'A key in the $structure_tables array. @see example.drushrc.php. Optional.',
    ) + $options,
  );
  $items['sql query'] = array(
    'callback' => 'drush_sql_query',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
    'description' => 'Execute a query against the site database.',
    'examples' => array(
      'drush sql query "SELECT * FROM {users} WHERE uid=1"' => 'Browse user record',
    ),
    'arguments' => array(
       'query' => 'A SQL query. Mandatory.',
    ),
    'options' => $options,
  );
  $items['sql load'] = array(
    'callback' => 'drush_sql_load',
    'description' => 'Copy source database to target database.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
    'examples' => array(
      'drush sql load prod dev' => 'Copy the DB defined in sites/prod to the DB in sites/dev.',
    ),
    'arguments' => array(
      'from' => 'Name of subdirectory within /sites. Its settings.php defines where to dump from.',
      'to' => 'Name of subdirectory within /sites. Its settings.php defines the destination for the dump.',
    ),
    'options' => array(
      '--skip-tables-key' => 'A key in the $skip_tables array. @see example.drushrc.php. Optional.',
      '--structure-tables-key' => 'A key in the $structure_tables array. @see example.drushrc.php. Optional.',
      '--source_database' => 'The SOURCE DB connection key if using multiple connections in settings.php.',
      '--destination_database' => 'The DESTINATION DB connection key if using multiple DB connections',
    ),
  );
  if (drush_drupal_major_version() >=7) {
    $items['sql load']['options'] += array(
      '--source_target' => 'The name of a target within the SOURCE database.',
      '--destination_target' => 'The name of a target within the specified DESTINATION database.',
    );
  }
  $items['sql cli'] = array(
    'callback' => 'drush_sql_cli',
    'description' => 'Open a SQL command-line interface using Drupal’s credentials.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
    'options' => $options,
  );
  return $items;
}

/**
 * Command callback. Displays the Drupal site's database connection string.
 */
function drush_sql_conf() {
  drush_print_r(_drush_sql_get_db_spec());
}

/**
 * Command callback. Emits a connect string for mysql or pgsql.
 */
function drush_sql_connect() {
  switch (_drush_sql_get_scheme()) {
    case 'mysql':
      $command = 'mysql ' . (drush_get_context('DRUSH_VERBOSE') ? ' -v' : '');
      $command .= _drush_sql_get_credentials();
      break;
    case 'pgsql':
      $command = 'psql -d ';
      $command .= _drush_sql_get_credentials();
      break;
  }
  drush_print($command);
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

/**
 * Build a mysqldump statement.
 * 
 * @param db_spec
 *   For D5/D6, a $db_url. For D7, a target in the default DB connection.
 * @return string
 *   A mysqldump statement that is ready for executing.
 */
function drush_sql_dump($db_spec = NULL) {
  $skip_tables = $structure_tables = $ignores = array();

  // Skip large core tables if instructed.  Also used by 'sql load' command.
  $key = drush_get_option('skip-tables-key');
  $all_skip_tables = drush_get_option('skip-tables', array());
  if (array_key_exists($key, $all_skip_tables)) {
    $skip_tables = $all_skip_tables[$key];
  }

  // Skip any structure-tables as well.
  $key = drush_get_option('structure-tables-key');
  $all_structure_tables = drush_get_option('structure-tables', array());
  if (array_key_exists($key, $all_structure_tables)) {
    $structure_tables = $all_structure_tables[$key];
    $skip_tables += $structure_tables;
  }
  $database = $db_spec['database'];

  switch (_drush_sql_get_scheme()) {
    case 'mysql':
      if (is_null($db_spec)) {
        $db_spec = _drush_sql_get_db_spec();
      }

      $exec = 'mysqldump' . (drush_get_context('DRUSH_VERBOSE') ? ' -v' : '');
      if ($file = drush_get_option('result-file')) {
        $exec .= ' --result-file '. $file;
      }
      $extra = ' --single-transaction --opt -Q' . _drush_sql_get_credentials($db_spec);
      $exec .= $extra;

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
      break;
    case 'pgsql':
      if (is_null($db_spec)) {
        $db_spec = _drush_sql_get_db_spec();
      }

      $exec = 'pg_dump --clean ' . (drush_get_context('DRUSH_VERBOSE') ? ' -v' : '');
      if ($file = drush_get_option('result-file')) {
        $exec .= ' --file '. $file;
      }
      $extra = ' ' . _drush_sql_get_credentials($db_spec);
      $exec .= $extra;
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
      break;
  }

  return $exec;
}

/**
 * Command callback. Executes the given SQL query on the Drupal database.
 */
function drush_sql_query($query) {
  // Save $query to a tmp file. We will redirect it in.
  if ($file = drush_save_data_to_temp_file($query)) {
    switch (_drush_sql_get_scheme()) {
      case 'mysql':
        $exec = 'mysql' . (drush_get_context('DRUSH_VERBOSE') ? ' -v' : '');
        $exec .= _drush_sql_get_credentials();
        $exec .= drush_get_option('extra');
        $exec .= " < $file";

        break;
      case 'pgsql':
        $exec = 'psql -d ';
        $exec .= _drush_sql_get_credentials();
        $exec .= (drush_get_context('DRUSH_VERBOSE') ? '' : ' -q');
        $exec .= ' --file -';
        $exec .= " < $file";
        break;
    }
    
    $return = drush_op('system', $exec) !== FALSE;
    drush_op('unlink', $file);
    return $return;
  }
}

/**
 * Copy an entire database to another database. For example, migrate from production to dev
 * or dev to staging.
 *
 * conf_path() uses a static var so we can't use it to figure out paths based on URIs.
 *
 * @param source
 *   The name of a subdirectory under sites. Its settings.php specifies the database which should be migrated.
 * @param target
 *    The name of a subdirectory under sites. Its settings.php specifies the database which whose tables will
 *    be replaced with the contents of `source`. *
 **/
function drush_sql_load($source, $destination) {
  $source = _drush_sql_get_spec_from_settings("./sites/$source/settings.php", 'source');
  $destination = _drush_sql_get_spec_from_settings("./sites/$destination/settings.php", 'target');
  
  // Prompt for confirmation. This is destructive.
  if (!drush_get_context('DRUSH_SIMULATE')) {
    $txt_source = $source['host'] . '/' . $source['database'];
    $txt_destination = $destination['host'] . '/' . $destination['database'];
    drush_print(dt("You will destroy data from !target and replace with data from !source.", array('!source' => $txt_source, '!target' => $txt_destination)));
    drush_print();
    // TODO: actually make the backup if desired.
    drush_print(dt("You might want to make a backup first, using sql_dump command.\n"));
    if (!drush_confirm(dt('Do you really want to continue?'))) {
      drush_die('Aborting.');
    }
  }
  
  // Ignore --result-file and always generate a temp file
  $file = tempnam(sys_get_temp_dir(), 'sql');
  drush_set_option('result-file', $file);

  // Get command to export from source.
  if ($retrieve = drush_sql_dump($source)) {
    
    switch (_drush_sql_get_scheme()) {
    case 'mysql':
      // Build import command for target. No verbose here as that emits too much SQL.
      $send = 'mysql' . _drush_sql_get_credentials($destination);
      break;
    case 'pgsql':
      $send .= 'psql -d ' . _drush_sql_get_credentials($destination) . ' --file -';
      break;
    }
    
    $exec = "$retrieve; $send < $file";
    // Avoid the php memory of the $output array in drush_shell_exec().
    drush_op('system', $exec);
    drush_op('unlink', $file);
  }
}

function drush_sql_cli() {
  switch (_drush_sql_get_scheme()) {
    case 'mysql':
      $command = 'mysql -A' . (drush_get_context('DRUSH_VERBOSE') ? ' -v' : '');
      $command .= _drush_sql_get_credentials();
      break;
    case 'pgsql':
      $command = ' psql -d ';
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
  $database = drush_get_option('database') ? drush_get_option('database') : 'default';
  $target = drush_get_option('target') ? drush_get_option('target') : 'default';
  
  switch (drush_drupal_major_version()) {
    case 5:
    case 6:
      $url = $GLOBALS['db_url'];
      // TODO: array version not working?
      $url =  is_array($url) ? $url[$database] : $url;
      $url = parse_url($url);
      // Fill in defaults to prevent notices.
      $url += array(
        'driver' => NULL,
        'user' => NULL,
        'pass' => NULL,
        'port' => NULL,
        'database' => NULL,
      );
      $url = (object)$url;
      return array(
        'driver' => $url->scheme == 'mysqli' ? 'mysql' : $url->scheme,
        'username' => urldecode($url->user),
        'password' => urldecode($url->pass),
        'port' => urldecode($url->port),
        'host' => urldecode($url->host),
        'database' => substr(urldecode($url->path), 1), // skip leading '/' character≈
      );
      
    default:
      // We don't use DB API here `sql load` would have to messily addConnection.
      return $GLOBALS['databases'][$database][$target]; 
  }
}

// Build a $db_spec from a given settings.php.
function _drush_sql_get_spec_from_settings($file, $prefix) {
  // Reset global databases to whatever is defined in $file
  global $databases, $db_url;
  require $file;
  
  // Translate the custom options for sql load into ones usually recognized (i.e. no prefix).
  if ($database = drush_get_option($prefix . '_database')) {
    drush_set_option('database', $value);
  }
  if ($target = drush_get_option($prefix . '_target')) {
    drush_set_option('target', $target);
  }
  
  return _drush_sql_get_db_spec();
}

function _drush_sql_get_scheme($db_spec = NULL) {
  if (is_null($db_spec)) {
    $db_spec = _drush_sql_get_db_spec();
  }
  return $db_spec['driver'];
}

/**
 * Build a fragment containing credentials and mysql connection parameters.
 *
 * @param $db_spec 
 * @return string
 */
function _drush_sql_get_credentials($db_spec = NULL) {
  switch (_drush_sql_get_scheme()) {
    case 'mysql':
      if (is_null($db_spec)) {
        $db_spec = _drush_sql_get_db_spec();
      }

      $cred = ' -h' . $db_spec['host'] .
         (empty($db_spec['port']) ? '' : ' -P' . $db_spec['port']) .
         ' -u' . $db_spec['username'] .
         (empty($db_spec['password']) ? '' : ' -p' . $db_spec['password']) . ' ' . $db_spec['database'];
      break;
  case 'pgsql':
      if (is_null($db_spec)) {
        $db_spec = _drush_sql_get_db_spec();
      }

      $cred = $db_spec['database'] . ' ' .
         (empty($db_spec['host']) || ($db_spec['host'] == "localhost") ? '' : '-h ' . $db_spec['host']) . ' ' .
         (empty($db_spec['port']) ? '' : '-p' . $db_spec['port']) . ' ';
      // Adding '-U' will cause Postgres to prompt for a password, so disable this for now.
      // Use "sudo -u postgres drush sql ..." to access the database without a password,
      // presuming that "postgres" is the database superuser and you have
      // "local all all ident sameuser" in your Postgres pg_hba.conf file.
      // See: http://drupal.org/node/438828
      // $cred .= '-U ' . $db_spec['username'] . ' ';
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
