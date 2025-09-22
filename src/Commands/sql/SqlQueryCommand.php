<?php

declare(strict_types=1);

namespace Drush\Commands\sql;

use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Drush;
use Drush\Exec\ExecTrait;
use Drush\Sql\SqlBase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Execute a query against a database.',
    aliases: ['sqlq', 'sql-query'],
)]
#[CLI\Bootstrap(level: DrupalBootLevels::MAX, max_level: DrupalBootLevels::CONFIGURATION)]
#[CLI\OptionsetSql]
final class SqlQueryCommand extends Command
{
    use ExecTrait;

    public const NAME = 'sql:query';

    protected function configure(): void
    {
        $this
            ->addArgument('query', InputArgument::OPTIONAL, 'An SQL query. Ignored if --file is provided.')
            ->addOption(name: 'result-file', mode: InputOption::VALUE_OPTIONAL, description: 'Save to a file. The file should be relative to Drupal root.')
            ->addOption(name: 'file', mode: InputOption::VALUE_REQUIRED, description: 'Path to a file containing the SQL to be run. Gzip files are accepted.')
            ->addOption(name: 'file-delete', mode: InputOption::VALUE_NONE, description: 'Delete the --file after running it.')
            ->addOption(name: 'extra', mode: InputOption::VALUE_REQUIRED, description: 'Add custom options to the connect string (e.g. --extra=--skip-column-names)')
            ->addOption(name: 'db-prefix', mode: InputOption::VALUE_NONE, description: 'Enable replacement of braces in your query.')
            ->addUsage('drush sql:query "SELECT * FROM users WHERE uid=1"')
            ->addUsage('drush sql:query --db-prefix "SELECT * FROM {users}"')
            ->addUsage('$(drush sql:connect) < example.sql')
            ->addUsage('drush sql:query --file=example.sql')
            ->addUsage('drush php:eval --format=json "return \\Drupal::service(\'database\')->query(\'SELECT * FROM users LIMIT 5\')->fetchAll()"')
            ->addUsage('$(drush sql:connect) -e "SELECT * FROM users LIMIT 5;"');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $query = (string) ($input->getArgument('query') ?? '');
        $filename = $input->getOption('file');
        $resultFile = $input->getOption('result-file');
        $dbPrefix = (bool) $input->getOption('db-prefix');

        if ($dbPrefix) {
            // Enable prefix processing when db-prefix option is used.
            Drush::bootstrapManager()->bootstrapMax(DrupalBootLevels::DATABASE);
        }

        // Simulate behavior
        if (Drush::config()->simulate()) {
            if ($query) {
                $output->writeln(dt('Simulating sql:query: !q', ['!q' => $query]));
            } else {
                $output->writeln(dt('Simulating sql:query from file !f', ['!f' => $filename]));
            }
            return Command::SUCCESS;
        }

        $sql = SqlBase::create($input->getOptions());
        $program = $sql->command();
        if (!self::programExists($program)) {
            $msg = dt('The shell command \'!command\' is required but cannot be found. Please install it and retry.', ['!command' => $program]);
            throw new \RuntimeException($msg);
        }

        $result = $sql->query($query, $filename, $resultFile);
        if (!$result) {
            throw new \Exception('Query failed. Rerun with --debug to see any error message. ' . $sql->getProcess()->getErrorOutput());
        }
        $output->writeln($sql->getProcess()->getOutput());
        return Command::SUCCESS;
    }
}
