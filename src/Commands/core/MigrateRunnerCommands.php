<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate\Plugin\RequirementsInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Drupal\Migrate\MigrateExecutable;
use Drush\Drupal\Migrate\MigrateMessage;
use Drush\Drupal\Migrate\MigrateUtils;
use Drush\Drupal\Migrate\ValidateMigrationId;
use Drush\Drush;
use Drush\Utils\StringUtils;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;

final class MigrateRunnerCommands extends DrushCommands
{
    use AutowireTrait;

    protected ?MigrationPluginManagerInterface $migrationPluginManager = null;
    protected KeyValueStoreInterface $keyValue;
    private MigrateMessage $migrateMessage;

    public function __construct(
        protected DateFormatterInterface $dateFormatter,
        // @todo Can we avoid the autowire attribute here?
        #[Autowire(service: 'keyvalue')]
        protected KeyValueFactoryInterface $keyValueFactory
    ) {
        parent::__construct();
        $this->keyValue = $keyValueFactory->get('migrate_last_imported');

        $container = Drush::getContainer();
        if ($container->has('plugin.manager.migration')) {
            $this->setMigrationPluginManager($container->get('plugin.manager.migration'));
        }
    }

    /**
     * Provide a migration plugin manager.
     */
    public function setMigrationPluginManager(MigrationPluginManagerInterface $migrationPluginManager)
    {
        $this->migrationPluginManager = $migrationPluginManager;
    }

