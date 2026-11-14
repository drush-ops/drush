<?php

declare(strict_types=1);

namespace Drush\Doctor\Commands\sql;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Runs under dr only. Drush keeps its own sql:cli.
 *
 * @internal
 */
#[AsCommand(
    name: self::NAME,
    description: 'Open a SQL command-line interface using Drupal\'s credentials.',
    usages: [
        'sql:cli',
        'sql:cli --extra=-A',
    ],
    help: 'To import an SQL dump, it is more efficient to use sql:connect than sql:cli: $(dr sql:connect) < example.sql',
)]
final class SqlCliCommand
{
    use SqlTrait;

    public const string NAME = 'sql:cli';

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option('Add custom options to the connect string (e.g. --extra=--skip-column-names)')]
        ?string $extra = null,
        #[Option('The DB connection key if using multiple connections in settings.php.')]
        string $database = 'default',
        #[Option('The name of a target within the specified database connection.')]
        string $target = 'default',
        #[Option('A database URL such as mysql://root:pass@localhost:3306/dbname, used instead of the site\'s settings.', name: 'db-url')]
        ?string $dbUrl = null,
    ): int {
        if ($this->hasPipedInput()) {
            throw new \RuntimeException('Instead of piping SQL to sql:cli, use sql:connect, which is faster: $(dr sql:connect) < example.sql');
        }

        $client = $this->client($this->connectionInfo($database, $target, $dbUrl));
        $this->requireProgram($client->program());

        $process = Process::fromShellCommandline($client->connectString($extra), null, $client->environment(), null, null);
        if (Process::isTtySupported()) {
            $process->setTty($input->isInteractive());
        }
        $process->mustRun(function (string $type, string $buffer) use ($output): void {
            $output->write($buffer, false, OutputInterface::OUTPUT_RAW);
        });
        return Command::SUCCESS;
    }

    /**
     * Whether input is waiting on STDIN, which means SQL was piped in.
     */
    protected function hasPipedInput(): bool
    {
        if (!defined('STDIN')) {
            return false;
        }
        $read = [STDIN];
        $write = [];
        $except = [];
        return (bool) @stream_select($read, $write, $except, 0);
    }
}
