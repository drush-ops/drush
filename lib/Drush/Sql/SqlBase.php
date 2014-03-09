<?php

namespace Drush\Sql;

class SqlBase {

  // An Drupal style array containing specs for connecting to database.
  public $db_spec;

  // A site alias which provides and overrides part of the $db_spec.
  public $site_alias_record;

  // Default code appended to sql-query connections.
  public $query_extra = '';

  // The way you pass a sql file when issueing a query.
  public $query_file = '<';

  /**
   * This constructor defaults to honoring CLI options if
   * not explicitly passed.
   */
  public function __construct($db_spec = NULL, $site_alias_record = NULL) {
    // Determine $db_spec when none was provided.
    if (!$db_spec) {
      $database = drush_get_option('database', 'default');
      $target = drush_get_option('target', 'default');

      if ($url = drush_get_option('db-url')) {
        $url =  is_array($url) ? $url[$database] : $url;
        $db_spec = drush_convert_db_from_db_url($url);
        $db_spec['db_prefix'] = drush_get_option('db-prefix');
      }
      elseif (($databases = drush_get_option('databases')) && (array_key_exists($database, $databases)) && (array_key_exists($target, $databases[$database]))) {
        $db_spec = $databases[$database][$target];
      }
      elseif (drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION)) {
        switch (drush_drupal_major_version()) {
          case 6:
            if ($url = isset($GLOBALS['db_url']) ? $GLOBALS['db_url'] : drush_get_option('db-url', NULL)) {
              $url =  is_array($url) ? $url[$database] : $url;
              $db_spec = drush_convert_db_from_db_url($url);
              $db_spec['db_prefix'] = isset($GLOBALS['db_prefix']) ? $GLOBALS['db_prefix'] : drush_get_option('db-prefix', NULL);
            }
            break;
          default:
            // We don't use DB API here `sql-sync` would have to messily addConnection.
            if (!isset($GLOBALS['databases']) || !array_key_exists($database, $GLOBALS['databases']) || !array_key_exists($target, $GLOBALS['databases'][$database])) {
              // Do nothing
            }
            else {
              $db_spec = $GLOBALS['databases'][$database][$target];;
            }
        }
      }
    }

