<?php

namespace Drush\Sql;

use Consolidation\SiteProcess\Util\Escape;
use Drupal\Core\Database\Database;
use Drush\Drush;
use Drush\Utils\FsUtils;
use Drush\Config\ConfigAwareTrait;
use Robo\Contract\ConfigAwareInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Webmozart\PathUtil\Path;
use Consolidation\Config\Util\Interpolator;

class SqlBase implements ConfigAwareInterface
{

    use SqlTableSelectionTrait;
    use ConfigAwareTrait;

    // An Drupal style array containing specs for connecting to database.
    public $dbSpec;

    // Default code appended to sql connections.
    public $queryExtra = '';

    // The way you pass a sql file when issueing a query.
    public $queryFile = '<';

    // An options array.
    public $options;

    /**
     * @var Process
     */
    protected $process;

    /**
     * Typically, SqlBase instances are constructed via SqlBase::create($options).
     */
    public function __construct($db_spec, $options)
    {
        $this->dbSpec = $db_spec;
        $this->options = $options;
    }

    /**
     * Get environment variables to pass to Process.
     */
    public function getEnv()
    {
    }

    /**
     * Get the last used Process.
     *
     * @return Process
     */
    public function getProcess()
    {
        return $this->process;
    }

    /**
     * @param Process $process
     */
    public function setProcess($process)
    {
        $this->process = $process;
    }

    /**
     * Get a driver specific instance of this class.
     *
     * @param $options
     *   An options array as handed to a command callback.
     * @return SqlBase
     */
    public static function create($options = [])
    {
        // Set defaults in the unfortunate event that caller doesn't provide values.
        $options += [
            'database' => 'default',
            'target' => 'default',
            'db-url' => null,
            'databases' => null,
            'db-prefix' => null,
        ];
        $database = $options['database'];
        $target = $options['target'];

        if ($url = $options['db-url']) {
            $url = is_array($url) ? $url[$database] : $url;
            $db_spec = self::dbSpecFromDbUrl($url);
            $db_spec['prefix'] = $options['db-prefix'];
            return self::getInstance($db_spec, $options);
        } elseif (($databases = $options['databases']) && (array_key_exists($database, $databases)) && (array_key_exists($target, $databases[$database]))) {
            // @todo 'databases' option is not declared anywhere?
            $db_spec = $databases[$database][$target];
            return self::getInstance($db_spec, $options);
        } elseif ($info = Database::getConnectionInfo($database)) {
            $db_spec = $info[$target];
            return self::getInstance($db_spec, $options);
        } else {
            throw new \Exception(dt('Unable to load Drupal settings. Check your --root, --uri, etc.'));
        }
    }

    public static function getInstance($db_spec, $options)
    {
        $driver = $db_spec['driver'];
        $class_name = 'Drush\Sql\Sql'. ucfirst($driver);
        $instance = new $class_name($db_spec, $options);
        // Inject config
        $instance->setConfig(Drush::config());
        return $instance;
    }

    /*
     * Get the current $db_spec.
     */
    public function getDbSpec()
    {
        return $this->dbSpec;
    }

    /**
     * Set the current db spec.
     *
     * @param array $dbSpec
     */
    public function setDbSpec($dbSpec)
    {
        $this->dbSpec = $dbSpec;
    }

    /**
     * The unix command used to connect to the database.
     * @return string
     */
    public function command()
    {
    }

    /**
     * A string for connecting to a database.
     *
     * @param bool $hide_password
     *  If TRUE, DBMS should try to hide password from process list.
     *  On mysql, that means using --defaults-file to supply the user+password.
     *
     * @return string
     */
    public function connect($hide_password = true)
    {
        return trim($this->command() . ' ' . $this->creds($hide_password) . ' ' . $this->getOption('extra', $this->queryExtra));
    }


