<?php

declare(strict_types=1);

namespace Drush\Doctor\Commands\sql;

use Drupal\Component\Serialization\Yaml;
use Drush\Commands\OutputFormat;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runs under dr only. Drush keeps its own sql:query.
 *
 * An inline query runs through core's Connection, so {table} braces are
 * prefixed and no client binary is needed. A --file runs through the
 * client, since Drupal's drivers execute one statement at a time and a
 * dump holds many.
 *
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Execute a query against a database.',
    usages: [
        'sql:query "SELECT * FROM {users} WHERE uid = 1"',
        'sql:query "SELECT uid, name FROM {users}" --format=json',
        'sql:query --file=example.sql',
        '$(dr sql:connect) < example.sql',
        'dr php:eval --format=json "return \\Drupal::service(\'database\')->query(\'SELECT * FROM users LIMIT 5\')->fetchAll()"',
    ],
    help: 'Table names in {braces} are prefixed with the connection\'s prefix. Rows print tab-separated without a header, as the database clients do in silent mode; --format=json or yaml prints them as records.',
)]
final class SqlQueryCommand
{
    use SqlTrait;

    public const string NAME = 'sql:query';

    public function __invoke(
        OutputInterface $output,
        #[Argument('An SQL query. Ignored if --file is provided.')]
        ?string $query = null,
        #[Option('Path to a file containing the SQL to be run. Gzip files are accepted.')]
        ?string $file = null,
        #[Option('Delete the --file after running it.', name: 'file-delete')]
        bool $fileDelete = false,
        #[Option('Save the result to a file instead of printing it.', name: 'result-file')]
        ?string $resultFile = null,
        #[Option('Add custom options to the connect string when running a --file (e.g. --extra=--skip-column-names).')]
        ?string $extra = null,
        #[Option('Print the rows of an inline query as json or yaml.')]
        ?OutputFormat $format = null,
        #[Option('The DB connection key if using multiple connections in settings.php.')]
        string $database = 'default',
        #[Option('The name of a target within the specified database connection.')]
        string $target = 'default',
        #[Option('A database URL such as mysql://root:pass@localhost:3306/dbname, used instead of the site\'s settings.', name: 'db-url')]
        ?string $dbUrl = null,
    ): int {
        if ($file !== null && $file !== '') {
            return $this->runFile($file, $fileDelete, $resultFile, $extra, $this->connectionInfo($database, $target, $dbUrl), $output);
        }
        if ($query === null || trim($query) === '') {
            throw new \InvalidArgumentException('Pass a query, or --file.');
        }

        $connection = $this->connection($database, $target, $dbUrl);
        $rows = $connection->query($query)->fetchAll(\PDO::FETCH_ASSOC);
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

    /**
     * Feeds a file of SQL to the client, as `client < file` does.
     */
    protected function runFile(string $file, bool $delete, ?string $resultFile, ?string $extra, array $info, OutputInterface $output): int
    {
        if (!is_file($file)) {
            throw new \InvalidArgumentException("The file $file does not exist.");
        }
        $client = $this->client($info);
        $this->requireProgram($client->program());

        $command = $client->connectString($extra);
        $command = str_ends_with($file, '.gz')
            ? 'gzip -dc ' . escapeshellarg($file) . ' | ' . $command
            : $command . ' < ' . escapeshellarg($file);
        if ($resultFile !== null && $resultFile !== '') {
            $command .= ' > ' . escapeshellarg($resultFile);
        }

        $process = $this->runShell($command, $client->environment(), $output);
        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Query failed: ' . trim($process->getErrorOutput()));
        }
        if ($delete) {
            unlink($file);
        }
        return Command::SUCCESS;
    }
}
