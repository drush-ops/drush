<?php

namespace Drush\Sql;

use Drupal\Core\Database\Database;
use Drush\Log\LogLevel;
use Webmozart\PathUtil\Path;

class SqlBase {

  use SqlTableSelectionTrait;

  // An Drupal style array containing specs for connecting to database.
  public $dbSpec;

  // Default code appended to sql-query connections.
  public $queryExtra = '';

  // The way you pass a sql file when issueing a query.
  public $queryFile = '<';

  /**
   * Typically, SqlBase instances are constructed via SqlBase::create($options).
   */
  public function __construct($db_spec = NULL) {
    $this->dbSpec = $db_spec;
  }

  /**
   * Get a driver specific instance of this class.
   *
   * @param $options
   *   An options array as handed to a command callback.
   * @return SqlBase
   */
  public static function create($options = []) {
    // Set defaults in the unfortunate event that caller doesn't provide values.
    $options += [
      'database' => 'default',
      'target' => 'default',
      'db-url' => NULL,
      'databases' => NULL,
      'db-prefix' => NULL,
    ];
    $database = $options['database'];
    $target = $options['target'];

    if ($url = $options['db-url']) {
      $url =  is_array($url) ? $url[$database] : $url;
      $db_spec = drush_convert_db_from_db_url($url);
      $db_spec['db_prefix'] = $options['db-prefix'];
      return self::getInstance($db_spec);
    }
    elseif (($databases = $options['databases']) && (array_key_exists($database, $databases)) && (array_key_exists($target, $databases[$database]))) {
      // @todo 'databases' option is not declared anywhere?
      $db_spec = $databases[$database][$target];
      return self::getInstance($db_spec);
    }
    elseif ($info = Database::getConnectionInfo($database)) {
      $db_spec = $info[$target];
      return self::getInstance($db_spec);
    }
    else {
      throw new \Exception(dt('Unable to load Drupal settings. Check your --root, --uri, etc.'));
    }
  }

  public static function getInstance($db_spec) {
    $driver = $db_spec['driver'];
    $class_name = 'Drush\Sql\Sql'. ucfirst($driver);
    return new $class_name($db_spec);
  }

  /*
   * Get the current $db_spec.
   */
  public function getDbSpec() {
    return $this->dbSpec;
  }

  /**
   * The unix command used to connect to the database.
   * @return string
   */
  public function command() {}

  /**
   * A string for connecting to a database.
   *
   * @param bool $hide_password
   *  If TRUE, DBMS should try to hide password from process list.
   *  On mysql, that means using --defaults-extra-file to supply the user+password.
   *
   * @return string
   */
  public function connect($hide_password = TRUE) {
    return trim($this->command() . ' ' . $this->creds($hide_password) . ' ' . drush_get_option('extra', $this->queryExtra));
  }


