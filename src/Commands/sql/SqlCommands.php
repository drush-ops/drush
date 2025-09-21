<?php

declare(strict_types=1);

namespace Drush\Commands\sql;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\AnnotatedCommand\Input\StdinAwareInterface;
use Consolidation\AnnotatedCommand\Input\StdinAwareTrait;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\core\DocsCommands;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Drush\Exec\ExecTrait;
use Drush\Sql\SqlBase;
use JetBrains\PhpStorm\Deprecated;

final class SqlCommands extends DrushCommands implements StdinAwareInterface
{
    use ExecTrait;
    use StdinAwareTrait;

    #[Deprecated(reason: 'Moved', replacement: SqlConfCommand::NAME)]
    const CONF = 'sql:conf';
    #[Deprecated(reason: 'Moved', replacement: SqlConnectCommand::NAME)]
    const CONNECT = 'sql:connect';
    #[Deprecated(reason: 'Moved', replacement: SqlCreateCommand::NAME)]
    const CREATE = 'sql:create';
    const DROP = 'sql:drop';
    #[Deprecated(reason: 'Moved', replacement: SqlCliCommand::NAME)]
    const CLI = 'sql:cli';
    const QUERY = 'sql:query';
    #[Deprecated(reason: 'Moved', replacement: SqlDumpCommand::NAME)]
    const DUMP = 'sql:dump';

    /**
     * Drop all tables in a given database.
     */
    #[CLI\Command(name: self::DROP, aliases: ['sql-drop'])]
    #[CLI\Bootstrap(level: DrupalBootLevels::MAX, max_level: DrupalBootLevels::CONFIGURATION)]
    #[CLI\Option(name: 'extra', description: 'Add custom options to the connect string (e.g. --extra=--skip-column-names)')]
    #[CLI\OptionsetSql]
    #[CLI\Topics(topics: [DocsCommands::POLICY])]
    public function drop($options = ['extra' => self::REQ]): void
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
     * To import an SQL dump, it is more efficient to use sql:connect than sql:cli. See the Examples below.
     */

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
