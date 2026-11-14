<?php

declare(strict_types=1);

namespace Drush\Doctor\Commands\sql;

use Drupal\Component\Serialization\Yaml;
use Drush\Commands\OutputFormat;
use Drush\Doctor\Sql\DumpOptions;
use Drush\Doctor\Sql\TableSelection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Runs under dr only. Drush keeps its own sql:dump.
 *
 * Runs mysqldump, pg_dump or sqlite3 .dump. The table lists are given on
 * the command line (--skip-tables-list and friends); the Drush command's
 * --*-key options read Drush configuration, which dr does not have.
 *
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Export the Drupal DB as SQL using mysqldump or equivalent.',
    usages: [
        'sql:dump --result-file=../18.sql',
        'sql:dump --result-file=auto --gzip',
        'sql:dump --skip-tables-list=cache*,sessions,watchdog',
        'sql:dump --structure-tables-list=cache*,sessions --result-file=auto --format=json',
        'sql:dump --extra-dump=--no-data',
    ],
    help: 'Without --result-file the dump is written to standard output. With it, the path of the file is printed, as plain text or with --format as json or yaml, so that a caller can find the dump.',
)]
final class SqlDumpCommand
{
    use SqlTrait;

    public const string NAME = 'sql:dump';

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option('Save to a file. The file should be relative to Drupal root. If --result-file is provided with the value \'auto\', a date-based filename will be created under ~/drush-backups.', name: 'result-file')]
        ?string $resultFile = null,
        #[Option('Omit DROP TABLE statements. Used by Postgres only, when importing into a freshly created database.', name: 'create-db')]
        bool $createDb = false,
        #[Option('Dump data without statements to create any of the schema.', name: 'data-only')]
        bool $dataOnly = false,
        #[Option('Order by primary key and add line breaks for efficient diffs. Slows down the dump. Mysql only.', name: 'ordered-dump')]
        bool $orderedDump = false,
        #[Option('Compress the dump using the gzip program which must be in your $PATH.')]
        bool $gzip = false,
        #[Option('Add custom arguments/options to the dumping of the database (e.g. mysqldump command).', name: 'extra-dump')]
        ?string $extraDump = null,
        #[Option('A comma-separated list of tables to exclude completely. Wildcards (*) are allowed.', name: 'skip-tables-list')]
        ?string $skipTablesList = null,
        #[Option('A comma-separated list of tables to include for structure, but not data. Wildcards (*) are allowed.', name: 'structure-tables-list')]
        ?string $structureTablesList = null,
        #[Option('A comma-separated list of tables to transfer. Wildcards (*) are allowed.', name: 'tables-list')]
        ?string $tablesList = null,
        #[Option('Print the path of the dump file as json or yaml.')]
        ?OutputFormat $format = null,
        #[Option('The DB connection key if using multiple connections in settings.php.')]
        string $database = 'default',
        #[Option('The name of a target within the specified database connection.')]
        string $target = 'default',
        #[Option('A database URL such as mysql://root:pass@localhost:3306/dbname, used instead of the site\'s settings.', name: 'db-url')]
        ?string $dbUrl = null,
    ): int {
        $connection = $this->connection($database, $target, $dbUrl);
        $client = $this->client($connection->getConnectionOptions());
        $this->requireProgram($client->dumpProgram());
        if ($gzip) {
            $this->requireProgram('gzip');
        }

        // The dump programs see the tables as the server names them, prefix
        // included, so the selection is expanded against those names.
        $prefix = $connection->getPrefix();
        $existing = array_map(fn (string $table) => $prefix . $table, $this->listTables($connection));
        $tables = TableSelection::fromOptions($skipTablesList, $structureTablesList, $tablesList, $existing);
        $options = new DumpOptions(dataOnly: $dataOnly, orderedDump: $orderedDump, createDb: $createDb, extraDump: $extraDump);

        $command = $client->dumpCommand($tables, $options);
        $file = $this->dumpFile($resultFile, $client->database());
        if ($gzip) {
            $command .= ' | gzip -f';
            if ($file !== null) {
                $file .= '.gz';
            }
        }
        if ($file !== null) {
            $command .= ' > ' . escapeshellarg($file);
        }
        if ($gzip && (new ExecutableFinder())->find('bash') !== null) {
            // Without pipefail a failed dump piped into gzip exits 0.
            $command = 'bash -c ' . escapeshellarg('set -o pipefail; ' . $command);
        }

        $process = $this->runShell($command, $client->environment(), $output);
        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Unable to dump database. ' . trim($process->getErrorOutput()));
        }

        if ($file !== null) {
            (new SymfonyStyle($input, $output))->getErrorStyle()->success(sprintf('Database dump saved to %s', $file));
            $result = match ($format) {
                OutputFormat::Json => json_encode(['path' => $file], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
                OutputFormat::Yaml => rtrim(Yaml::encode(['path' => $file]), "\n"),
                default => $file,
            };
            $output->writeln($result, OutputInterface::OUTPUT_RAW);
        }
        return Command::SUCCESS;
    }

    /**
     * The path to write the dump to, or null for standard output.
     *
     * 'auto' picks a dated file under ~/drush-backups/<database>/, or the
     * system temp dir when the home directory is not writable. @DATABASE and
     * @DATE in a given path are replaced.
     */
    protected function dumpFile(?string $resultFile, string $database): ?string
    {
        if ($resultFile === null || $resultFile === '') {
            return null;
        }
        // basename() because a sqlite database is a path.
        $database = basename($database);
        if ($resultFile === 'auto') {
            $resultFile = $this->backupDirectory($database) . '/@DATABASE_@DATE.sql';
        }
        return str_replace(['@DATABASE', '@DATE'], [$database, gmdate('Ymd_His')], $resultFile);
    }

    protected function backupDirectory(string $database): string
    {
        $home = getenv('HOME') ?: '';
        $candidates = [
            $home !== '' ? "$home/drush-backups/$database" : null,
            sys_get_temp_dir() . "/drush-backups/$database",
        ];
        foreach (array_filter($candidates) as $dir) {
            if (is_dir($dir) ? is_writable($dir) : @mkdir($dir, 0700, true)) {
                return $dir;
            }
        }
        throw new \RuntimeException('No writable backup directory found; pass --result-file with a path.');
    }
}
