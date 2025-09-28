<?php

declare(strict_types=1);

namespace Drush\Commands\sql;

use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Command\HelpLinks;
use Drush\Exceptions\UserAbortException;
use Drush\Exec\ExecTrait;
use Drush\Sql\SqlBase;
use Drush\Style\DrushStyle;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Drop all tables in a given database.',
    aliases: ['sql-drop'],
)]
#[CLI\Bootstrap(level: DrupalBootLevels::MAX, max_level: DrupalBootLevels::CONFIGURATION)]
#[CLI\OptionsetSql]
#[CLI\HelpLinks(links: [HelpLinks::Policy])]
final class SqlDropCommand extends Command
{
    use ExecTrait;

    const NAME = 'sql:drop';

    protected function configure()
    {
        $this
            ->addOption(name: 'extra', mode: InputOption::VALUE_REQUIRED, description: 'Add custom options to the connect string (e.g. --extra=--skip-column-names)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sql = SqlBase::create($input->getOptions());
        $program = $sql->command();
        if (!self::programExists($program)) {
            $msg = dt('The shell command \'!command\' is required but cannot be found. Please install it and retry.', ['!command' => $program]);
            throw new RuntimeException($msg);
        }

        $db_spec = $sql->getDbSpec();
        if (!(new DrushStyle($input, $output))->confirm(dt('Do you really want to drop all tables in the database !db?', ['!db' => $db_spec['database']]))) {
            throw new UserAbortException();
        }
        $tables = $sql->listTablesQuoted();
        if (!$sql->drop($tables)) {
            throw new \Exception('Unable to drop all tables: ' . $sql->getProcess()->getErrorOutput());
        }
        return Command::SUCCESS;
    }
}