  /*
   * Execute a SQL dump and return the path to the resulting dump file.
   *
   * @param array @options
   *   The options array as passed to the Annotated Command.
   */
  public function dump($options) {
    /** @var string|bool $file Path where dump file should be stored. If TRUE, generate a path based on usual backup directory and current date.*/
    $file = isset($options['result-file']) ? $options['result-file'] : NULL;
    $file_suffix = '';
    $table_selection = $this->getExpandedTableSelection($options);
    $file = $this->dumpFile($file);
    $cmd = $this->dumpCmd($table_selection, $options);
    // Gzip the output from dump command(s) if requested.
    if ($options['gzip']) {
      $cmd .= ' | gzip -f';
      $file_suffix .= '.gz';
    }
    if ($file) {
      $file .= $file_suffix;
      $cmd .= ' > ' . drush_escapeshellarg($file);
    }

    // Avoid the php memory of the $output array in drush_shell_exec().
    if (!$return = drush_op_system($cmd)) {
      if ($file) {
        drush_log(dt('Database dump saved to !path', array('!path' => $file)), LogLevel::SUCCESS);
        drush_backend_set_result($file);
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
   * @param array $options
   *   An options array as passed by an Annotated Command.
   * @return string
   *   One or more mysqldump/pg_dump/sqlite3/etc statements that are ready for executing.
   *   If multiple statements are needed, enclose in parenthesis.
   */
  public function dumpCmd($table_selection, $options) {}

  /*
   * Generate a path to an output file for a SQL dump when needed.
   *
   * @param string|bool @file
   *   If TRUE, generate a path based on usual backup directory and current date.
   *   Otherwise, just return the path that was provided.
   */
  public function dumpFile($file) {
    $database = $this->dbSpec['database'];

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
        $file = Path::join($backup_dir, '@DATABASE_@DATE.sql');
      }
      $file = str_replace(array('@DATABASE', '@DATE'), array($database, gmdate('Ymd_His')), $file);
    }
    return $file;
  }

  /**
   * Execute a SQL query.
   *
   * Note: This is an API function. Try to avoid using drush_get_option() and instead
   * pass params in. If you don't want to query results to print during --debug then
   * provide a $result_file whose value can be drush_bit_bucket().
   *
   * @param string $query
   *   The SQL to be executed. Should be NULL if $input_file is provided.
   * @param string $input_file
   *   A path to a file containing the SQL to be executed.
   * @param string $result_file
   *   A path to save query results to. Can be drush_bit_bucket() if desired.
   *
   * @return
   *   TRUE on success, FALSE on failure
   */
  public function query($query, $input_file = NULL, $result_file = '') {
    $input_file_original = $input_file;
    if ($input_file && drush_file_is_tarball($input_file)) {
      if (drush_shell_exec('gzip -d %s', $input_file)) {
        $input_file = trim($input_file, '.gz');
      }
      else {
        return drush_set_error(dt('Failed to decompress input file.'));
      }
    }

    // Save $query to a tmp file if needed. We will redirect it in.
    if (!$input_file) {
      $query = $this->queryPrefix($query);
      $query = $this->queryFormat($query);
      $input_file = drush_save_data_to_temp_file($query);
    }

    $parts = array(
      $this->command(),
      $this->creds(),
      $this->silent(), // This removes column header and various helpful things in mysql.
      drush_get_option('extra', $this->queryExtra),
      $this->queryFile,
      drush_escapeshellarg($input_file),
    );
    $exec = implode(' ', $parts);

    if ($result_file) {
      $exec .= ' > '. drush_escapeshellarg($result_file);
    }

    // In --verbose mode, drush_shell_exec() will show the call to mysql/psql/sqlite,
    // but the sql query itself is stored in a temp file and not displayed.
    // We show the query when --debug is used and this function created the temp file.
    if ((drush_get_context('DRUSH_DEBUG') || drush_get_context('DRUSH_SIMULATE')) && empty($input_file_original)) {
      drush_log('sql-query: ' . $query, LogLevel::INFO);
    }

    $success = drush_shell_exec($exec);

    if ($success && drush_get_option('file-delete')) {
      drush_op('drush_delete_dir', $input_file);
    }

    return $success;
  }

  /*
   * A string to add to the command when queries should not print their results.
   */
  public function silent() {}


  public function queryPrefix($query) {
    // Inject table prefixes as needed.
    if (drush_has_boostrapped(DRUSH_BOOTSTRAP_DRUPAL_DATABASE)) {
      // Enable prefix processing which can be dangerous so off by default. See http://drupal.org/node/1219850.
      if (drush_get_option('db-prefix')) {
        if (drush_drupal_major_version() >= 7) {
          $query = \Database::getConnection()->prefixTables($query);
        }
        else {
          $query = db_prefix_tables($query);
        }
      }
    }
    return $query;
  }


  public function queryFormat($query) {
    return $query;
  }

  /**
   * Drop specified database.
   *
   * @param array $tables
   *   An array of table names
   * @return boolean
   *   True if successful, FALSE if failed.
   */
  public function drop($tables) {
    $return = TRUE;
    if ($tables) {
      $sql = 'DROP TABLE '. implode(', ', $tables);
      $return = $this->query($sql);
    }
    return $return;
  }

  /**
   * Build a SQL string for dropping and creating a database.
   *
   * @param string dbname
   *   The database name.
   * @param boolean $quoted
   *   Quote the database name. Mysql uses backticks to quote which can cause problems
   *   in a Windows shell. Set TRUE if the CREATE is not running on the bash command line.
   */
  public function createdbSql($dbname, $quoted = FALSE) {}

  /**
   * Create a new database.
   *
   * @param boolean $quoted
   *   Quote the database name. Mysql uses backticks to quote which can cause problems
   *   in a Windows shell. Set TRUE if the CREATE is not running on the bash command line.
   * @return boolean
   *   True if successful, FALSE otherwise.
   */
  public function createdb($quoted = FALSE) {
    $dbname = $this->dbSpec['database'];
    $sql = $this->createdbSql($dbname, $quoted);
    // Adjust connection to allow for superuser creds if provided.
    $this->su();
    return $this->query($sql);
  }

  /**
   * Drop all tables (if DB exists) or CREATE target database.
   *
   * return boolean
   *   TRUE or FALSE depending on success.
   */
  public function dropOrCreate() {
    if ($this->dbExists()) {
      return $this->drop($this->listTables());
    }
    else {
      return $this->createdb();
    }
  }

  /*
   * Determine if the specified DB already exists.
   *
   * @return bool
   */
  public function dbExists() {}

  public function delete() {}

  /**
   * Build a fragment connection parameters.
   *
   * @param bool $hide_password
   *  If TRUE, DBMS should try to hide password from process list.
   *  On mysql, that means using --defaults-extra-file to supply the user+password.
   * @return string
   */
  public function creds($hide_password = TRUE) {}

  /**
   * The active database driver.
   * @return string
   */
  public function scheme() {
    return $this->dbSpec['driver'];
  }

  /**
   * Extract the name of all existing tables in the given database.
   *
   * @return array|null
   *   An array of table names which exist in the current database.
   */
  public function listTables() {}

  /*
   * Helper method to turn associative array into options with values.
   *
   * @return string
   *   A bash fragment.
   */
  public function paramsToOptions($parameters) {
    // Turn each parameter into a valid parameter string.
    $parameter_strings = array();
    foreach ($parameters as $key => $value) {
      // Only escape the values, not the keys or the rest of the string.
      $value = drush_escapeshellarg($value);
      $parameter_strings[] = "--$key=$value";
    }

    // Join the parameters and return.
    return implode(' ', $parameter_strings);
  }

  /**
   * Adjust DB connection with superuser credentials if provided.
   *
   * The options 'db-su' and 'db-su-pw' will be retrieved from the
   * specified site alias record.
   *
   * @return null
   */
  public function su() {
    $create_db_target = $this->dbSpec;

    $create_db_target['database'] = '';
    $db_superuser = drush_get_option('db-su');
    if (isset($db_superuser)) {
      $create_db_target['username'] = $db_superuser;
    }
    $db_su_pw = drush_get_option('db-su-pw');
    // If --db-su-pw is not provided and --db-su is, default to empty password.
    // This way db cli command will take password from .my.cnf or .pgpass.
    if (!empty($db_su_pw)) {
      $create_db_target['password'] = $db_su_pw;
    }
    elseif (isset($db_superuser)) {
      unset($create_db_target['password']);
    }
    $this->dbSpec = $create_db_target;
  }
}
