<?php

declare(strict_types=1);

namespace Drush\Commands\sql;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Drush\Sql\SqlBase;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Export the Drupal DB as SQL using mysqldump or equivalent.',
    aliases: ['sql-dump'],
)]
#[CLI\FieldLabels(labels: ['path' => 'Path'])]
#[CLI\Formatter(returnType: PropertyList::class, defaultFormatter: 'null')]
#[CLI\OptionsetSql]
#[CLI\OptionsetTableSelection]
#[CLI\Bootstrap(level: DrupalBootLevels::CONFIGURATION)]
#[CLI\HelpLinks(links: [HelpLinks::Aliases, HelpLinks::DrushConfiguration, HelpLinks::Policy])]
final class SqlDumpCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    public const NAME = 'sql:dump';

    public function __construct(
        protected BootstrapManager $bootstrapManager,
        protected readonly FormatterManager $formatterManager,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        $this
            ->addOption(name: 'result-file', mode: InputOption::VALUE_OPTIONAL, description: 'Save to a file. The file should be relative to Drupal root. If --result-file is provided with the value \'auto\', a date-based filename will be created under ~/drush-backups directory.')
            // create-db is used by sql:sync, since including the DROP TABLE statements interferes with the import when the database is created.
            ->addOption(name: 'create-db', description: 'Omit DROP TABLE statements. Used by Postgres and Oracle only.')
            ->addOption(name: 'data-only', description: 'Dump data without statements to create any of the schema.')
            ->addOption(name: 'ordered-dump', description: 'Order by primary key and add line breaks for efficient diffs. Slows down the dump. Mysql only.')
            ->addOption(name: 'gzip', description: 'Compress the dump using the gzip program which must be in your <info>$PATH</info>.')
            ->addOption(name: 'extra', mode: InputOption::VALUE_REQUIRED, description: 'Add custom arguments/options when connecting to database (used internally to list tables).')
            ->addOption(name: 'extra-dump', mode: InputOption::VALUE_REQUIRED, description: 'Add custom arguments/options to the dumping of the database (e.g. <info>mysqldump</info> command).')
            ->addUsage('sql:dump --result-file=../18.sql')
            ->addUsage('sql:dump --skip-tables-key=common')
            ->addUsage('sql:dump --extra-dump=--no-data');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return Command::SUCCESS;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): PropertyList
    {
        $sql = SqlBase::create($input->getOptions());
        $return = $sql->dump();
        if ($return === false) {
            throw new \Exception('Unable to dump database. Rerun with --debug to see any error message.');
        }

        // SqlBase::dump() returns null if 'result-file' option is empty.
        if ($return) {
            (new DrushStyle($input, $output))->success(sprintf('Database dump saved to %s', $return));
        }
        return new PropertyList(['path' => $return]);
    }
}
