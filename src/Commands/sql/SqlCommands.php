<?php
namespace Drush\Commands\sql;

use Drupal\Core\Database\Database;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Drush\Sql\SqlBase;

class SqlCommands extends DrushCommands
{

    /**
     * Print database connection details using print_r().
     *
     * @command sql-conf
     * @option all Show all database connections, instead of just one.
     * @option show-passwords Show database password.
     * @optionset_sql
     * @hidden
     */
    public function conf($options = ['format' => 'yaml', 'all' => false, 'show-passwords' => false])
    {
        $this->further($options);
        if ($options['all']) {
            $return = Database::getAllConnectionInfo();
            foreach ($return as $key1 => $value) {
                foreach ($value as $key2 => $spec) {
                    if (!$options['show-passwords']) {
                        unset($return[$key1][$key2]['password']);
                    }
                }
            }
        } else {
            $sql = SqlBase::create($options);
            $return = $sql->getDbSpec();
            if (!$options['show-passwords']) {
                unset($return['password']);
            }
        }
        return $return;
    }

    /**
     * A string for connecting to the DB.
     *
     * @command sql-connect
     * @option extra Add custom options to the connect string (e.g. --extra=--skip-column-names)
     * @optionset_sql
     * @usage `drush sql-connect` < example.sql
     *   Bash: Import SQL statements from a file into the current database.
     * @usage eval (drush sql-connect) < example.sql
     *   Fish: Import SQL statements from a file into the current database.
     */
    public function connect($options = ['extra' => ''])
    {
        $this->further($options);
        $sql = SqlBase::create($options);
        return $sql->connect(false);
    }

    /**
     * Create a database.
     *
     * @command sql-create
     * @option db-su Account to use when creating a new database.
     * @option db-su-pw Password for the db-su account.
     * @optionset_sql
     * @usage drush sql-create
     *   Create the database for the current site.
     * @usage drush @site.test sql-create
     *   Create the database as specified for @site.test.
     * @usage drush sql-create --db-su=root --db-su-pw=rootpassword --db-url="mysql://drupal_db_user:drupal_db_password@127.0.0.1/drupal_db"
     *   Create the database as specified in the db-url option.
     */
    public function create($options = [])
    {
        $this->further($options);
        $sql = SqlBase::create($options);
        $db_spec = $sql->getDbSpec();
        // Prompt for confirmation.
        if (!drush_get_context('DRUSH_SIMULATE')) {
            // @todo odd - maybe for sql-sync.
            $txt_destination = (isset($db_spec['remote-host']) ? $db_spec['remote-host'] . '/' : '') . $db_spec['database'];
            drush_print(dt("Creating database !target. Any existing database will be dropped!", array('!target' => $txt_destination)));

            if (!$this->io()->confirm(dt('Do you really want to continue?'))) {
                throw new UserAbortException();
            }

            if (!$sql->createdb(true)) {
                throw new \Exception('Unable to create database. Rerun with --debug to see any error message.');
            }
        }
    }

    /**
     * Drop all tables in a given database.
     *
     * @command sql-drop
     * @optionset_sql
     * @topics docs-policy
     */
    public function drop($options = [])
    {
        $this->further($options);
        $sql = SqlBase::create($options);
        $db_spec = $sql->getDbSpec();
        if (!$this->io()->confirm(dt('Do you really want to drop all tables in the database !db?', array('!db' => $db_spec['database'])))) {
            throw new UserAbortException();
        }
        $tables = $sql->listTables();
        if (!$sql->drop($tables)) {
            throw new \Exception('Unable to drop database. Rerun with --debug to see any error message.');
        }
    }

    /**
     * Open a SQL command-line interface using Drupal's credentials.
     *
     * @command sql-cli
     * @optionset_sql
     * @allow-additional-options sql-connect
     * @aliases sqlc
     * @usage drush sql-cli
     *   Open a SQL command-line interface using Drupal's credentials.
     * @usage drush sql-cli --extra=-A
     *   Open a SQL CLI and skip reading table information.
     * @remote-tty
     */
    public function cli($options = [])
    {
        $this->further($options);
        $sql = SqlBase::create($options);
        if (!drush_shell_proc_open($sql->connect())) {
            throw new \Exception('Unable to open database shell. Rerun with --debug to see any error message.');
        }
    }

