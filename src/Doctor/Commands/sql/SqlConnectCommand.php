<?php

declare(strict_types=1);

namespace Drush\Doctor\Commands\sql;

use Drupal\Component\Serialization\Yaml;
use Drush\Commands\OutputFormat;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runs under dr only. Drush keeps its own sql:connect.
 *
 * The command reads nothing but core's Database registry, so it needs no
 * services and works as soon as settings.php declares a connection.
 *
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Print a string for connecting to the database.',
    aliases: ['sql-connect'],
    usages: [
        '$(dr sql:connect) < example.sql',
        'eval (dr sql:connect) < example.sql',
        'sql:connect --extra=--skip-column-names',
        'sql:connect --db-url=mysql://root:pass@127.0.0.1/drupal',
        'sql:connect --format=json',
        '$(dr sql:connect) -e "SELECT * FROM users LIMIT 5;"',
        'dr php:eval --format=json "return \\Drupal::service(\'database\')->query(\'SELECT * FROM users LIMIT 5\')->fetchAll()"',
    ],
    help: 'For PostgreSQL the password is not part of the string; psql reads it from PGPASSFILE or ~/.pgpass. With --format the connection info is printed as structured data instead, as sql:conf does.',
)]
final class SqlConnectCommand
{
    use SqlTrait;

    public const string NAME = 'sql:connect';

    public function __invoke(
        OutputInterface $output,
        #[Option('Add custom options to the connect string (e.g. --extra=--skip-column-names)')]
        ?string $extra = null,
        #[Option('The DB connection key if using multiple connections in settings.php.')]
        string $database = 'default',
        #[Option('The name of a target within the specified database connection.')]
        string $target = 'default',
        #[Option('A database URL such as mysql://root:pass@localhost:3306/dbname, used instead of the site\'s settings.', name: 'db-url')]
        ?string $dbUrl = null,
        #[Option('Print the connection info as json or yaml instead of a connect string.')]
        ?OutputFormat $format = null,
    ): int {
        $info = $this->connectionInfo($database, $target, $dbUrl);
        $result = match ($format) {
            OutputFormat::Json => json_encode($info, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            OutputFormat::Yaml => rtrim(Yaml::encode($info), "\n"),
            default => $this->connectString($info, $extra),
        };
        $output->writeln($result, OutputInterface::OUTPUT_RAW);
        return Command::SUCCESS;
    }
}
