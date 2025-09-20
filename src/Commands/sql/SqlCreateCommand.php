<?php

declare(strict_types=1);

namespace Drush\Commands\sql;

use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Config\DrushConfig;
use Drush\Exceptions\UserAbortException;
use Drush\Sql\SqlBase;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Create a database.',
    aliases: ['sql-create'],
)]
#[CLI\Bootstrap(level: DrupalBootLevels::MAX, max_level: DrupalBootLevels::CONFIGURATION)]
#[CLI\OptionsetSql]
final class SqlCreateCommand extends Command
{
    use AutowireTrait;

    public const NAME = 'sql:create';

    public function __construct(
        protected DrushConfig $drushConfig,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(name: 'db-su', mode: InputOption::VALUE_REQUIRED, description: 'Account to use when creating a new database.')
            ->addOption(name: 'db-su-pw', mode: InputOption::VALUE_REQUIRED, description: 'Password for the db-su account.')
            ->addOption(name: 'extra', mode: InputOption::VALUE_REQUIRED, description: 'Add custom options to the connect string (e.g. --extra=--skip-column-names)')
            ->addUsage('drush @site.test sql:create')
            ->addUsage('drush sql:create --db-su=root --db-su-pw=rootpassword --db-url="mysql://drupal_db_user:drupal_db_password@127.0.0.1/drupal_db"');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new DrushStyle($input, $output);
        $sql = SqlBase::create($input->getOptions());
        $db_spec = $sql->getDbSpec();

        $io->writeln(dt('Creating database !target. Any existing database will be dropped!', ['!target' => $db_spec['database']]));
        if (!$this->drushConfig->simulate() && !$io->confirm(dt('Do you really want to continue?'))) {
            throw new UserAbortException();
        }

        if (!$sql->createdb(true)) {
            throw new \Exception('Unable to create database. Rerun with --debug to see any error message.  ' . $sql->getProcess()->getErrorOutput());
        }

        return Command::SUCCESS;
    }
}
