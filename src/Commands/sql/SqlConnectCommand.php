<?php

declare(strict_types=1);

namespace Drush\Commands\sql;

use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Sql\SqlBase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: "Print a string for connecting to the database.",
    aliases: ['sql-connect'],
)]
#[CLI\OptionsetSql]
#[CLI\Bootstrap(level: DrupalBootLevels::CONFIGURATION)]
final class SqlConnectCommand extends Command
{
    public const NAME = 'sql:connect';

    protected function configure(): void
    {
        $this
            ->addOption(
                name: 'extra',
                mode: InputOption::VALUE_REQUIRED,
                description: 'Add custom options to the connect string (e.g. --extra=--skip-column-names)'
            )
            ->addUsage('$(drush sql:connect) < example.sql')
            ->addUsage('eval (drush sql:connect) < example.sql');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $sql = SqlBase::create($input->getOptions());
        $connect = $sql->connect(false);
        $output->writeln($connect);
        return Command::SUCCESS;
    }
}
