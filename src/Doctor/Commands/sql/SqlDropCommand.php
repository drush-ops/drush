<?php

declare(strict_types=1);

namespace Drush\Doctor\Commands\sql;

use Drupal\Core\Command\Exception\UserAbortException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Runs under dr only. Drush keeps its own sql:drop.
 *
 * Lists and drops the tables through core's Schema API; no client binary
 * is involved. With a table prefix, only that site's tables are dropped.
 *
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Drop all tables in a given database.',
    usages: [
        'sql:drop',
        'sql:drop --no-interaction --database=migrate',
    ],
)]
final class SqlDropCommand
{
    use SqlTrait;

    public const string NAME = 'sql:drop';

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option('The DB connection key if using multiple connections in settings.php.')]
        string $database = 'default',
        #[Option('The name of a target within the specified database connection.')]
        string $target = 'default',
        #[Option('A database URL such as mysql://root:pass@localhost:3306/dbname, used instead of the site\'s settings.', name: 'db-url')]
        ?string $dbUrl = null,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $connection = $this->connection($database, $target, $dbUrl);
        $client = $this->client($connection->getConnectionOptions());

        $tables = $this->listTables($connection);
        if (!$tables) {
            $io->getErrorStyle()->success('No tables to drop.');
            return Command::SUCCESS;
        }
        if (!$io->confirm(sprintf('Do you really want to drop all %d tables in the database %s?', count($tables), $client->database()))) {
            throw new UserAbortException();
        }

        $schema = $connection->schema();
        foreach ($tables as $table) {
            $schema->dropTable($table);
        }
        $io->getErrorStyle()->success(sprintf('Dropped %d tables.', count($tables)));
        return Command::SUCCESS;
    }
}
