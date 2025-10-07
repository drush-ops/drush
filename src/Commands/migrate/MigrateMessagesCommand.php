<?php

declare(strict_types=1);

namespace Drush\Commands\migrate;

use Consolidation\OutputFormatters\FormatterManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drush\Attributes as CLI;
use Drush\Command\HelpLinks;
use Drush\Commands\AutowireTrait;
use Drush\Drupal\Migrate\MigrateUtils;
use Drush\Drupal\Migrate\ValidateMigrationId;
use Drush\Formatters\FormatterTrait;
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
    description: 'View any messages associated with a migration.',
    aliases: ['mmsg', 'migrate-messages'],
)]
#[CLI\HelpLinks(links: [HelpLinks::Migrate])]
#[CLI\ValidateModulesEnabled(modules: ['migrate'])]
#[ValidateMigrationId()]
#[CLI\FieldLabels(labels: [
    'level' => 'Level',
    'source_ids' => 'Source ID(s)',
    'destination_ids' => 'Destination ID(s)',
    'message' => 'Message',
    'hash' => 'Source IDs hash',
])]
#[CLI\DefaultFields(fields: [
    'level',
    'source_ids',
    'destination_ids',
    'message',
    'hash',
])]
#[CLI\Formatter(returnType: RowsOfFields::class, defaultFormatter: 'table')]
#[CLI\Version(version: '10.4')]
final class MigrateMessagesCommand extends Command
{
    use AutowireTrait;
    use FormatterTrait;
    use MigrateRunnerTrait;

    public const NAME = 'migrate:messages';

    public function __construct(
        #[Autowire(service: 'keyvalue')]
        protected readonly KeyValueFactoryInterface $keyValueFactory,
        protected readonly ContainerInterface $container,
        protected readonly LoggerInterface $logger,
        protected readonly FormatterManager $formatterManager,
    ) {
        parent::__construct();
        $this->keyValue = $keyValueFactory->get('migrate_last_imported');

        if ($container->has('plugin.manager.migration')) {
            $this->setMigrationPluginManager($container->get('plugin.manager.migration'));
        }
    }

    protected function configure(): void
    {
        $this
            ->addArgument('migrationId', InputArgument::REQUIRED, 'The ID of the migration.')
            ->addOption('idlist', null, InputOption::VALUE_REQUIRED, "Comma-separated list of IDs to import. As an ID may have more than one column, concatenate the columns with the colon ':' separator")
            ->addUsage('migrate:messages article')
            ->addUsage('migrate:messages node_revision --idlist=1:2,2:3,3:5')
            ->addUsage('migrate:messages custom_node_revision --idlist=1:"r:1",2:"r:3"')
            ->setHelp('Show all messages for a migration, or messages related to specific source IDs.');
    }

    /**
     * @throws PluginException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $data = $this->doExecute($input, $output);
        $this->writeFormattedOutput($input, $output, $data);
        return self::SUCCESS;
    }

    public function doExecute(InputInterface $input, OutputInterface $output): RowsOfFields
    {
        $migrationId = $input->getArgument('migrationId');
        $idlist = $input->getOption('idlist');

        /** @var MigrationInterface $migration */
        $migration = $this->migrationPluginManager->createInstance($migrationId);
        $idMap = $migration->getIdMap();
        $sourceIdKeys = $this->getSourceIdKeys($idMap);
        $table = [];
        if ($sourceIdKeys === []) {
            // Cannot find one item to extract keys from, no need to process
            // messages on an empty ID map.
            return new RowsOfFields($table);
        }
        if (!empty($idlist)) {
            // There is no way to retrieve a filtered set of messages from an ID
            // map on Drupal core, right now. Even if using
            // \Drush\Drupal\Migrate\MigrateIdMapFilter does the right thing
            // filtering the data on the ID map, sadly its getMessages() method
            // does not take it into account the iterator, and retrieves data
            // directly, e.g. at SQL ID map plugin. On the other side Drupal
            // core's \Drupal\migrate\Plugin\MigrateIdMapInterface only allows
            // to filter by one source IDs set, and not by multiple, on
            // getMessages(). For now, go over known IDs passed directly, one at
            // a time a workaround, at the cost of more queries in the usual SQL
            // ID map, which is likely OK for its use, to show only few source
            // IDs messages.
            foreach (MigrateUtils::parseIdList($idlist) as $sourceIdValues) {
                foreach ($idMap->getMessages($sourceIdValues) as $row) {
                    $table[] = $this->preprocessMessageRow($row, $sourceIdKeys);
                }
            }
            return new RowsOfFields($table);
        }
        foreach ($idMap->getMessages() as $row) {
            $table[] = $this->preprocessMessageRow($row, $sourceIdKeys);
        }
        return new RowsOfFields($table);
    }
}