    /**
     * Execute a query against a database.
     *
     * @command sql-query
     * @param $query An SQL query. Ignored if --file is provided.
     * @optionset_sql
     * @option result-file Save to a file. The file should be relative to Drupal root.
     * @option file Path to a file containing the SQL to be run. Gzip files are accepted.
     * @option extra Add custom options to the connect string (e.g. --extra=--skip-column-names)
     * @option db-prefix Enable replacement of braces in your query.
     * @validate-file-exists file
     * @aliases sqlq
     * @usage drush sql-query "SELECT * FROM users WHERE uid=1"
     *   Browse user record. Table prefixes, if used, must be added to table names by hand.
     * @usage drush sql-query --db-prefix "SELECT * FROM {users}"
     *   Browse user record. Table prefixes are honored.  Caution: All curly-braces will be stripped.
     * @usage `drush sql-connect` < example.sql
     *   Import sql statements from a file into the current database.
     * @usage drush sql-query --file=example.sql
     *   Alternate way to import sql statements from a file.
     *
     */
    public function query($query = '', $options = ['result-file' => null, 'file' => null, 'extra' => null, 'db-prefix' => null])
    {
        $this->further($options);
        $filename = $options['file'];
        // Enable prefix processing when db-prefix option is used.
        if ($options['db-prefix']) {
            drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_DATABASE);
        }
        if (drush_get_context('DRUSH_SIMULATE')) {
            if ($query) {
                drush_print(dt('Simulating sql-query: !q', array('!q' => $query)));
            } else {
                drush_print(dt('Simulating sql-import from !f', array('!f' => $options['file'])));
            }
        } else {
            $sql = SqlBase::create($options);
            $result = $sql->query($query, $filename, $options['result-file']);
            if (!$result) {
                throw new \Exception(dt('Query failed.'));
            }
            drush_print(implode("\n", drush_shell_exec_output()));
        }
        return true;
    }

    /**
     * Exports the Drupal DB as SQL using mysqldump or equivalent.
     *
     * @command sql-dump
     * @optionset_sql
     * @optionset_table_selection
     * @option result-file Save to a file. The file should be relative to Drupal root.
     * @option create-db Omit DROP TABLE statements. Used by Postgres and Oracle only.
     * @option data-only Dump data without statements to create any of the schema.
     * @option ordered-dump Order by primary key and add line breaks for efficient diffs. Slows down the dump. Mysql only.
     * @option gzip Compress the dump using the gzip program which must be in your $PATH.
     * @option extra Add custom arguments/options when connecting to database (used internally to list tables).
     * @option extra-dump Add custom arguments/options to the dumping the database (e.g. mysqldump command).
     * @usage drush sql-dump --result-file=../18.sql
     *   Save SQL dump to the directory above Drupal root.
     * @usage drush sql-dump --skip-tables-key=common
     *   Skip standard tables. @see example.drushrc.php
     * @usage drush sql-dump --extra-dump=--no-data
     *   Pass extra option to mysqldump command.
     * @hidden-options create-db
     *
     * @notes
     *   createdb is used by sql-sync, since including the DROP TABLE statements interfere with the import when the database is created.
     */
    public function dump($options = ['result-file' => null, 'create-db' => null, 'data-only' => null, 'ordered-dump' => null, 'gzip' => null, 'extra' => null, 'extra-dump' => null])
    {
        $this->further($options);
        $sql = SqlBase::create($options);
        if ($sql->dump($options) === false) {
            throw new \Exception('Unable to drop database. Rerun with --debug to see any error message.');
        }
    }

    /**
     * Check whether further bootstrap is needed. If so, do it.
     */
    public function further($options)
    {
        if (empty($options['db-url']) && empty($options['db-spec'])) {
            drush_bootstrap_max(DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION);
        }
    }
}