    /*
     * Execute a SQL dump and return the path to the resulting dump file.
     *
     * @return string|bool
     *   Returns path to dump file, or false on failure.
     */
    public function dump()
    {
        /** @var string|bool $file Path where dump file should be stored. If TRUE, generate a path based on usual backup directory and current date.*/
        $file = $this->getOption('result-file');
        $file_suffix = '';
        $table_selection = $this->getExpandedTableSelection($this->getOptions(), $this->listTables());
        $file = $this->dumpFile($file);
        $cmd = $this->dumpCmd($table_selection);
        $pipefail = '';
        // Gzip the output from dump command(s) if requested.
        if ($this->getOption('gzip')) {
            // See https://github.com/drush-ops/drush/issues/3816.
            $pipefail = $this->getConfig()->get('sh.pipefail', 'bash -c "set -o pipefail; {{cmd}}"');
            $cmd .= " | gzip -f";
            $file_suffix .= '.gz';
        }
        if ($file) {
            $file .= $file_suffix;
            $cmd .= ' > ' . Escape::shellArg($file);
        }
        $cmd = $this->addPipeFail($cmd, $pipefail);

        $process = Drush::shell($cmd, null, $this->getEnv());
        // Avoid the php memory of saving stdout.
        $process->disableOutput();
        // Show dump in real-time on stdout, for backward compat.
        $process->run($process->showRealtime());
        return $process->isSuccessful() ? $file : false;
    }

    /**
     * Handle 'pipefail' option for the specified command.
     *
     * @param string $cmd Script command to execute; should contain a pipe command
     * @param string $pipefail Script statements to insert into / wrap around $cmd
     * @return string Result varies based on value of $pipefail
     *   - empty: Return $cmd unmodified
     *   - simple string: Return $cmd appended to $pipefail
     *   - interpolated: Add slashes to $cmd and insert in $pipefail
     *
     * Interpolation is particularly for environments such as Ubuntu
     * that use something other than bash as the default shell. To
     * make pipefail work right in this instance, we must wrap it
     * in 'bash -c', since pipefail is a bash feature.
     */
    protected function addPipeFail($cmd, $pipefail)
    {
        if (empty($pipefail)) {
            return $cmd;
        }
        if (strpos($pipefail, '{{cmd}}') === false) {
            return $pipefail . ' ' . $cmd;
        }
        $interpolator = new Interpolator();
        $replacements = [
            'cmd' => str_replace('"', '\\"', $cmd),
        ];
        return $interpolator->interpolate($replacements, $pipefail);
    }

    /*
     * Build bash for dumping a database.
     *
     * @param array $table_selection
     *   Supported keys: 'skip', 'structure', 'tables'.
     * @return string
     *   One or more mysqldump/pg_dump/sqlite3/etc statements that are ready for executing.
     *   If multiple statements are needed, enclose in parenthesis.
     */
    public function dumpCmd($table_selection)
    {
    }

    /*
     * Generate a path to an output file for a SQL dump when needed.
     *
     * @param string|bool @file
     *   If TRUE, generate a path based on usual backup directory and current date.
     *   Otherwise, just return the path that was provided.
     */
    public function dumpFile($file)
    {
        $database = $this->dbSpec['database'];

        // $file is passed in to us usually via --result-file.  If the user
        // has set $options['result-file'] = 'auto', then we
        // will generate an SQL dump file in the backup directory.
        if ($file) {
            if ($file === 'auto') {
                $backup_dir = FsUtils::prepareBackupDir($database);
                if (empty($backup_dir)) {
                    $backup_dir = $this->getConfig()->tmp();
                }
                $file = Path::join($backup_dir, '@DATABASE_@DATE.sql');
            }
            $file = str_replace(['@DATABASE', '@DATE'], [$database, gmdate('Ymd_His')], $file);
        }
        return $file;
    }

