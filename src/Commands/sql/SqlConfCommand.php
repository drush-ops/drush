<?php

declare(strict_types=1);

namespace Drush\Commands\sql;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drupal\Core\Database\Database;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Drush\Sql\SqlBase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Print database connection details.',
    aliases: ['sql-conf'],
    hidden: true,
)]
#[CLI\Bootstrap(level: DrupalBootLevels::MAX, max_level: DrupalBootLevels::CONFIGURATION)]
#[CLI\OptionsetSql]
#[CLI\Formatter(returnType: PropertyList::class, defaultFormatter: 'yaml')]
final class SqlConfCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    public const NAME = 'sql:conf';

    public function __construct(
        protected readonly FormatterManager $formatterManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('all', null, InputOption::VALUE_NONE, 'Show all database connections, instead of just one.')
            ->addOption('show-passwords', null, InputOption::VALUE_NONE, 'Include passwords in output.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input);
        $this->writeFormattedOutput($input, $output, $data);
        return Command::SUCCESS;
    }

    public function doExecute(InputInterface $input): PropertyList
    {
        $showAll = (bool) $input->getOption('all');
        $showPasswords = (bool) $input->getOption('show-passwords');

        if ($showAll) {
            $return = Database::getAllConnectionInfo();
            foreach ($return as $key1 => $value) {
                foreach ($value as $key2 => $spec) {
                    if (!$showPasswords) {
                        unset($return[$key1][$key2]['password']);
                    }
                }
            }
            return new PropertyList($return);
        }

        // Include OptionsetSql-provided options when building SqlBase.
        $sql = SqlBase::create($input->getOptions());
        $return = $sql->getDbSpec();
        if (!$showPasswords) {
            unset($return['password']);
        }
        return new PropertyList($return);
    }
}