    /**
     * List all migrations with current status.
     */
    #[CLI\Command(name: 'migrate:status', aliases: ['ms', 'migrate-status'])]
    #[CLI\Argument(name: 'migrationIds', description: 'Restrict to a comma-separated list of migrations. Optional.')]
    #[CLI\Option(name: 'tag', description: 'A comma-separated list of migration tags to list. If only <info>--tag</info> is provided, all tagged migrations will be listed, grouped by tags.')]
    #[CLI\Usage(name: 'migrate:status', description: 'Retrieve status for all migrations')]
    #[CLI\Usage(name: 'migrate:status --tag', description: 'Retrieve status for all migrations, grouped by tag')]
    #[CLI\Usage(name: 'migrate:status --tag=user,main_content', description: 'Retrieve status for all migrations tagged with <info>user</info> or <info>main_content</info>')]
    #[CLI\Usage(name: 'migrate:status classification,article', description: 'Retrieve status for specific migrations')]
    #[CLI\Usage(name: 'migrate:status --field=id', description: 'Retrieve a raw list of migration IDs.')]
    #[CLI\Usage(name: 'ms --fields=id,status --format=json', description: 'Retrieve a Json serialized list of migrations, each item containing only the migration ID and its status.')]
    #[CLI\Topics(topics: [DocsCommands::MIGRATE])]
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
    #[CLI\DefaultFields(fields: [
        'id',
        'status',
        'total',
        'imported',
        'unprocessed',
        'last_imported',
    ])]
    #[CLI\FilterDefaultField(field: 'status')]
    #[CLI\Version(version:  '10.4')]
    public function status(?string $migrationIds = null, array $options = [
      'tag' => self::REQ,
      'format' => 'table'
    ]): RowsOfFields
    {
        $fields = [];
        if ($options['field']) {
            $fields = [$options['field']];
        } elseif ($options['fields']) {
            $fields = StringUtils::csvToArray($options['fields']);
        }

        $list = $this->getMigrationList($migrationIds, $options['tag']);

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
                            $row[$field] = $sourceRowsCount !== null ? $sourceRowsCount : dt('N/A');
                            break;
                        case 'needing_update':
                            $row[$field] = $this->getMigrationNeedingUpdateCount($migration);
                            break;
                        case 'unprocessed':
                            $unprocessedCount = $this->getMigrationUnprocessedCount($migration);
                            $row[$field] = $unprocessedCount !== null ? $unprocessedCount : dt('N/A');
                            break;
                        case 'imported':
                            $importedCount = $this->getMigrationImportedCount($migration);
                            if ($importedCount === null) {
                                // Next migration.
                                continue 2;
                            }
                            $sourceRowsCount = $sourceRowsCount ?? $this->getMigrationSourceRowsCount($migration);
                            if ($sourceRowsCount > 0 && $importedCount > 0) {
                                $importedCount .= ' (' . round(($importedCount / $sourceRowsCount) * 100, 1) . '%)';
                            }
                            $row[$field] = $importedCount;
                            break;
                        case 'last_imported':
                            $row[$field] = $this->getMigrationLastImportedTime($migration);
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

    /**
     * Returns the migration source rows count.
     *
     * @param MigrationInterface $migration
     *   The migration plugin instance.
     * @return int|null
     *   The migration source rows count or null if the source is uncountable or
     *   the source count couldn't be retrieved.
     */
    protected function getMigrationSourceRowsCount(MigrationInterface $migration): ?int
    {
        try {
            $sourceRowsCount = $migration->getSourcePlugin()->count();
            // -1 indicates uncountable sources.
            if ($sourceRowsCount === -1) {
                return null;
            }
            return $sourceRowsCount;
        } catch (\Exception $exception) {
            $arguments = [
              '@migration' => $migration->id(),
              '@message' => $exception->getMessage(),
            ];
            $this->logger()->error(dt('Could not retrieve source count from @migration: @message', $arguments));
            return null;
        }
    }

    /**
     * Returns the number of items that needs update.
     *
     * @param MigrationInterface $migration
     *   The migration plugin instance.
     *
     * @return int
     *   The number of items that needs update.
     */
    protected function getMigrationNeedingUpdateCount(MigrationInterface $migration): int
    {
        $map = $migration->getIdMap();
        return count($map->getRowsNeedingUpdate($map->processedCount()));
    }

    /**
     * Returns the number of unprocessed items.
     *
     * @param MigrationInterface $migration
     *   The migration plugin instance.
     *
     * @return int|null
     *   The number of unprocessed items or null if it cannot be determined.
     */
    protected function getMigrationUnprocessedCount(MigrationInterface $migration): ?int
    {
        $sourceRowsCount = $this->getMigrationSourceRowsCount($migration);
        if ($sourceRowsCount === null) {
            return null;
        }
        return $sourceRowsCount - $migration->getIdMap()->processedCount();
    }

    /**
     * Returns the number of imported items.
     *
     * @param MigrationInterface $migration
     *   The migration plugin instance.
     *
     * @return int|null
     *   The number of imported items or null if it cannot be determined.
     */
    protected function getMigrationImportedCount(MigrationInterface $migration): ?int
    {
        try {
            return $migration->getIdMap()->importedCount();
        } catch (\Exception $exception) {
            $arguments = [
              '@migration' => $migration->id(),
              '@message' => $exception->getMessage(),
            ];
            $this->logger()->error(dt('Failure retrieving information on @migration: @message', $arguments));
            return null;
        }
    }

    /**
     * Returns the last imported date/time if any.
     *
     * @param MigrationInterface $migration
     *   The migration plugin instance.
     *
     * @return string
     *   The last imported date/time if any.
     */
    protected function getMigrationLastImportedTime(MigrationInterface $migration): string
    {
        if ($lastImported = $this->keyValue->get($migration->id(), '')) {
            $lastImported = $this->dateFormatter->format(round($lastImported / 1000), 'custom', 'Y-m-d H:i:s');
        }
        return $lastImported;
    }

    /**
     * Pads an incomplete table row with empty cells.
     *
     * @param array $row
     *   The row to be prepared.
     * @param array $fields
     *   The table columns.
     *
     * @return array
     *   The complete table row.
     */
    protected function padTableRow(array $row, array $fields): array
    {
        foreach (array_diff_key(array_flip($fields), $row) as $field => $delta) {
            $row[$field] = null;
        }
        return $row;
    }

    /**
     * Perform one or more migration processes.
     *
     * @throws \Exception
     *   When not enough options were provided or no migration was found.
     */
    #[CLI\Command(name: 'migrate:import', aliases: ['mim', 'migrate-import'])]
    #[CLI\Argument(name: 'migrationIds', description: 'Comma-separated list of migration IDs.')]
    #[CLI\Option(name: 'all', description: 'Process all migrations')]
    #[CLI\Option(name: 'tag', description: 'A comma-separated list of migration tags to import')]
    #[CLI\Option(name: 'limit', description: 'Limit on the number of items to process in each migration')]
    #[CLI\Option(name: 'feedback', description: 'Frequency of progress messages, in items processed')]
    #[CLI\Option(name: 'idlist', description: "Comma-separated list of IDs to import. As an ID may have more than one column, concatenate the columns with the colon ':' separator")]
    #[CLI\Option(name: 'update', description: 'In addition to processing unprocessed items from the source, update previously-imported items with the current data')]
    #[CLI\Option(name: 'force', description: 'Force an operation to run, even if all dependencies are not satisfied')]
    #[CLI\Option(name: 'execute-dependencies', description: 'Execute all dependent migrations first')]
    #[CLI\Option(name: 'timestamp', description: 'Show progress ending timestamp in progress messages')]
    #[CLI\Option(name: 'total', description: 'Show total processed item number in progress messages')]
    #[CLI\Option(name: 'progress', description: 'Show progress bar')]
    #[CLI\Option(name: 'delete', description: 'Delete destination records missed from the source. Not compatible with <info>--limit</info> and <info>--idlist</info> options, and high_water_property source configuration key.')]
    #[CLI\Usage(name: 'migrate:import --all', description: 'Perform all migrations')]
    #[CLI\Usage(name: 'migrate:import --all --no-progress', description: 'Perform all migrations but avoid the progress bar')]
    #[CLI\Usage(name: 'migrate:import --tag=user,main_content', description: 'Import all migrations tagged with <info>user</info> and <info>main_content</info> tags')]
    #[CLI\Usage(name: 'migrate:import classification,article', description: 'Import new terms and nodes using migration <info>classification</info> and <info>article</info>')]
    #[CLI\Usage(name: 'migrate:import user --limit=2', description: 'Import no more than 2 users using the <info>user</info> migration')]
    #[CLI\Usage(name: 'migrate:import user --idlist=5', description: 'Import the user record with source ID 5')]
    #[CLI\Usage(name: 'migrate:import node_revision --idlist=1:2,2:3,3:5', description: 'Import the node revision record with source IDs [1,2], [2,3], and [3,5]')]
    #[CLI\Usage(name: 'migrate:import user --limit=50 --feedback=20', description: 'Import 50 users and show process message every 20th record')]
    #[CLI\Usage(name: 'migrate:import --all --delete', description: 'Perform all migrations and delete the destination items that are missing from source')]
    #[CLI\Topics(topics: [DocsCommands::MIGRATE])]
    #[CLI\ValidateModulesEnabled(modules: ['migrate'])]
    #[CLI\Version(version: '10.4')]
    public function import(?string $migrationIds = null, array $options = ['all' => false, 'tag' => self::REQ, 'limit' => self::REQ, 'feedback' => self::REQ, 'idlist' => self::REQ, 'update' => false, 'force' => false, 'execute-dependencies' => false, 'timestamp' => false, 'total' => false, 'progress' => true, 'delete' => false]): void
    {
        $tags = $options['tag'];
        $all = $options['all'];

        if (!$all && !$migrationIds && !$tags) {
            throw new \Exception(dt('You must specify --all, --tag or one or more migration names separated by commas'));
        }

        if (!$list = $this->getMigrationList($migrationIds, $options['tag'])) {
            throw new \Exception(dt('No migrations found.'));
        }

        $userData = [
            'options' => array_intersect_key($options, array_flip([
                'limit',
                'feedback',
                'idlist',
                'update',
                'force',
                'timestamp',
                'total',
                'progress',
                'delete',
            ])),
            'execute_dependencies' => $options['execute-dependencies'],
        ];

        // Include the file providing a migrate_prepare_row hook implementation.
        require_once Path::join($this->getConfig()->get('drush.base-dir'), 'src/Drupal/Migrate/migrate_runner.inc');
        // If the 'migrate_prepare_row' hook implementations are already cached,
        // make sure that system_migrate_prepare_row() is picked-up.
        \Drupal::moduleHandler()->resetImplementations();

        foreach ($list as $migrations) {
            array_walk($migrations, [static::class, 'executeMigration'], $userData);
        }
    }

    /**
     * Executes a single migration.
     *
     * If the --execute-dependencies option was given, the migration's
     * dependencies will also be executed first.
     *
     * @param MigrationInterface $migration
     *   The migration to execute.
     * @param string $migrationId
     *   The migration ID (not used, just an artifact of array_walk()).
     * @param array $userData
     *   Additional data passed to the callback.
     *
     * @throws \Exception
     *   If there are failed migrations.
     */
    protected function executeMigration(MigrationInterface $migration, string $migrationId, array $userData): void
    {
        static $executedMigrations = [];

        if ($userData['execute_dependencies']) {
            $dependencies = $migration->getMigrationDependencies()['required'];
            // Remove already executed migrations.
            $dependencies = array_diff($dependencies, $executedMigrations);
            if ($dependencies) {
                $requiredMigrations = $this->migrationPluginManager->createInstances($dependencies);
                array_walk($requiredMigrations, [static::class, __FUNCTION__], $userData);
            }
        }
        if (!empty($userData['options']['force'])) {
            // @todo Use the new MigrationInterface::setRequirements() method,
            //   instead of Migration::set() and remove the PHPStan exception
            //   from phpstan-baseline.neon when #2796755 lands in Drupal core.
            // @see https://www.drupal.org/i/2796755
            $migration->set('requirements', []);
        }
        if (!empty($userData['options']['update'])) {
            if (empty($userData['options']['idlist'])) {
                $migration->getIdMap()->prepareUpdate();
            } else {
                $sourceIdValuesList = MigrateUtils::parseIdList($userData['options']['idlist']);
                $keys = array_keys($migration->getSourcePlugin()->getIds());
                foreach ($sourceIdValuesList as $sourceIdValues) {
                    $migration->getIdMap()->setUpdate(array_combine($keys, $sourceIdValues));
                }
            }
        }

        $executable = new MigrateExecutable($migration, $this->getMigrateMessage(), $this->output(), $userData['options']);
        // drush_op() provides --simulate support.
        drush_op([$executable, 'import']);
        if ($count = $executable->getFailedCount()) {
            // Nudge Drush to use a non-zero exit code.
            throw new \Exception(dt('!name migration: !count failed.', ['!name' => $migrationId, '!count' => $count]));
        }

        // Keep track of executed migrations.
        $executedMigrations[] = $migrationId;
    }

    /**
     * Rollback one or more migrations.
     *
     * @throws \Exception
     *   When not enough options were provided.
     */
    #[CLI\Command(name: 'migrate:rollback', aliases: ['mr', 'migrate-rollback'])]
    #[CLI\Argument(name: 'migrationIds', description: 'Comma-separated list of migration IDs.')]
    #[CLI\Option(name: 'all', description: 'Process all migrations')]
    #[CLI\Option(name: 'tag', description: 'A comma-separated list of migration tags to rollback')]
    #[CLI\Option(name: 'feedback', description: 'Frequency of progress messages, in items processed')]
    #[CLI\Option(name: 'idlist', description: "Comma-separated list of IDs to rollback. As an ID may have more than one column, concatenate the columns with the colon ':' separator")]
    #[CLI\Option(name: 'progress', description: 'Show progress bar')]
    #[CLI\Usage(name: 'migrate:rollback --all', description: 'Rollback all migrations')]
    #[CLI\Usage(name: 'migrate:rollback --all --no-progress', description: 'Rollback all migrations but avoid the progress bar')]
    #[CLI\Usage(name: 'migrate:rollback --tag=user,main_content', description: 'Rollback all migrations tagged with <info>user</info> and <info>main_content</info> tags')]
    #[CLI\Usage(name: 'migrate:rollback classification,article', description: 'Rollback terms and nodes imported by <info>classification</info> and <info>article</info> migrations')]
    #[CLI\Usage(name: 'migrate:rollback user --idlist=5', description: 'Rollback imported user record with source ID 5')]
    #[CLI\Topics(topics: [DocsCommands::MIGRATE])]
    #[CLI\ValidateModulesEnabled(modules: ['migrate'])]
    #[CLI\Version(version: '10.4')]
    public function rollback(?string $migrationIds = null, array $options = ['all' => false, 'tag' => self::REQ, 'feedback' => self::REQ, 'idlist' => self::REQ, 'progress' => true]): void
    {
        $tags = $options['tag'];
        $all = $options['all'];

        if (!$all && !$migrationIds && !$tags) {
            throw new \Exception(dt('You must specify --all, --tag, or one or more migration names separated by commas'));
        }

        if (!$list = $this->getMigrationList($migrationIds, $options['tag'])) {
            $this->logger()->error(dt('No migrations found.'));
        }

        $executableOptions = array_intersect_key(
            $options,
            array_flip(['feedback', 'idlist', 'progress'])
        );
        foreach ($list as $migrations) {
            // Rollback in reverse order.
            $migrations = array_reverse($migrations);
            foreach ($migrations as $migration) {
                $executable = new MigrateExecutable($migration, $this->getMigrateMessage(), $this->output(), $executableOptions);
                // drush_op() provides --simulate support.
                drush_op([$executable, 'rollback']);
            }
        }
    }

    /**
     * Stop an active migration operation.
     *
     * @throws PluginException
     */
    #[CLI\Command(name: 'migrate:stop', aliases: ['mst', 'migrate-stop'])]
    #[CLI\Argument(name: 'migrationId', description: 'The ID of migration to stop.')]
    #[CLI\Topics(topics: [DocsCommands::MIGRATE])]
    #[CLI\ValidateModulesEnabled(modules: ['migrate'])]
    #[ValidateMigrationId()]
    #[CLI\Version(version: '10.4')]
    public function stop(string $migrationId): void
    {
        /** @var MigrationInterface $migration */
        $migration = $this->migrationPluginManager->createInstance($migrationId);
        switch ($migration->getStatus()) {
            case MigrationInterface::STATUS_IDLE:
                $this->logger()->warning(dt('Migration @id is idle', ['@id' => $migrationId]));
                break;
            case MigrationInterface::STATUS_DISABLED:
                $this->logger()->warning(dt('Migration @id is disabled', ['@id' => $migrationId]));
                break;
            case MigrationInterface::STATUS_STOPPING:
                $this->logger()->warning(dt('Migration @id is already stopping', ['@id' => $migrationId]));
                break;
            default:
                $migration->interruptMigration(MigrationInterface::RESULT_STOPPED);
                $this->logger()->success(dt('Migration @id requested to stop', ['@id' => $migrationId]));
                break;
        }
    }

    /**
     * Reset an active migration's status to idle.
     *
     * @throws PluginException
     */
    #[CLI\Command(name: 'migrate:reset-status', aliases: ['mrs', 'migrate-reset-status'])]
    #[CLI\Argument(name: 'migrationId', description: 'The ID of migration to reset.')]
    #[CLI\Topics(topics: [DocsCommands::MIGRATE])]
    #[CLI\ValidateModulesEnabled(modules: ['migrate'])]
    #[ValidateMigrationId()]
    #[CLI\Version(version: '10.4')]
    public function resetStatus(string $migrationId): void
    {
        /** @var MigrationInterface $migration */
        $migration = $this->migrationPluginManager->createInstance($migrationId);
        $status = $migration->getStatus();
        if ($status == MigrationInterface::STATUS_IDLE) {
            $this->logger()->warning(dt('Migration @id is already Idle', ['@id' => $migrationId]));
        } else {
            $migration->setStatus(MigrationInterface::STATUS_IDLE);
            $this->logger()->success(dt('Migration @id reset to Idle', ['@id' => $migrationId]));
        }
    }

    /**
     * View any messages associated with a migration.
     *
     * @throws PluginException
     */
    #[CLI\Command(name: 'migrate:messages', aliases: ['mmsg', 'migrate-messages'])]
    #[CLI\Argument(name: 'migrationId', description: 'The ID of the migration.')]
    #[CLI\Option(name: 'idlist', description: "Comma-separated list of IDs to import. As an ID may have more than one column, concatenate the columns with the colon ':' separator")]
    #[CLI\Usage(name: 'migrate:messages article', description: 'Show all messages for the <info>article</info> migration')]
    #[CLI\Usage(name: 'migrate:messages node_revision --idlist=1:2,2:3,3:5', description: 'Show messages related to node revision records with source IDs [1,2], [2,3], and [3,5].')]
    #[CLI\Usage(name: 'migrate:messages custom_node_revision --idlist=1:"r:1",2:"r:3"', description: 'Show messages related to node revision records with source IDs [1,"r:1"], and [2,"r:3"].')]
    #[CLI\Topics(topics: [DocsCommands::MIGRATE])]
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
    #[CLI\Version(version: '10.4')]
    public function messages(string $migrationId, array $options = ['idlist' => self::REQ, 'format' => 'table']): RowsOfFields
    {
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
        if (!empty($options['idlist'])) {
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
            foreach (MigrateUtils::parseIdList($options['idlist']) as $sourceIdValues) {
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

    /**
     * Preprocesses migrate message rows.
     *
     * Given an item inside the list generated by
     * MigrateIdMapInterface::getMessages(), prepare it for display.
     *
     * @param \StdClass $row
     *   A message to process.
     * @param array $sourceIdKeys
     *   The source IDs keys, for reference.
     *
     * @see \Drupal\migrate\Plugin\MigrateIdMapInterface::getMessages()
     */
    protected function preprocessMessageRow(\StdClass $row, array $sourceIdKeys): array
    {
        unset($row->msgid);
        $row = (array) $row;
        // If the message includes useful IDs don't print the hash.
        if (count($sourceIdKeys) === count(array_intersect_key($sourceIdKeys, $row))) {
            unset($row['source_ids_hash']);
        }
        $sourceIds = $destinationIds = [];
        foreach ($row as $key => $value) {
            if (str_starts_with($key, 'src_')) {
                $sourceIds[$key] = $value;
            }
            if (str_starts_with($key, 'dest_')) {
                $destinationIds[$key] = $value;
            }
        }
        $row['source_ids'] = implode(' : ', $sourceIds);
        $row['destination_ids'] = implode(' : ', $destinationIds);
        return $row;
    }

    /**
     * List the fields available for mapping in a source.
     *
     * @throws PluginException
     */
    #[CLI\Command(name: 'migrate:fields-source', aliases: ['mfs', 'migrate-fields-source'])]
    #[CLI\Argument(name: 'migrationId', description: 'The ID of the migration.')]
    #[CLI\Usage(name: 'migrate:fields-source article', description: 'List fields for the source in the article migration.')]
    #[CLI\Topics(topics: ['docs:migrate'])]
    #[CLI\ValidateModulesEnabled(modules: ['migrate'])]
    #[ValidateMigrationId()]
    #[CLI\FieldLabels(labels: [
        'machine_name' => 'Field name',
        'description' => 'Description',
    ])]
    #[CLI\DefaultFields(fields: ['machine_name', 'description'])]
    #[CLI\Version(version: '10.4')]
    public function fieldsSource(string $migrationId, $options = ['format' => 'table']): RowsOfFields
    {
        /** @var MigrationInterface $migration */
        $migration = $this->migrationPluginManager->createInstance($migrationId);
        $source = $migration->getSourcePlugin();
        $table = [];
        foreach ($source->fields() as $machineName => $description) {
            $table[] = [
                'machine_name' => $machineName,
                'description' => strip_tags((string) $description),
            ];
        }
        return new RowsOfFields($table);
    }

    /**
     * Retrieves a list of active migrations.
     *
     * @param string|null $migrationIds A comma-separated list of migration IDs. If omitted, will return all
     *   migrations.
     * @param string|null $tags
     *   A comma separated list of tags.
     *
     * @return MigrationInterface[][]
     *   An array keyed by migration tag, each value containing an array of
     *   migrations or an empty array if no migrations match the input criteria.
     *
     * @throws PluginException
     */
    protected function getMigrationList(?string $migrationIds, ?string $tags): array
    {
        $migrationIds = StringUtils::csvToArray((string) $migrationIds);
        $migrations = $this->migrationPluginManager->createInstances($migrationIds);

        // Check for invalid migration IDs.
        if ($invalidMigrations = array_diff_key(array_flip($migrationIds), $migrations)) {
            throw new \InvalidArgumentException('Invalid migration IDs: ' . implode(', ', array_flip($invalidMigrations)));
        }

        foreach ($migrations as $migrationId => $migration) {
            try {
                $sourcePlugin = $migration->getSourcePlugin();
                if ($sourcePlugin instanceof RequirementsInterface) {
                    $sourcePlugin->checkRequirements();
                }
            } catch (RequirementsException $exception) {
                $this->logger()->debug("Migration '$migrationId' is skipped as its source plugin has missed requirements: {$exception->getRequirementsString()}");
                unset($migrations[$migrationId]);
            }
        }

        // If --tag was not passed, don't group on tags, use a single empty tag.
        if ($tags === null) {
            return [null => $migrations];
        }

        $tags = array_filter(array_map('trim', explode(',', $tags)));

        $list = [];
        foreach ($migrations as $migrationId => $migration) {
            $migrationTags = $migration->getMigrationTags();
            $commonTags = array_intersect($tags, $migrationTags);
            if (!$commonTags) {
                // Skip if migration is not tagged with any of the passed tags.
                continue;
            }
            foreach ($commonTags as $tag) {
                $list[$tag][$migrationId] = $migration;
            }
        }
        ksort($list);

        return $list;
    }

    /**
     * Returns the migrate message logger.
     *
     * @return MigrateMessageInterface
     *   The migrate message logger.
     */
    protected function getMigrateMessage(): MigrateMessageInterface
    {
        if (!isset($this->migrateMessage)) {
            $this->migrateMessage = new MigrateMessage($this->logger());
        }
        return $this->migrateMessage;
    }

    /**
     * Get the source ID keys.
     *
     * @param MigrateIdMapInterface $idMap
     *   The migration ID map.
     *
     * @return string[]
     *   The source ID keys.
     */
    protected function getSourceIdKeys(MigrateIdMapInterface $idMap): array
    {
        if (iterator_count($idMap) === 0) {
            // E.g. when the migration has not yet been executed. the ID map is
            // empty. do not try to process it.
            return [];
        }
        $idMap->rewind();
        $columns = $idMap->currentSource();
        $sourceIdKeys = array_map(static function ($id) {
            return "src_$id";
        }, array_keys($columns));
        return array_combine($sourceIdKeys, $sourceIdKeys);
    }

    /**
     * Validates a migration ID is valid.
     *
     * If the argument to be validated is not named migrationId, pass the
     * argument name as the value of the annotation.
     */
    #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, selector: 'validate-migration-id')]
    public function validateMigrationId(CommandData $commandData): ?CommandError
    {
        $argName = $commandData->annotationData()->get('validate-migration-id') ?: 'migrationId';
        $migrationId = $commandData->input()->getArgument($argName);
        if (!$this->migrationPluginManager->hasDefinition($migrationId)) {
            return new CommandError(dt('Migration "@id" does not exist', ['@id' => $migrationId]));
        }
        return null;
    }
}