    /**
     * Execute a SQL query. Respect simulate mode.
     *
     * If you don't want to query results to print during --debug then
     * provide a $result_file whose value can be drush_bit_bucket().
     *
     * @param string $query
     *   The SQL to be executed. Should be NULL if $input_file is provided.
     * @param string $input_file
     *   A path to a file containing the SQL to be executed.
     * @param string $result_file
     *   A path to save query results to. Can be drush_bit_bucket() if desired.
     *
     * @return boolean
     *   TRUE on success, FALSE on failure
     */
    public function query($query, $input_file = null, $result_file = '')
    {
        if (!Drush::simulate()) {
            return $this->alwaysQuery($query, $input_file, $result_file);
        }
        $this->logQueryInDebugMode($query, $input_file);
    }

    /**
     * Execute a SQL query. Always execute regardless of simulate mode.
     *
     * If you don't want results to print during --debug then
     * provide a $result_file whose value can be drush_bit_bucket().
     *
     * @param string $query
     *   The SQL to be executed. Should be null if $input_file is provided.
     * @param string $input_file
     *   A path to a file containing the SQL to be executed.
     * @param string $result_file
     *   A path to save query results to. Can be drush_bit_bucket() if desired.
     *
     * @return boolean
     *   TRUE on success, FALSE on failure
     */
    public function alwaysQuery($query, $input_file = null, $result_file = '')
    {
        $input_file_original = $input_file;
        if ($input_file && drush_file_is_tarball($input_file)) {
            $process = Drush::process(['gzip', '-d', $input_file]);
            $process->setSimulated(false);
            $process->run();
            $this->setProcess($process);
            if ($process->isSuccessful()) {
                $input_file = trim($input_file, '.gz');
            } else {
                Drush::logger()->error(dt('Failed to decompress input file.'));
                return false;
            }
        }

        // Save $query to a tmp file if needed. We redirect it in.
        if (!$input_file) {
            $query = $this->queryPrefix($query);
            $query = $this->queryFormat($query);
            $input_file = drush_save_data_to_temp_file($query);
        }

        $parts = [
            $this->command(),
            $this->creds(),
            $this->silent(), // This removes column header and various helpful things in mysql.
            $this->getOption('extra', $this->queryExtra),
            $this->queryFile,
            Escape::shellArg($input_file),
        ];
        $exec = implode(' ', $parts);

        if ($result_file) {
            $exec .= ' > '. Escape::shellArg($result_file);
        }

        // In --verbose mode, Process will show the call to mysql/psql/sqlite,
        // but the sql query itself is stored in a temp file and not displayed.
        // We show the query when --debug is used and this function created the temp file.
        $this->logQueryInDebugMode($query, $input_file_original);

        $process = Drush::shell($exec, null, $this->getEnv());
        $process->setSimulated(false);
        $process->run();
        $success = $process->isSuccessful();
        $this->setProcess($process);

        if ($success && $this->getOption('file-delete')) {
            $fs = new Filesystem();
            $fs->remove($input_file);
        }

        return $success;
    }

    /**
     * Show the query in debug mode and simulate mode
     */
    protected function logQueryInDebugMode($query, $input_file_original)
    {
        // In --verbose mode, Drush::process() will show the call to mysql/psql/sqlite,
        // but the sql query itself is stored in a temp file and not displayed.
        // We show the query when --debug is used and this function created the temp file.
        if ((Drush::debug() || Drush::simulate()) && empty($input_file_original)) {
            Drush::logger()->info('sql:query: ' . $query);
        }
    }

    /*
     * A string to add to the command when queries should not print their results.
     */
    public function silent()
    {
    }


    public function queryPrefix($query)
    {
        // Inject table prefixes as needed.
        if (Drush::bootstrapManager()->hasBootstrapped(DRUSH_BOOTSTRAP_DRUPAL_DATABASE)) {
            // Enable prefix processing which can be dangerous so off by default. See http://drupal.org/node/1219850.
            if ($this->getOption('db-prefix')) {
                $query = Database::getConnection()->prefixTables($query);
            }
        }
        return $query;
    }


