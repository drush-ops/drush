<?php

declare(strict_types=1);

namespace Drush\Commands\sql;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\AnnotatedCommand\Input\StdinAwareInterface;
use Consolidation\AnnotatedCommand\Input\StdinAwareTrait;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Consolidation\SiteProcess\Util\Tty;
use Drupal\Core\Database\Database;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\core\DocsCommands;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Drush\Exec\ExecTrait;
use Drush\Sql\SqlBase;
use Symfony\Component\Console\Input\InputInterface;

final class SqlCommands extends DrushCommands implements StdinAwareInterface
{
    use ExecTrait;
    use StdinAwareTrait;

    const CONF = 'sql:conf';
    const CONNECT = 'sql:connect';
    const CREATE = 'sql:create';
    const DROP = 'sql:drop';
    const CLI = 'sql:cli';
    const QUERY = 'sql:query';
    const DUMP = 'sql:dump';

    #[CLI\Command(name: self::CONF, aliases: ['sql-conf'])]
    #[CLI\Help(hidden: true)]
    #[CLI\Option(name: 'all', description: 'Show all database connections, instead of just one.')]
    #[CLI\Bootstrap(level: DrupalBootLevels::MAX, max_level: DrupalBootLevels::CONFIGURATION)]
    #[CLI\OptionsetSql]
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
     */
    #[CLI\Command(name: self::CONNECT, aliases: ['sql-connect'])]
    #[CLI\Option(name: 'extra', description: 'Add custom options to the connect string (e.g. --extra=--skip-column-names)')]
    #[CLI\OptionsetSql]
    #[CLI\Bootstrap(level: DrupalBootLevels::CONFIGURATION)]
    #[CLI\Usage(name: '$(drush sql:connect) < example.sql', description: 'Bash: Import SQL statements from a file into the current database.')]
    #[CLI\Usage(name: 'eval (drush sql:connect) < example.sql', description: 'Fish: Import SQL statements from a file into the current database.')]
    public function connect($options = ['extra' => self::REQ]): string
    {
        $sql = SqlBase::create($options);
        return $sql->connect(false);
    }

    /**
     * Create a database.
     */
    #[CLI\Command(name: self::CREATE, aliases: ['sql-create'])]
    #[CLI\Option(name: 'db-su', description: 'Account to use when creating a new database.')]
    #[CLI\Option(name: 'db-su-pw', description: 'Password for the db-su account.')]
    #[CLI\Usage(name: 'drush sql:create', description: 'Create the database for the current site.')]
    #[CLI\Usage(name: 'drush @site.test sql:create', description: 'Create the database as specified for @site.test.')]
    #[CLI\Usage(name: 'drush sql:create --db-su=root --db-su-pw=rootpassword --db-url="mysql://drupal_db_user:drupal_db_password@127.0.0.1/drupal_db"', description: 'Create the database as specified in the db-url option.')]
    #[CLI\Bootstrap(level: DrupalBootLevels::MAX, max_level: DrupalBootLevels::CONFIGURATION)]
    #[CLI\OptionsetSql]
    public function createDb($options = ['db-su' => self::REQ, 'db-su-pw' => self::REQ]): void
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
     */
    #[CLI\Command(name: self::DROP, aliases: ['sql-drop'])]
    #[CLI\Bootstrap(level: DrupalBootLevels::MAX, max_level: DrupalBootLevels::CONFIGURATION)]
    #[CLI\OptionsetSql]
    #[CLI\Topics(topics: [DocsCommands::POLICY])]
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
     */
    #[CLI\Command(name: self::CLI, aliases: ['sqlc', 'sql-cli'])]
    #[CLI\Option(name: 'extra', description: 'Add custom options to the connect string (e.g. --extra=--skip-column-names)')]
    #[CLI\Bootstrap(level: DrupalBootLevels::MAX, max_level: DrupalBootLevels::CONFIGURATION)]
    #[CLI\OptionsetSql]
    #[CLI\Topics(topics: [DocsCommands::POLICY])]
    #[CLI\Usage(name: 'drush sql:cli', description: 'Open a SQL command-line interface using Drupal\'s credentials.')]
    #[CLI\Usage(name: 'drush sql:cli --extra=--progress-reports', description: 'Open a SQL CLI and skip reading table information.')]
    #[CLI\Usage(name: 'drush sql:cli < example.sql', description: 'Import sql statements from a file into the current database.')]
    public function cli(InputInterface $input, $options = ['extra' => self::REQ]): void
    {
        $sql = SqlBase::create($options);
        $process = $this->processManager()->shell($sql->connect(), null, $sql->getEnv());
        if (!Tty::isTtySupported()) {
            $process->setInput($this->stdin()->getStream());
        } else {
            $process->setTty((bool) $this->getConfig()->get('ssh.tty', $input->isInteractive()));
        }
        $process->mustRun($process->showRealtime());
    }

