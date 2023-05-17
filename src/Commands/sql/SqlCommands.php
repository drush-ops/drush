<?php

namespace Drush\Commands\sql;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Input\StdinAwareInterface;
use Consolidation\AnnotatedCommand\Input\StdinAwareTrait;
use Consolidation\SiteProcess\Util\Tty;
use Drupal\Core\Database\Database;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Drush\Exec\ExecTrait;
use Drush\Sql\SqlBase;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Symfony\Component\Console\Input\InputInterface;

class SqlCommands extends DrushCommands implements StdinAwareInterface
{
    use ExecTrait;
    use StdinAwareTrait;

    /**
     * Print database connection details.
     *
     * @command sql:conf
     * @aliases sql-conf
     * @option all Show all database connections, instead of just one.
     * @optionset_sql
     * @bootstrap max configuration
     * @hidden
     */
    public function conf($options = ['format' => 'yaml', 'all' => false, 'show-passwords' => false]): ?array
    {
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
     * @command sql:connect
     * @aliases sql-connect
     * @option extra Add custom options to the connect string (e.g. --extra=--skip-column-names)
     * @optionset_sql
     * @bootstrap max configuration
     * @usage $(drush sql-connect) < example.sql
     *   Bash: Import SQL statements from a file into the current database.
     * @usage eval (drush sql-connect) < example.sql
     *   Fish: Import SQL statements from a file into the current database.
     */
    public function connect($options = ['extra' => self::REQ]): string
    {
        $sql = SqlBase::create($options);
        return $sql->connect(false);
    }

    /**
     * Create a database.
     *
     * @command sql:create
     * @aliases sql-create
     * @option db-su Account to use when creating a new database.
     * @option db-su-pw Password for the db-su account.
     * @optionset_sql
     * @usage drush sql:create
     *   Create the database for the current site.
     * @usage drush @site.test sql-create
     *   Create the database as specified for @site.test.
     * @usage drush sql:create --db-su=root --db-su-pw=rootpassword --db-url="mysql://drupal_db_user:drupal_db_password@127.0.0.1/drupal_db"
     *   Create the database as specified in the db-url option.
     * @bootstrap max configuration
     */
    public function create($options = ['db-su' => self::REQ, 'db-su-pw' => self::REQ]): void
    {
        $sql = SqlBase::create($options);
        $db_spec = $sql->getDbSpec();

        $this->output()->writeln(dt("Creating database !target. Any existing database will be dropped!", ['!target' => $db_spec['database']]));
        if (!$this->getConfig()->simulate() && !$this->io()->confirm(dt('Do you really want to continue?'))) {
            throw new UserAbortException();
        }

        if (!$sql->createdb(true)) {
            throw new \Exception('Unable to create database. Rerun with --debug to see any error message.  ' . $sql->getProcess()->getErrorOutput());
        }
    }

    /**
     * Drop all tables in a given database.
     *
     * @command sql:drop
     * @aliases sql-drop
     * @optionset_sql
     * @bootstrap max configuration
     * @topics docs:policy
     */
    public function drop($options = []): void
    {
        $sql = SqlBase::create($options);
        $db_spec = $sql->getDbSpec();
        if (!$this->io()->confirm(dt('Do you really want to drop all tables in the database !db?', ['!db' => $db_spec['database']]))) {
            throw new UserAbortException();
        }
        $tables = $sql->listTablesQuoted();
        if (!$sql->drop($tables)) {
            throw new \Exception('Unable to drop all tables: ' . $sql->getProcess()->getErrorOutput());
        }
    }

    /**
     * Open a SQL command-line interface using Drupal's credentials.
     *
     * @command sql:cli
     * @option extra Add custom options to the connect string
     * @optionset_sql
     * @aliases sqlc,sql-cli
     * @usage drush sql:cli
     *   Open a SQL command-line interface using Drupal's credentials.
     * @usage drush sql:cli --extra=--progress-reports
     *   Open a SQL CLI and skip reading table information.
     * @usage drush sql:cli < example.sql
     *   Import sql statements from a file into the current database.
     * @remote-tty
     * @bootstrap max configuration
     */
    public function cli(InputInterface $input, $options = ['extra' => self::REQ]): void
    {
        $sql = SqlBase::create($options);
        $process = $this->processManager()->shell($sql->connect(), null, $sql->getEnv());
        if (!Tty::isTtySupported()) {
            $process->setInput($this->stdin()->getStream());
        } else {
            $process->setTty($this->getConfig()->get('ssh.tty', $input->isInteractive()));
        }
        $process->mustRun($process->showRealtime());
    }

    /**
     * Execute a query against a database.
     *
     * @command sql:query
     * @param $query An SQL query. Ignored if --file is provided.
     * @optionset_sql
     * @option result-file Save to a file. The file should be relative to Drupal root.
     * @option file Path to a file containing the SQL to be run. Gzip files are accepted.
     * @option file-delete Delete the --file after running it.
     * @option extra Add custom options to the connect string (e.g. --extra=--skip-column-names)
     * @option db-prefix Enable replacement of braces in your query.
     * @validate-file-exists file
     * @aliases sqlq,sql-query
     * @usage drush sql:query "SELECT * FROM users WHERE uid=1"
     *   Browse user record. Table prefixes, if used, must be added to table names by hand.
     * @usage drush sql:query --db-prefix "SELECT * FROM {users}"
     *   Browse user record. Table prefixes are honored.  Caution: All curly-braces will be stripped.
     * @usage $(drush sql:connect) < example.sql
     *   Import sql statements from a file into the current database.
     * @usage drush sql:query --file=example.sql
     *   Alternate way to import sql statements from a file.
     * @usage drush ev "return db_query('SELECT * FROM users')->fetchAll()" --format=json
     *   Get data back in JSON format. See https://github.com/drush-ops/drush/issues/3071#issuecomment-347929777.
     * @usage `drush sql:connect` -e "select * from users limit 5;"
     *   Results are formatted in a pretty table with borders and column headers.
     * @bootstrap max configuration
     *
     */
    public function query($query = '', $options = ['result-file' => null, 'file' => self::REQ, 'file-delete' => false, 'extra' => self::REQ, 'db-prefix' => false]): bool
    {
        $filename = $options['file'];
        // Enable prefix processing when db-prefix option is used.
        if ($options['db-prefix']) {
            Drush::bootstrapManager()->bootstrapMax(DRUSH_BOOTSTRAP_DRUPAL_DATABASE);
        }
        if ($this->getConfig()->simulate()) {
            if ($query) {
                $this->output()->writeln(dt('Simulating sql:query: !q', ['!q' => $query]));
            } else {
                $this->output()->writeln(dt('Simulating sql:query from file !f', ['!f' => $options['file']]));
            }
        } else {
            $sql = SqlBase::create($options);
            $result = $sql->query($query, $filename, $options['result-file']);
            if (!$result) {
                throw new \Exception('Query failed. Rerun with --debug to see any error message. ' . $sql->getProcess()->getErrorOutput());
            }
            $this->output()->writeln($sql->getProcess()->getOutput());
        }
        return true;
    }

    /**
     * Exports the Drupal DB as SQL using mysqldump or equivalent.
     *
     * @command sql:dump
     * @aliases sql-dump
     * @optionset_sql
     * @optionset_table_selection
     * @option result-file Save to a file. The file should be relative to Drupal root. If --result-file is provided with the value 'auto', a date-based filename will be created under ~/drush-backups directory.
     * @option create-db Omit DROP TABLE statements. Used by Postgres and Oracle only.
     * @option data-only Dump data without statements to create any of the schema.
     * @option ordered-dump Order by primary key and add line breaks for efficient diffs. Slows down the dump. Mysql only.
     * @option gzip Compress the dump using the gzip program which must be in your <info>$PATH</info>.
     * @option extra Add custom arguments/options when connecting to database (used internally to list tables).
     * @option extra-dump Add custom arguments/options to the dumping of the database (e.g. <info>mysqldump</info> command).
     * @usage drush sql:dump --result-file=../18.sql
     *   Save SQL dump to the directory above Drupal root.
     * @usage drush sql:dump --skip-tables-key=common
     *   Skip standard tables. See [Drush configuration](../../using-drush-configuration)
     * @usage drush sql:dump --extra-dump=--no-data
     *   Pass extra option to <info>mysqldump</info> command.
     * @hidden-options create-db
     * @bootstrap max configuration
     * @field-labels
     *   path: Path
     *
     *
     * @notes
     *   --createdb is used by sql-sync, since including the DROP TABLE statements interferes with the import when the database is created.
     */
    public function dump($options = ['result-file' => self::REQ, 'create-db' => false, 'data-only' => false, 'ordered-dump' => false, 'gzip' => false, 'extra' => self::REQ, 'extra-dump' => self::REQ, 'format' => 'null']): PropertyList
    {
        $sql = SqlBase::create($options);
        $return = $sql->dump();
        if ($return === false) {
            throw new \Exception('Unable to dump database. Rerun with --debug to see any error message.');
        }

        // SqlBase::dump() returns null if 'result-file' option is empty.
        if ($return) {
            $this->logger()->success(dt('Database dump saved to !path', ['!path' => $return]));
        }
        return new PropertyList(['path' => $return]);
    }

    /**
     * Assert that `mysql` or similar are on the user's PATH.
     *
     * @hook validate
     * @param CommandData $commandData
     * @return bool
     * @throws \Exception
     */
    public function validate(CommandData $commandData)
    {
        if (in_array($commandData->annotationData()->get('command'), ['sql:connect', 'sql:conf'])) {
            // These commands don't require a program.
            return;
        }

        $sql = SqlBase::create($commandData->options());
        $program = $sql->command();

        if (!$this->programExists($program)) {
            $this->logger->warning(dt('The shell command \'!command\' is required but cannot be found. Please install it and retry.', ['!command' => $program]));
            return false;
        }
    }
}
