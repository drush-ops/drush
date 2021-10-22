<?php

namespace Drush\Drupal\Commands\core;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate\Plugin\RequirementsInterface;
use Drush\Commands\DrushCommands;
use Drush\Drupal\Migrate\MigrateExecutable;
use Drush\Drupal\Migrate\MigrateMessage;
use Drush\Drupal\Migrate\MigrateUtils;
use Drush\Utils\StringUtils;
use Webmozart\PathUtil\Path;

/**
 * Migrate runner commands.
 */
class MigrateRunnerCommands extends DrushCommands
{
    /**
     * Migration plugin manager service.
     *
     * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
     */
    protected $migrationPluginManager;

    /**
     * Date formatter service.
     *
     * @var \Drupal\Core\Datetime\DateFormatter
     */
    protected $dateFormatter;

    /**
     * The key-value store service.
     *
     * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
     */
    protected $keyValue;

    /**
     * Migrate message service.
     *
     * @var \Drupal\migrate\MigrateMessageInterface
     */
    protected $migrateMessage;

    /**
     * Constructs a new class instance.
     *
     * @param \Drupal\Core\Datetime\DateFormatter $dateFormatter
     *   Date formatter service.
     * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValueFactory
     *   The key-value factory service.
     */
    public function __construct(DateFormatter $dateFormatter, KeyValueFactoryInterface $keyValueFactory)
    {
        parent::__construct();
        $this->dateFormatter = $dateFormatter;
        $this->keyValue = $keyValueFactory->get('migrate_last_imported');
    }