    if (empty($db_spec)) {
      throw new SqlException(dt('Could not find a matching database connection.'));
    }
    else {
      $this->db_spec = $db_spec;
    }
    $this->site_alias_record = $site_alias_record;
  }

  /**
   * The unix command used to connect to the database.
   * @return string
   */
  public function command() {}

  /**
   * A string for connecting to a database.
   *
   * @return string
   */
  public function connect() {
    return trim($this->command() . ' ' . $this->creds() . ' ' . drush_get_option('extra', $this->query_extra));
  }


  public function dump($file = '') {
    $table_selection = drush_sql_get_expanded_table_selection();
    $file = $this->dumpFile($file);
    list($cmd, $file) = $this->dumpCmd($table_selection, $file);
    list($suffix, $file) = $this->dumpGzip($file);
    // Avoid the php memory of the $output array in drush_shell_exec().
    if (!$return = drush_op_system($cmd)) {
      if ($file) {
        drush_log(dt('Database dump saved to !path', array('!path' => $file)), 'success');
        return $file;
      }
    }
    else {
      return drush_set_error('DRUSH_SQL_DUMP_FAIL', 'Database dump failed');
    }
  }

  /*
   * Build bash for dumping a database.
   *
   * @param array $table_selection
   *   Supported keys: 'skip', 'structure', 'tables'.
   * @param file
   *   Destination for the dump file.
   * @return array
   *   A two item indexed array.
   *     1. A mysqldump/pg_dump/sqlite3/etc statement that is ready for executing.
   *     2. The filepath where the dump will be saved.
   */
  public function dumpCmd($table_selection, $file) {}

  public function dumpFile($file) {
    $database = $this->db_spec['database'];

    // $file is passed in to us usually via --result-file.  If the user
    // has set $options['result-file'] = TRUE, then we
    // will generate an SQL dump file in the same backup
    // directory that pm-updatecode uses.
    if ($file) {
      if ($file === TRUE) {
        // User did not pass a specific value for --result-file. Make one.
        $backup = drush_include_engine('version_control', 'backup');
        $backup_dir = $backup->prepare_backup_dir($database);
        if (empty($backup_dir)) {
          $backup_dir = drush_find_tmp();
        }
        $file = $backup_dir . '/@DATABASE_@DATE.sql';
      }
      $file = str_replace(array('@DATABASE', '@DATE'), array($database, gmdate('Ymd_His')), $file);
    }
    return $file;
  }

  /*
   * Bash which gzips dump file if specified.
   *
   * @param string $file
   *
   * @return array
   */
  function dumpGzip($file) {
    $suffix = '';
    if (drush_get_option('gzip')) {
      if ($file) {
        $escfile = drush_escapeshellarg($file);
        if (drush_get_context('DRUSH_AFFIRMATIVE')) {
          // Gzip the result-file without Gzip confirmation
          $suffix = " && gzip -f $escfile";
          $file .= '.gz';
        }
        else {
          // Gzip the result-file
          $suffix = " && gzip $escfile";
          $file .= '.gz';
        }
      }
      else {
        // gzip via pipe since user has not specified a file.
        $suffix = "| gzip";
      }
    }
    return array($suffix, $file);
  }

  /**
   * Execute a SQL query.
   *
   * @param string $query
   *   The SQL to be executed. Should be NULL if $file is provided.
   * @param string $input_file
   *   A path to a file containing the SQL to be executed.
   * @param bool $save_output
   *   If TRUE, Drush uses a bit more memory to store query results. Use
   *   drush_shell_exec_output() to fetch them.
   */
  public function query($query, $input_file = NULL, $save_output = FALSE) {
    if ($input_file) {
      $query = file_get_contents($input_file);
    }
    $query = $this->query_prefix($query);
    $query = $this->query_format($query);

    // Save $query to a tmp file if needed. We will redirect it in.
    if (!$input_file) {
      // @todo
      $suffix = '';
      $input_file = drush_save_data_to_temp_file($query, $suffix);
    }

    $parts = array(
      $this->command(),
      $this->creds(),
      drush_get_option('extra', $this->query_extra),
      $this->query_file,
      drush_escapeshellarg($input_file),
    );
    $exec = implode(' ', $parts);

    if ($output_file = drush_get_option('result-file')) {
      $exec .= ' > '. drush_escapeshellarg($output_file);
    }
    elseif (!$save_output) {
      $exec .= ' > '. drush_escapeshellarg(drush_bit_bucket());
    }

    // In --simulate mode, drush_op will show the call to mysql or psql,
    // but the sql query itself is stored in a temp file and not displayed.
    // We will therefore show the query explicitly in the interest of debugging.
    if (drush_get_context('DRUSH_SIMULATE')) {
      drush_print('sql-query: ' . $query);
      if (!empty($exec)) {
        drush_print('exec: ' . $exec);
      }
      return TRUE;
    }

    if ($save_output) {
      return drush_shell_exec($exec);
    }
    else {
      return (drush_op_system($exec) == 0);
    }
  }

  public function query_prefix($query) {
    // Inject table prefixes as needed.
    if (drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_DATABASE)) {
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
    return $query;
  }


  public function query_format($query) {
    return $query;
  }

  public function drop($tables) {
    $sql = 'DROP TABLE '. implode(', ', $tables);
    return $this->query($sql);
  }

  /**
   * Build a SQL string for dropping and creating a database.
   *
   * @param boolean $quoted
   *   Quote the database name. Mysql uses backticks to quote which can cause problems
   *   in a Windows shell. Set TRUE if the CREATE is not running on the bash command line.
   */
  public function createdb_sql($quoted = FALSE) {}

  /**
   * Create a new database.
   *
   * @param boolean $quoted
   *   Quote the database name. Mysql uses backticks to quote which can cause problems
   *   in a Windows shell. Set TRUE if the CREATE is not running on the bash command line.
   */
  public function createdb($quoted = FALSE) {
    // Adjust connection to allow for superuser creds if provided.
    $dbname = $this->db_spec['database'];
    $this->su();
    return $this->query($this->createdb_sql($dbname));
  }

  /**
   * Drop all tables (if DB exists) or CREATE target database.
   *
   * return boolean
   *   TRUE or FALSE depending on success.
   */
  public function drop_or_create() {
    if ($this->db_exists()) {
      $this->drop($this->listTables());
    }
    else {
      $this->createdb();
    }
  }

  /*
   * Determine if the specified DB already exists.
   *
   * @return bool
   */
  public function db_exists() {}

  public function delete() {}

  /**
   * Build a fragment containing credentials and other connection parameters.
   * @return string
   */
  public function creds() {}

  /**
   * The active database driver.
   * @return string
   */
  public function scheme() {
    return $this->db_spec['driver'];
  }

  /**
   * Extract the name of all existing tables in the given database.
   *
   * @return array
   *   An array of table names which exist in the current database.
   */
  public function listTables() {}

  /*
   * Helper method to turn associative array into options with values.
   *
   * @return string
   *   A bash fragment.
   */
  public function params_to_options($parameters) {
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

  /**
   * Adjust DB connection with superuser credentials if provided.
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
   *
   * @return null
   */
  public function su() {
    $create_db_target = $this->db_spec;

    $create_db_target['database'] = '';
    $db_superuser = drush_sitealias_get_option($this->site_alias_record, 'db-su');
    if (isset($db_superuser)) {
      $create_db_target['username'] = $db_superuser;
    }
    $db_su_pw = drush_sitealias_get_option($this->site_alias_record, 'db-su-pw');
    // If --db-su-pw is not provided and --db-su is, default to empty password.
    // This way db cli command will take password from .my.cnf or .pgpass.
    if (!empty($db_su_pw)) {
      $create_db_target['password'] = $db_su_pw;
    }
    elseif (isset($db_superuser)) {
      unset($create_db_target['password']);
    }
    $this->db_spec = $create_db_target;
  }
}
