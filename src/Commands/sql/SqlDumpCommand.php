<?php

declare(strict_types=1);

namespace Drush\Commands\sql;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Drush\Attributes as CLI;
use Drush\Boot\BootstrapManager;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Drush\Sql\SqlBase;
use Drush\Style\DrushStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Export the Drupal DB as SQL using mysqldump or equivalent.',
    usages: ['sql:dump --result-file=../18.sql', 'sql:dump --skip-tables-key=common', 'sql:dump --extra-dump=--no-data'],
    // @todo alias causes problem with command name https://github.com/symfony/symfony/pull/61367
    // aliases: ['sql-dump']
)]
#[CLI\FieldLabels(labels: ['path' => 'Path'])]
#[CLI\Formatter(returnType: PropertyList::class, defaultFormatter: 'null')]
#[CLI\OptionsetSql]
#[CLI\OptionsetTableSelection]
#[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
final class SqlDumpCommand
{
    use AutowireTrait;
    use FormatterTrait;

    public const NAME = 'sql:dump';

    public function __construct(
        protected BootstrapManager $bootstrapManager,
        protected readonly FormatterManager $formatterManager,
    ) {}

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(name: 'result-file', description: 'Save to a file. The file should be relative to Drupal root. If --result-file is provided with the value \'auto\', a date-based filename will be created under ~/drush-backups directory.')]
        ?string $resultFile = null,
        // create-db is used by sql:sync, since including the DROP TABLE statements interferes with the import when the database is created.
        #[Option(name: 'create-db', description: 'Omit DROP TABLE statements. Used by Postgres and Oracle only.')]
        bool $createDb = false,
        #[Option(name: 'data-only', description: 'Dump data without statements to create any of the schema.')]
        bool $dataOnly = false,
        #[Option(name: 'ordered-dump', description: 'Order by primary key and add line breaks for efficient diffs. Slows down the dump. Mysql only.')]
        bool $orderedDump = false,
        #[Option(name: 'gzip', description: 'Compress the dump using the gzip program which must be in your <info>$PATH</info>.')]
        bool $gzip = false,
        #[Option(name: 'extra', description: 'Add custom arguments/options when connecting to database (used internally to list tables).')]
        ?string $extra = null,
        #[Option(name: 'extra-dump', description: 'Add custom arguments/options to the dumping of the database (e.g. <info>mysqldump</info> command).')]
        ?string $extraDump = null,
    ): int
    {
        $this->bootstrapManager->bootstrapMax(DrupalBootLevels::CONFIGURATION);
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
            $io = new DrushStyle($input, $output);
            $io->success(dt('Database dump saved to !path', ['!path' => $return]));
        }
        return new PropertyList(['path' => $return]);
    }
}