    /**
     * List all migrations with current status.
     *
     * @command migrate:status
     *
     * @param string|null $migrationIds
     *   Restrict to a comma-separated list of migrations. Optional.
     *
     * @option tag A comma-separated list of migration tags to list. If only
     *   <info>--tag</info> is provided, all tagged migrations will be listed,
     *   grouped by tags.
     * @option names-only [Deprecated, use --field=id instead] Only return names, not all the details (faster).
     *
     * @usage migrate:status
     *   Retrieve status for all migrations
     * @usage migrate:status --tag
     *   Retrieve status for all migrations, grouped by tag
     * @usage migrate:status --tag=user,main_content
     *   Retrieve status for all migrations tagged with <info>user</info> or
     *   <info>main_content</info>
     * @usage migrate:status classification,article
     *   Retrieve status for specific migrations
     * @usage migrate:status --field=id
     *   Retrieve a raw list of migration IDs.
     * @usage ms --fields=id,status --format=json
     *   Retrieve a Json serialized list of migrations, each item containing only the migration ID and its status.
     *
     * @aliases ms,migrate-status
     *
     * @topics docs:migrate
     *
     * @validate-module-enabled migrate
     *
     * @field-labels
     *   id: Migration ID
     *   status: Status
     *   total: Total
     *   imported: Imported
     *   needing_update: Needing update
     *   unprocessed: Unprocessed
     *   last_imported: Last Imported
     * @default-fields id,status,total,imported,unprocessed,last_imported
     *
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     *   Migrations status formatted as table.
     *
     * @throws \Exception
     *   If --names-only is used with --field having other value than 'id'.
     */
    public function status(?string $migrationIds = null, array $options = [
      'tag' => self::REQ,
      'names-only' => false,
    ]): RowsOfFields
    {
        // The --names-only option takes precedence over --fields.
        if ($options['names-only']) {
            if ($options['field'] && $options['field'] !== 'id') {
                throw new \Exception("Cannot use --names-only with --field={$options['field']}.");
            }
            $deprecationMessage = 'The --names-only option is deprecated in Drush 10.5.1 and is removed from Drush 11.0.0. Use --field=id instead.';
            $this->logger()->warning($deprecationMessage);
            @trigger_error($deprecationMessage, E_USER_DEPRECATED);
            $fields = ['id'];
        } elseif ($options['field']) {
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
     * @param \Drupal\migrate\Plugin\MigrationInterface $migration
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
     * Returns the number or items that needs update.
     *
     * @param \Drupal\migrate\Plugin\MigrationInterface $migration
     *   The migration plugin instance.
     *
     * @return int|null
     *   The number or items that needs update.
     */
    protected function getMigrationNeedingUpdateCount(MigrationInterface $migration): int
    {
        $map = $migration->getIdMap();
        return count($map->getRowsNeedingUpdate($map->processedCount()));
    }

    /**
     * Returns the number of unprocessed items.
     *
     * @param \Drupal\migrate\Plugin\MigrationInterface $migration
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
     * @param \Drupal\migrate\Plugin\MigrationInterface $migration
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
     * @param \Drupal\migrate\Plugin\MigrationInterface $migration
     *   The migration plugin instance.
     *
     * @return string
     *   The last imported date/time if any.
     */
    protected function getMigrationLastImportedTime(MigrationInterface $migration): string
    {
        if ($lastImported = $this->keyValue->get($migration->id(), '')) {
            $lastImported = $this->dateFormatter->format($lastImported / 1000, 'custom', 'Y-m-d H:i:s');
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
     * @command migrate:import
     *
     * @param string|null $migrationIds
     *   Comma-separated list of migration IDs.
     *
     * @option all Process all migrations.
     * @option tag A comma-separated list of migration tags to import
     * @option limit Limit on the number of items to process in each migration
     * @option feedback Frequency of progress messages, in items processed
     * @option idlist Comma-separated list of IDs to import. As an ID may have more than one column, concatenate the columns with the colon ':' separator
     * @option update In addition to processing unprocessed items from the source, update previously-imported items with the current data
     * @option force Force an operation to run, even if all dependencies are not satisfied
     * @option execute-dependencies Execute all dependent migrations first.
     * @option timestamp Show progress ending timestamp in progress messages
     * @option total Show total processed item number in progress messages
     * @option progress Show progress bar
     * @option delete Delete destination records missed from the source. Not compatible with --limit and --idlist options, and high_water_property source configuration key.
     *
     * @usage migrate:import --all
     *   Perform all migrations
     * @usage migrate:import --all --no-progress
     *   Perform all migrations but avoid the progress bar
     * @usage migrate:import --tag=user,main_content
     *   Import all migrations tagged with <info>user</info> and <info>main_content</info> tags
     * @usage migrate:import classification,article
     *   Import new terms and nodes using migration <info>classification</info> and <info>article</info>
     * @usage migrate:import user --limit=2
     *   Import no more than 2 users using the <info>user</info> migration
     * @usage migrate:import user --idlist=5
     *   Import the user record with source ID 5
     * @usage migrate:import node_revision --idlist=1:2,2:3,3:5
     *   Import the node revision record with source IDs [1,2], [2,3], and [3,5]
     * @usage migrate:import user --limit=50 --feedback=20
     *   Import 50 users and show process message every 20th record
     * @usage migrate:import --all --delete
     *   Perform all migrations and delete the destination items that are missing from source
     *
     * @aliases mim,migrate-import
     *
     * @topics docs:migrate
     *
     * @validate-module-enabled migrate
     *
     * @throws \Exception
     *   When not enough options were provided or no migration was found.
     */
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
        require_once Path::join(DRUSH_BASE_PATH, 'src/Drupal/Migrate/migrate_runner.inc');
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
     * @param \Drupal\migrate\Plugin\MigrationInterface $migration
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
                $requiredMigrations = $this->getMigrationPluginManager()->createInstances($dependencies);
                array_walk($requiredMigrations, [static::class, __FUNCTION__], $userData);
            }
        }
        if (!empty($userData['options']['force'])) {
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
     * @command migrate:rollback
     *
     * @param string|null $migrationIds
     *   Comma-separated list of migration IDs.
     *
     * @option all Process all migrations.
     * @option tag A comma-separated list of migration tags to rollback
     * @option feedback Frequency of progress messages, in items processed
     * @option idlist Comma-separated list of IDs to rollback. As an ID may have more than one column, concatenate the columns with the colon ':' separator
     * @option progress Show progress bar
     *
     * @usage migrate:rollback --all
     *   Rollback all migrations
     * @usage migrate:rollback --all --no-progress
     *   Rollback all migrations but avoid the progress bar
     * @usage migrate:rollback --tag=user,main_content
     *   Rollback all migrations tagged with <info>user</info> and <info>main_content</info> tags
     * @usage migrate:rollback classification,article
     *   Rollback terms and nodes imported by <info>classification</info> and <info>article</info> migrations
     * @usage migrate:rollback user --idlist=5
     *   Rollback imported user record with source ID 5
     *
     * @aliases mr,migrate-rollback
     *
     * @topics docs:migrate
     *
     * @validate-module-enabled migrate
     *
     * @throws \Exception
     *   When not enough options were provided.
     */
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
     * @command migrate:stop
     *
     * @param string $migrationId
     *   The ID of migration to stop.
     *
     * @aliases mst,migrate-stop
     *
     * @topics docs:migrate
     *
     * @validate-module-enabled migrate
     * @validate-migration-id
     */
    public function stop(string $migrationId): void
    {
        /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
        $migration = $this->getMigrationPluginManager()->createInstance($migrationId);
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
     * @command migrate:reset-status
     *
     * @param string $migrationId
     *   The ID of migration to reset.
     *
     * @aliases mrs,migrate-reset-status
     *
     * @topics docs:migrate
     *
     * @validate-module-enabled migrate
     * @validate-migration-id
     */
    public function resetStatus(string $migrationId): void
    {
        /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
        $migration = $this->getMigrationPluginManager()->createInstance($migrationId);
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
     * @command migrate:messages
     *
     * @param string $migrationId
     *   The ID of the migration.
     *
     * @option idlist Comma-separated list of IDs to import. As an ID may have more than one column, concatenate the columns with the colon ':' separator.
     *
     * @usage migrate:messages article
     *   Show all messages for the <info>article</info> migration
     * @usage migrate:messages article --idlist=5
     *   Show messages related to article record with source ID 5.
     * @usage migrate:messages node_revision --idlist=1:2,2:3,3:5
     *   Show messages related to node revision records with source IDs [1,2], [2,3], and [3,5].
     * @usage migrate:messages custom_node_revision --idlist=1:"r:1",2:"r:3"
     *   Show messages related to node revision records with source IDs [1,"r:1"], and [2,"r:3"].
     *
     * @aliases mmsg,migrate-messages
     *
     * @topics docs:migrate
     *
     * @validate-module-enabled migrate
     * @validate-migration-id
     *
     * @field-labels
     *   level: Level
     *   source_ids: Source ID(s)
     *   destination_ids: Destination ID(s)
     *   message: Message
     *   hash: Source IDs hash
     * @default-fields level,source_ids,destination_ids,message,hash
     *
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     *   Migration messages status formatted as table.
     */
    public function messages(string $migrationId, array $options = ['idlist' => self::REQ]): RowsOfFields
    {
        /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
        $migration = $this->getMigrationPluginManager()->createInstance($migrationId);
        $idMap = $migration->getIdMap();
        $sourceIdKeys = $this->getSourceIdKeys($idMap);
        $table = [];
        if (empty($sourceIdKeys)) {
            // Cannot find one item to extract keys from, no need to process
            // messages on an empty ID map.
            return new RowsOfFields($table);
        }
        if (!empty($options['idlist'])) {
            // There is not way to retreive a filtered set of messages from an
            // ID map on Drupal core, right now.
            // Even if using \Drush\Drupal\Migrate\MigrateIdMapFilter does the
            // right thing filtering the data on the ID map, sadly its
            // getMessages() method does not take it into account the iterator,
            // and retrieves data directly, e.g. at SQL ID map plugin.
            // On the other side Drupal core's
            // \Drupal\migrate\Plugin\MigrateIdMapInterface only allows to
            // filter by one source IDs set, and not by multiple, on
            // getMessages().
            // For now, go over known IDs passed directly, one at a time a
            // work-around, at the cost of more queries in the usual SQL ID map,
            // which is likely OK for its use, to show only few source IDs
            // messages.
            foreach (MigrateUtils::parseIdList($options['idlist']) as $sourceIdValues) {
                foreach ($idMap->getMessages($sourceIdValues) as $row) {
                    $table[] = $this->preprocessMessageRow($row, $sourceIdKeys);
                }
            }
            return new RowsOfFields($table);
        }
        $table = [];
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
    protected function preprocessMessageRow(\StdClass $row, array $sourceIdKeys)
    {
        unset($row->msgid);
        $row = (array) $row;
        // If the message includes useful IDs don't print the hash.
        if (count($sourceIdKeys) === count(array_intersect_key($sourceIdKeys, $row))) {
            unset($row['source_ids_hash']);
        }
        $sourceIds = $destinationIds = [];
        foreach ($row as $key => $value) {
            if (substr($key, 0, 4) === 'src_') {
                $sourceIds[$key] = $value;
            }
            if (substr($key, 0, 5) === 'dest_') {
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
     * @command migrate:fields-source
     *
     * @param string $migrationId
     *   The ID of the migration.
     *
     * @usage migrate:fields-source article
     *   List fields for the source in the article migration
     *
     * @aliases mfs,migrate-fields-source
     *
     * @topics docs:migrate
     *
     * @validate-module-enabled migrate
     * @validate-migration-id
     *
     * @field-labels
     *   machine_name: Field name
     *   description: Description
     * @default-fields machine_name,description
     *
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     *   Source fields of the given migration.
     */
    public function fieldsSource(string $migrationId): RowsOfFields
    {
        /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
        $migration = $this->getMigrationPluginManager()->createInstance($migrationId);
        $source = $migration->getSourcePlugin();
        $table = [];
        foreach ($source->fields() as $machineName => $description) {
            $table[] = [
                'machine_name' => $machineName,
                'description' => strip_tags($description),
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
     * @return \Drupal\migrate\Plugin\MigrationInterface[][]
     *   An array keyed by migration tag, each value containing an array of
     *   migrations or an empty array if no migrations match the input criteria.
     *
     * @throws \Drupal\Component\Plugin\Exception\PluginException
     */
    protected function getMigrationList(?string $migrationIds, ?string $tags): array
    {
        $migrationIds = StringUtils::csvToArray($migrationIds);
        $migrations = $this->getMigrationPluginManager()->createInstances($migrationIds);

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
                $this->logger()->debug("Migration '{$migrationId}' is skipped as its source plugin has missed requirements: " . $exception->getRequirementsString());
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
            $migrationTags = (array)$migration->getMigrationTags();
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
     * @return \Drupal\migrate\MigrateMessageInterface
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
     * Returns the migration plugin manager service.
     *
     * @return \Drupal\migrate\Plugin\MigrationPluginManagerInterface
     *   The migration plugin manager service.
     *
     * @todo This service cannot be injected as the 'migrate' module might not
     *   be enabled and will throw the following exception:
     *   > Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     *   > The service "migrate_runner.commands" has a dependency on a
     *   > non-existent service "plugin.manager.migration".
     *   Unfortunately, we cannot avoid the class instantiation, via an
     *   annotation (as @validate-module-enabled for methods), if a specific
     *   module is not installed. Open a followup to tackle this issue.
     */
    protected function getMigrationPluginManager(): MigrationPluginManagerInterface
    {
        if (!isset($this->migrationPluginManager)) {
            $this->migrationPluginManager = \Drupal::service('plugin.manager.migration');
        }
        return $this->migrationPluginManager;
    }

    /**
     * Get the source ID keys.
     *
     * @param \Drupal\migrate\Plugin\MigrateIdMapInterface $idMap
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
            return "src_{$id}";
        }, array_keys($columns));
        return array_combine($sourceIdKeys, $sourceIdKeys);
    }

    /**
     * Validates a migration ID is valid.
     *
     * If the argument to be validated is not named migrationId, pass the
     * argument name as the value of the annotation.
     *
     * @hook validate @validate-migration-id
     *
     * @param \Consolidation\AnnotatedCommand\CommandData $commandData
     *
     * @return \Consolidation\AnnotatedCommand\CommandError|null
     */
    public function validateMigrationId(CommandData $commandData)
    {
        $argName = $commandData->annotationData()->get('validate-migration-id') ?: 'migrationId';
        $migrationId = $commandData->input()->getArgument($argName);
        if (!$this->getMigrationPluginManager()->hasDefinition($migrationId)) {
            return new CommandError(dt('Migration "@id" does not exist', ['@id' => $migrationId]));
        }
    }
}