    public function queryFormat($query)
    {
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
    public function drop($tables)
    {
        $return = true;
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
     * @return string
     */
    public function createdbSql($dbname, $quoted = false)
    {
    }

    /**
     * Create a new database.
     *
     * @param boolean $quoted
     *   Quote the database name. Mysql uses backticks to quote which can cause problems
     *   in a Windows shell. Set TRUE if the CREATE is not running on the bash command line.
     * @return boolean
     *   True if successful, FALSE otherwise.
     */
    public function createdb($quoted = false)
    {
        $dbname = $this->getDbSpec()['database'];
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
    public function dropOrCreate()
    {
        if ($this->dbExists()) {
            return $this->drop($this->listTables());
        } else {
            return $this->createdb();
        }
    }

    /*
     * Determine if the specified DB already exists.
     *
     * @return bool
     */
    public function dbExists()
    {
    }

    public function delete()
    {
    }

    /**
     * Build a fragment connection parameters.
     *
     * @param bool $hide_password
     *  If TRUE, DBMS should try to hide password from process list.
     *  On mysql, that means using --defaults-file to supply the user+password.
     * @return string
     */
    public function creds($hide_password = true)
    {
    }

    /**
     * The active database driver.
     * @return string
     */
    public function scheme()
    {
        return $this->dbSpec['driver'];
    }

    /**
     * Extract the name of all existing tables in the given database.
     *
     * @return array|null
     *   An array of table names which exist in the current database.
     */
    public function listTables()
    {
    }

    /*
     * Helper method to turn associative array into options with values.
     *
     * @return string
     *   A bash fragment.
     */
    public function paramsToOptions($parameters)
    {
        // Turn each parameter into a valid parameter string.
        $parameter_strings = [];
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
    public function su()
    {
        $create_db_target = $this->getDbSpec();

        $create_db_target['database'] = '';
        $db_superuser = $this->getOption('db-su');
        if (!empty($db_superuser)) {
            $create_db_target['username'] = $db_superuser;
        }
        $db_su_pw = $this->getOption('db-su-pw');
        // If --db-su-pw is not provided and --db-su is, default to empty password.
        // This way db cli command will take password from .my.cnf or .pgpass.
        if (!empty($db_su_pw)) {
            $create_db_target['password'] = $db_su_pw;
        } elseif (!empty($db_superuser)) {
            unset($create_db_target['password']);
        }
        $this->setDbSpec($create_db_target);
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    public function getOption($name, $default = null)
    {
        $options = $this->getOptions();
        return array_key_exists($name, $options) && !is_null($options[$name]) ? $options[$name] : $default;
    }

    /**
     * @deprecated.
     */
    public function db_spec() // @codingStandardsIgnoreLine
    {
        return $this->getDbSpec();
    }

    /**
     * Convert from an old-style database URL to an array of database settings.
     *
     * @param db_url
     *   A Drupal 6 db url string to convert, or an array with a 'default' element.
     * @return array
     *   An array of database values containing only the 'default' element of
     *   the db url. If the parse fails the array is empty.
     */
    public static function dbSpecFromDbUrl($db_url)
    {
        $db_spec = [];

        if (is_array($db_url)) {
            $db_url_default = $db_url['default'];
        } else {
            $db_url_default = $db_url;
        }

        // If it's a sqlite database, pick the database path and we're done.
        if (strpos($db_url_default, 'sqlite://') === 0) {
            $db_spec = [
                'driver'   => 'sqlite',
                'database' => substr($db_url_default, strlen('sqlite://')),
            ];
        } else {
            $url = parse_url($db_url_default);
            if ($url) {
                // Fill in defaults to prevent notices.
                $url += [
                    'scheme' => null,
                    'user'   => null,
                    'pass'   => null,
                    'host'   => null,
                    'port'   => null,
                    'path'   => null,
                ];
                $url = (object)array_map('urldecode', $url);
                $db_spec = [
                    'driver'   => $url->scheme == 'mysqli' ? 'mysql' : $url->scheme,
                    'username' => $url->user,
                    'password' => $url->pass,
                    'host' => $url->host,
                    'port' => $url->port,
                    'database' => ltrim($url->path, '/'),
                ];
            }
        }

        return $db_spec;
    }
}
