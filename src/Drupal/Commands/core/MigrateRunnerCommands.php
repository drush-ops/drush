<?php

namespace Drush\Drupal\Commands\core;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate\Plugin\RequirementsInterface;
use Drush\Commands\DrushCommands;
use Drush\Drupal\Migrate\MigrateCommandProgressBar;
use Drush\Drupal\Migrate\MigrateExecutable;
use Drush\Drupal\Migrate\MigrateMessage;
use Drush\Drupal\Migrate\MigrateUtils;
use Webmozart\PathUtil\Path;

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
     * The command progress bar service.
     *
     * @var \Drush\Drupal\Migrate\MigrateCommandProgressBar
     */
    protected $commandProgress;

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
     * @param \Drush\Drupal\Migrate\MigrateCommandProgressBar $commandProgress
     *   The command progress bar service.
     */
    public function __construct(DateFormatter $dateFormatter, KeyValueFactoryInterface $keyValueFactory, MigrateCommandProgressBar $commandProgress)
    {
        parent::__construct();
        $this->dateFormatter = $dateFormatter;
        $this->keyValue = $keyValueFactory->get('migrate_last_imported');
        $this->commandProgress = $commandProgress;
    }

    /**
     * List all migrations with current status.
     *
     * @command migrate:status
     *
     * @drupal-dependencies migrate
     *
     * @param string|null $migrationIds
     *   Restrict to a comma-separated list of migrations. Optional.
     *
     * @option tag A comma-separated list of migration tags to list. If only --tag is provided, all migrations will be listed, grouped by tags.
     * @option names-only Only return names, not all the details (faster)
     * @usage migrate-status
     *   Retrieve status for all migrations
     * @usage migrate-status --tag
     *   Retrieve status for all migrations, grouped by tag
     * @usage migrate-status --tag=user,main_content
     *   Retrieve status for all migrations tagged with "user" or "main_content"
     * @usage migrate-status classification,article
     *   Retrieve status for specific migrations
     * @aliases ms,migrate-status
     * @topics docs:migrate
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
     * @default-fields id,status,total,imported,needing_update,unprocessed,last_imported
     *
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     *   Migrations status formatted as table.
     */
    public function status(?string $migrationIds = null, array $options = ['tag' => self::REQ, 'names-only' => false]): RowsOfFields
    {
        $namesOnly = $options['names-only'];
        $list = $this->getMigrationList($migrationIds, $options['tag']);

        $table = [];
        // Take it one tag at a time, listing the migrations within each tag.
        foreach ($list as $tag => $migrations) {
            if ($tag) {
                $table[] = $this->prepareTableRow(['id' => dt('Tag: @name', ['@name' => $tag])], $namesOnly);
            }
            ksort($migrations);
            foreach ($migrations as $migrationId => $migration) {
                $printedMigrationId = ($tag ? ' ' : '') . $migrationId;
                if ($namesOnly) {
                    $table[] = $this->prepareTableRow(['id' => $printedMigrationId], $namesOnly);
                    // No future processing is needed. We're done with this row.
                    continue;
                }

                try {
                    $map = $migration->getIdMap();
                    $imported = $map->importedCount();
                    $sourcePlugin = $migration->getSourcePlugin();
                } catch (\Exception $exception) {
                    $arguments = ['@migration' => $migrationId, '@message' => $exception->getMessage()];
                    $this->logger()->error(dt('Failure retrieving information on @migration: @message', $arguments));
                    continue;
                }

                try {
                    $sourceRows = $sourcePlugin->count();
                    // -1 indicates uncountable sources.
                    if ($sourceRows == -1) {
                        $sourceRows = dt('N/A');
                        $unprocessed = dt('N/A');
                    } else {
                        $unprocessed = $sourceRows - $map->processedCount();
                        if ($sourceRows > 0 && $imported > 0) {
                            $imported .= ' (' . round(($imported / $sourceRows) * 100, 1) . '%)';
                        }
                    }
                    $needingUpdate = count($map->getRowsNeedingUpdate($map->processedCount()));
                } catch (\Exception $exception) {
                    $arguments = ['@migration' => $migrationId, '@message' => $exception->getMessage()];
                    $this->logger()->error(dt('Could not retrieve source count from @migration: @message', $arguments));
                    $sourceRows = dt('N/A');
                    $unprocessed = dt('N/A');
                    $needingUpdate = dt('N/A');
                }

                $status = $migration->getStatusLabel();
                if ($lastImported = $this->keyValue->get($migration->id(), '')) {
                    $lastImported = $this->dateFormatter->format(
                        $lastImported / 1000,
                        'custom',
                        'Y-m-d H:i:s'
                    );
                }

                $table[] = [
                    'id' => $printedMigrationId,
                    'status' => $status,
                    'total' => $sourceRows,
                    'imported' => $imported,
                    'needing_update' => $needingUpdate,
                    'unprocessed' => $unprocessed,
                    'last_imported' => $lastImported,
                ];
            }

            // Add an empty row after a tag group.
            if ($tag) {
                $table[] = $this->prepareTableRow([], $namesOnly);
            }
        }

        return new RowsOfFields($table);
    }

    /**
     * Prepares a table row for migrate status.
     *
     * @param array $row
     *   The row to be prepared.
     * @param true|null $namesOnly
     *   If to output only the migration IDs.
     *
     * @return array
     *   The prepared row.
     */
    protected function prepareTableRow(array $row, ?bool $namesOnly): array
    {
        $defaults = array_fill_keys([
          'id',
          'status',
          'total',
          'imported',
          'needing_update',
          'unprocessed',
          'last_imported',
        ], null);
        if (!$namesOnly) {
            $row += $defaults;
        }

        return $row;
    }

    /**
     * Perform one or more migration processes.
     *
     * @command migrate:import
     *
     * @drupal-dependencies migrate
     *
     * @param string|null $migrationIds
     *   Comma-separated list of migration IDs.
     *
     * @option all Process all migrations.
     * @option tag A comma-separated list of migration tags to import
     * @option limit Limit on the number of items to process in each migration
     * @option feedback Frequency of progress messages, in items processed
     * @option idlist Comma-separated list of IDs to import. As an ID may have more than one columns, concatenate the columns with the colon ':' separator
     * @option update In addition to processing unprocessed items from the source, update previously-imported items with the current data
     * @option force Force an operation to run, even if all dependencies are not satisfied
     * @option execute-dependencies Execute all dependent migrations first.
     * @option timestamp Show progress ending timestamp in progress messages
     * @option total Show total processed item number in progress messages
     * @option progress Show progress bar
     *
     * @usage migrate-import --all
     *   Perform all migrations
     * @usage migrate-import --tag=user,main_content
     *   Import all migrations tagged with user and main_content tags
     * @usage migrate-import classification,article
     *   Import new terms and nodes
     * @usage migrate-import beer_user --limit=2
     *   Import no more than 2 users
     * @usage migrate-import beer_user --idlist=5
     *   Import the user record with source ID 5
     * @usage migrate-import beer_user --limit=50 --feedback=20
     *   Import 50 users and show process message every 20th record
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
    public function import(?string $migrationIds = null, array $options = [
        'all' => false,
        'tag' => self::REQ,
        'limit' => self::REQ,
        'feedback' => self::REQ,
        'idlist' => self::REQ,
        'update' => false,
        'force' => false,
        'execute-dependencies' => false,
        'timestamp' => false,
        'total' => false,
        'progress' => true,
    ])
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
            ])),
            'execute_dependencies' => $options['execute-dependencies'],
        ];


        // Include the file providing a 'migrate_prepare_row' hook implementation.
        require_once Path::join(DRUSH_BASE_PATH, 'includes/migrate_runner.inc');

        // Take it one group at a time, importing the migrations within each group.
        foreach ($list as $tag => $migrations) {
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

        // Initialize the Symfony Console progress bar, if case.
        $this->initProgressBar($migration, $userData['options']);

        $executable = new MigrateExecutable($migration, $this->getMigrateMessage(), $userData['options']);
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
     * @drupal-dependencies migrate
     *
     * @param string|null $migrationIds
     *   Comma-separated list of migration IDs.
     *
     * @option all Process all migrations.
     * @option tag A comma-separated list of migration tags to rollback
     * @option feedback Frequency of progress messages, in items processed
     * @option progress Show progress bar
     *
     * @usage migrate-rollback --all
     *   Perform all migrations
     * @usage migrate-rollback --tag=user,main_content
     *   Rollback all migrations tagged with user and main_content tags
     * @usage migrate-rollback classification,article
     *   Rollback imported terms and nodes
     *
     * @aliases mr,migrate-rollback
     * @topics docs:migrate
     * @validate-module-enabled migrate
     *
     * @throws \Exception
     *   When not enough options were provided.
     */
    public function rollback(?string $migrationIds = null, array $options = [
      'all' => false,
      'tag' => self::REQ,
      'feedback' => self::REQ,
      'progress' => true,
    ]): void
    {
        $tags = $options['tag'];
        $all = $options['all'];

        if (!$all && !$migrationIds && !$tags) {
            throw new \Exception(dt('You must specify --all, --tag, or one or more migration names separated by commas'));
        }

        if (!$list = $this->getMigrationList($migrationIds, $options['tag'])) {
            $this->logger()->error(dt('No migrations found.'));
        }

        $executableOptions = $options['feedback'] ? ['feedback' => $options['feedback']] : [];
        // Take it one tag at a time, rolling back the migrations within each tag.
        foreach ($list as $migrations) {
            // Rollback in reverse order.
            $migrations = array_reverse($migrations);
            foreach ($migrations as $migrationId => $migration) {
                $this->initProgressBar($migration, $options);
                $executable = new MigrateExecutable($migration, $this->getMigrateMessage(), $executableOptions);
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
     * @drupal-dependencies migrate
     *
     * @param string $migrationId
     *   The ID of migration to stop.
     *
     * @aliases mst,migrate-stop
     * @topics docs:migrate
     * @validate-module-enabled migrate
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
                $this->logger()->log('status', dt('Migration @id requested to stop', ['@id' => $migrationId]));
                break;
        }
    }

    /**
     * Reset an active migration's status to idle.
     *
     * @command migrate:reset-status
     *
     * @drupal-dependencies migrate
     *
     * @param string $migrationId
     *   The ID of migration to reset.
     *
     * @aliases mrs,migrate-reset-status
     * @topics docs:migrate
     * @validate-module-enabled migrate
     */
    public function resetStatus(string $migrationId): void
    {
        /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
        $migration = $this->getMigrationPluginManager()->createInstance($migrationId);
        if ($migration) {
            $status = $migration->getStatus();
            if ($status == MigrationInterface::STATUS_IDLE) {
                $this->logger()->warning(dt('Migration @id is already Idle', ['@id' => $migrationId]));
            } else {
                $migration->setStatus(MigrationInterface::STATUS_IDLE);
                $this->logger()->log('status', dt('Migration @id reset to Idle', ['@id' => $migrationId]));
            }
        } else {
            $this->logger()->error(dt('Migration @id does not exist', ['@id' => $migrationId]));
        }
    }

    /**
     * View any messages associated with a migration.
     *
     * @command migrate:messages
     *
     * @drupal-dependencies migrate
     *
     * @param string $migrationId
     *   The ID of the migration.
     *
     * @usage migrate-messages article
     *   Show all messages for the article migration
     * @aliases mmsg,migrate-messages
     * @topics docs:migrate
     * @validate-module-enabled migrate
     *
     * @field-labels
     *   level: Level
     *   message: Message
     *   hash: Source IDs hash
     * @default-fields level,message,hash
     *
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     *   Migration messages status formatted as table.
     */
    public function messages(string $migrationId): RowsOfFields
    {
        /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
        $migration = $this->getMigrationPluginManager()->createInstance($migrationId);
        $table = [];
        foreach ($migration->getIdMap()->getMessages() as $row) {
            $table[] = [
                'level' => $row->level,
                'message' => $row->message,
                'hash' => $row->source_ids_hash,
            ];
        }
        return new RowsOfFields($table);
    }

    /**
     * List the fields available for mapping in a source.
     *
     * @command migrate:fields-source
     *
     * @drupal-dependencies migrate
     *
     * @param string $migrationId
     *   The ID of the migration.
     *
     * @usage migrate-fields-source article
     *   List fields for the source in the article migration
     * @aliases mfs,migrate-fields-source
     * @topics docs:migrate
     * @validate-module-enabled migrate
     *
     * @field-labels
     *   machine_name: Field name
     *   description: Description
     * @default-fields machine_name,description
     *
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     *   Migration messages status formatted as table.
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
        $migrationIds = array_filter(array_map('trim', explode(',', $migrationIds)));
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
     * Initializes the command progress bar if possible.
     *
     * @param \Drupal\migrate\Plugin\MigrationInterface $migration
     *   The migration.
     * @param array $options
     *   The command options.
     */
    protected function initProgressBar(MigrationInterface $migration, array $options): void
    {
        // Cannot use the progress bar when:
        // - The --no-progress option is used,
        // - The --feedback option is used,
        // - The migration source skips count.
        if ($options['progress'] && !$options['feedback'] && empty($migration->getSourceConfiguration()['skip_count'])) {
            $this->commandProgress->initProgressBar($migration, $this->output());
        }
    }
}