    /**
     * Execute a query against a database.
     */
    #[CLI\Command(name: self::QUERY, aliases: ['sqlq', 'sql-query'])]
    #[CLI\Argument(name: 'query', description: 'An SQL query. Ignored if --file is provided.')]
    #[CLI\Bootstrap(level: DrupalBootLevels::MAX, max_level: DrupalBootLevels::CONFIGURATION)]
    #[CLI\OptionsetSql]
    #[CLI\Option(name: 'result-file', description: 'Save to a file. The file should be relative to Drupal root.')]
    #[CLI\Option(name: 'file', description: 'Path to a file containing the SQL to be run. Gzip files are accepted.')]
    #[CLI\Option(name: 'file-delete', description: 'Delete the --file after running it.')]
    #[CLI\Option(name: 'extra', description: 'Add custom options to the connect string (e.g. --extra=--skip-column-names)')]
    #[CLI\Option(name: 'db-prefix', description: 'Enable replacement of braces in your query.')]
    #[CLI\Usage(name: 'drush sql:query "SELECT * FROM users WHERE uid=1"', description: 'Browse user record. Table prefixes, if used, must be added to table names by hand.')]
    #[CLI\Usage(name: 'drush sql:query --db-prefix "SELECT * FROM {users}"', description: 'Browse user record. Table prefixes are honored.  Caution: All curly-braces will be stripped.')]
    #[CLI\Usage(name: '$(drush sql:connect) < example.sql', description: 'Import sql statements from a file into the current database.')]
    #[CLI\Usage(name: 'drush sql:query --file=example.sql', description: 'Alternate way to import sql statements from a file.')]
    #[CLI\Usage(name: 'drush php:eval --format=json "return \Drupal::service(\'database\')->query(\'SELECT * FROM users LIMIT 5\')->fetchAll()"', description: 'Get data back in JSON format. See https://github.com/drush-ops/drush/issues/3071#issuecomment-347929777.')]
    #[CLI\Usage(name: '$(drush sql:connect) -e "SELECT * FROM users LIMIT 5;"', description: 'Results are formatted in a pretty table with borders and column headers.')]
    #[CLI\ValidateFileExists(argName: 'file')]
    public function query($query = '', $options = ['result-file' => null, 'file' => self::REQ, 'file-delete' => false, 'extra' => self::REQ, 'db-prefix' => false]): bool
    {
        $filename = $options['file'];
        // Enable prefix processing when db-prefix option is used.
        if ($options['db-prefix']) {
            Drush::bootstrapManager()->bootstrapMax(DrupalBootLevels::DATABASE);
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
     * --create-db is used by sql-sync, since including the DROP TABLE statements interferes with the import when the database is created.
     */
    #[CLI\Command(name: self::DUMP, aliases: ['sql-dump'])]
    #[CLI\Bootstrap(level: DrupalBootLevels::MAX, max_level: DrupalBootLevels::CONFIGURATION)]
    #[CLI\OptionsetSql]
    #[CLI\OptionsetTableSelection]
    #[CLI\Option(name: 'result-file', description: "Save to a file. The file should be relative to Drupal root. If --result-file is provided with the value 'auto', a date-based filename will be created under ~/drush-backups directory.")]
    #[CLI\Option(name: 'create-db', description: 'Omit DROP TABLE statements. Used by Postgres and Oracle only.')]
    #[CLI\Option(name: 'data-only', description: 'Dump data without statements to create any of the schema.')]
    #[CLI\Option(name: 'ordered-dump', description: 'Order by primary key and add line breaks for efficient diffs. Slows down the dump. Mysql only.')]
    #[CLI\Option(name: 'gzip', description: 'Compress the dump using the gzip program which must be in your <info>$PATH</info>.')]
    #[CLI\Option(name: 'extra', description: 'Add custom arguments/options when connecting to database (used internally to list tables).')]
    #[CLI\Option(name: 'extra-dump', description: 'Add custom arguments/options to the dumping of the database (e.g. <info>mysqldump</info> command).')]
    #[CLI\Usage(name: 'drush sql:dump --result-file=../18.sql', description: 'Save SQL dump to the directory above Drupal root.')]
    #[CLI\Usage(name: 'drush sql:dump --skip-tables-key=common', description: 'Skip standard tables. See [Drush configuration](../../using-drush-configuration)')]
    #[CLI\Usage(name: 'drush sql:dump --extra-dump=--no-data', description: 'Pass extra option to <info>mysqldump</info> command.')]
    #[CLI\FieldLabels(labels: ['path' => 'Path'])]
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
     */
    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR)]
    public function validate(CommandData $commandData)
    {
        if (in_array($commandData->annotationData()->get('command'), [self::CONNECT, self::CONF])) {
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
