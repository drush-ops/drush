<?php

declare(strict_types=1);

namespace Drush\Commands\migrate;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drush\Attributes as CLI;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Formatters\FormatterTrait;
use Drush\Utils\StringUtils;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: self::NAME,
    description: 'List all migrations with current status.',
    aliases: ['ms', 'migrate-status'],
)]
#[CLI\HelpLinks(links: [HelpLinks::Migrate])]
#[CLI\ValidateModulesEnabled(modules: ['migrate'])]
#[CLI\FieldLabels(labels: [
    'id' => 'Migration ID',
    'status' => 'Status',
    'total' => 'Total',
    'imported' => 'Imported',
    'needing_update' => 'Needing update',
    'unprocessed' => 'Unprocessed',
    'last_imported' => 'Last Imported',
])]
#[CLI\DefaultFields(fields: ['id', 'status', 'total', 'imported', 'unprocessed', 'last_imported'])]
#[CLI\FilterDefaultField(field: 'status')]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
#[CLI\Version(version:  '10.4')]
final class MigrateStatusCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;
    use MigrateRunnerTrait;

    public const NAME = 'migrate:status';

    public function __construct(
        protected readonly DateFormatterInterface $dateFormatter,
        #[Autowire(service: 'keyvalue')]
        protected readonly KeyValueFactoryInterface $keyValueFactory,
        protected readonly FormatterManager $formatterManager,
        protected readonly LoggerInterface $logger,
        protected readonly ContainerInterface $container,
    ) {
        parent::__construct();
        $this->keyValue = $keyValueFactory->get('migrate_last_imported');

        // $container = Drush::getContainer();
        if ($container->has('plugin.manager.migration')) {
            $this->setMigrationPluginManager($container->get('plugin.manager.migration'));
        }
    }

    protected function configure(): void
    {
        $this
            ->addArgument('migrationIds', InputArgument::OPTIONAL, 'Restrict to a comma-separated list of migrations. Optional.')
            ->addOption('tag', null, InputOption::VALUE_REQUIRED, 'A comma-separated list of migration tags to list. If only <info>--tag</info> is provided, all tagged migrations will be listed, grouped by tags.')
            ->addUsage('migrate:status --tag')
            ->addUsage('migrate:status --tag=user,main_content')
            ->addUsage('migrate:status classification,article')
            ->addUsage('migrate:status --field=id')
            ->addUsage('ms --fields=id,status --format=json')
            ->setHelp('Retrieve status for all migrations. Use --tag to group by tags, or specify specific migration IDs.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return self::SUCCESS;
    }

    public function doExecute(InputInterface $input, OutputInterface $output): RowsOfFields
    {
        $migrationIds = $input->getArgument('migrationIds');
        $tag = $input->getOption('tag');
        $options = $input->getOptions();

        $fields = [];
        if ($options['field']) {
            $fields = [$options['field']];
        } elseif ($options['fields']) {
            $fields = StringUtils::csvToArray($options['fields']);
        }

        $list = $this->getMigrationList($migrationIds, $tag);

        $table = [];
        // Take it one tag at a time, listing the migrations within each tag.
        foreach ($list as $tag => $migrations) {
            if ($tag) {
                $table[] = $this->padTableRow([
                  'id' => dt('Tag: @name', ['@name' => $tag])
                ], $fields);
            }
            ksort($migrations);
            foreach ($migrations as $migration) {
                $row = [];
                foreach ($fields as $field) {
                    switch ($field) {
                        case 'id':
                            $row[$field] = ($tag ? ' ' : '') . $migration->id();
                            break;
                        case 'status':
                            $row[$field] = $migration->getStatusLabel();
                            break;
                        case 'total':
                            $sourceRowsCount = $this->getMigrationSourceRowsCount($migration);
                            $row[$field] = $sourceRowsCount ?? dt('N/A');
                            break;
                        case 'needing_update':
                            $row[$field] = $this->getMigrationNeedingUpdateCount($migration);
                            break;
                        case 'unprocessed':
                            $unprocessedCount = $this->getMigrationUnprocessedCount($migration);
                            $row[$field] = $unprocessedCount ?? dt('N/A');
                            break;
                        case 'imported':
                            $importedCount = $this->getMigrationImportedCount($migration);
                            if ($importedCount === null) {
                                // Next migration.
                                continue 2;
                            }
                            $sourceRowsCount ??= $this->getMigrationSourceRowsCount($migration);
                            if ($sourceRowsCount > 0 && $importedCount > 0) {
                                $importedCount .= ' (' . round(($importedCount / $sourceRowsCount) * 100, 1) . '%)';
                            }
                            $row[$field] = $importedCount;
                            break;
                        case 'last_imported':
                            $row[$field] = $this->getMigrationLastImportedTime($migration, $this->dateFormatter);
                            break;
                    }
                }
                $table[] = $row;
            }

            // Add an empty row after a tag group.
            if ($tag) {
                $table[] = $this->padTableRow([], $fields);
            }
        }

        return new RowsOfFields($table);
    }
}
