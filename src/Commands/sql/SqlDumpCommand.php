<?php

declare(strict_types=1);

namespace Drush\Commands\sql;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Commands\OptionSets;
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
    aliases: ['sql-dump']
)]
#[CLI\Bootstrap(level: DrupalBootLevels::MAX, max_level: DrupalBootLevels::CONFIGURATION)]
#[CLI\OptionsetTableSelection]
final class SqlDumpCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;

    public const NAME = 'sql:dump';

    public function __construct(
        protected readonly FormatterManager $formatterManager
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->addOption('result-file', null, InputOption::VALUE_REQUIRED, 'Save to a file. The file should be relative to Drupal root. If --result-file is provided with the value \'auto\', a date-based filename will be created under ~/drush-backups directory.')
            // create-db is used by sql:sync, since including the DROP TABLE statements interferes with the import when the database is created.
            ->addOption('create-db', null, InputOption::VALUE_NONE, 'Omit DROP TABLE statements. Used by Postgres and Oracle only.')
            ->addOption('data-only', null, InputOption::VALUE_NONE, 'Dump data without statements to create any of the schema.')
            ->addOption('ordered-dump', null, InputOption::VALUE_NONE, 'Order by primary key and add line breaks for efficient diffs. Slows down the dump. Mysql only.')
            ->addOption('gzip', null, InputOption::VALUE_NONE, 'Compress the dump using the gzip program which must be in your <info>$PATH</info>.')
            ->addOption('extra', null, InputOption::VALUE_REQUIRED, 'Add custom arguments/options when connecting to database (used internally to list tables).')
            ->addOption('extra-dump', null, InputOption::VALUE_REQUIRED, 'Add custom arguments/options to the dumping of the database (e.g. <info>mysqldump</info> command).')
            ->addUsage('sql:dump --result-file=../18.sql')
            ->addUsage('sql:dump --skip-tables-key=common')
            ->addUsage('sql:dump --extra-dump=--no-data');
        $this->configureFormatter(PropertyList::class);
        OptionSets::sql($this);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $formatterOptions = $this->getFormatterOptions()->setInput($input);
        $data = $this->doExecute($input, $output);
        $data = $this->alterResult($data, $input);
        $this->formatterManager->write($output, $input->getOption('format'), $data, $formatterOptions);
        return static::SUCCESS;
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
            $io = new DrushStyle($input, $output);
            $io->success(dt('Database dump saved to !path', ['!path' => $return]));
        }
        return new PropertyList(['path' => $return]);
    }

    protected function getFormatterOptions(): FormatterOptions
    {
        return (new FormatterOptions())
            ->setFieldLabels(['path' => 'Path']);
    }
}
