<?php

declare(strict_types=1);

namespace Drush\Doctor\Commands\sql;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Database\Statement\FetchAs;
use Drush\Commands\OutputFormat;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runs under dr only. Drush keeps its own sql:query.
 *
 * The query runs through core's Connection, so {table} braces are prefixed
 * and no client binary is needed. To run a file of SQL, feed it to the
 * client: $(dr sql:connect) < file.sql.
 *
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Execute a query against a database.',
    aliases: ['sqlq', 'sql-query'],
    usages: [
        'sql:query "SELECT * FROM {users} WHERE uid = 1"',
        'sql:query "SELECT uid, name FROM {users}" --format=json',
        '$(dr sql:connect) < example.sql',
        'dr php:eval --format=json "return \\Drupal::service(\'database\')->query(\'SELECT * FROM users LIMIT 5\')->fetchAll()"',
    ],
    help: 'Table names in {braces} are prefixed with the connection\'s prefix. Rows print tab-separated without a header, as the database clients do in silent mode; --format=json or yaml prints them as records. The query is one statement; to run a file of SQL, use $(dr sql:connect) < example.sql.',
)]
final class SqlQueryCommand
{
    use SqlTrait;

    public const string NAME = 'sql:query';

    public function __invoke(
        OutputInterface $output,
        #[Argument('An SQL statement.')]
        string $query,
        #[Option('Save the result to a file instead of printing it.', name: 'result-file')]
        ?string $resultFile = null,
        #[Option('Print the rows as json or yaml.')]
        ?OutputFormat $format = null,
        #[Option('The DB connection key if using multiple connections in settings.php.')]
        string $database = 'default',
        #[Option('The name of a target within the specified database connection.')]
        string $target = 'default',
        #[Option('A database URL such as mysql://root:pass@localhost:3306/dbname, used instead of the site\'s settings.', name: 'db-url')]
        ?string $dbUrl = null,
    ): int {
        if (trim($query) === '') {
            throw new \InvalidArgumentException('The query is empty.');
        }

        $connection = $this->connection($database, $target, $dbUrl);
        $rows = $connection->query($query)->fetchAll(FetchAs::Associative);
        $result = match ($format) {
            OutputFormat::Json => json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            OutputFormat::Yaml => rtrim(Yaml::encode($rows), "\n"),
            default => implode("\n", array_map(fn (array $row) => implode("\t", array_map(fn ($v) => (string) $v, $row)), $rows)),
        };

        if ($resultFile !== null && $resultFile !== '') {
            file_put_contents($resultFile, $result === '' ? '' : $result . "\n");
        } elseif ($result !== '') {
            $output->writeln($result, OutputInterface::OUTPUT_RAW);
        }
        return Command::SUCCESS;
    }
}
