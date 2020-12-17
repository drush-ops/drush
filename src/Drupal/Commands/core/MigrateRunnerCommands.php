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
     * Migrate message service.
     *
     * @var \Drupal\migrate\MigrateMessageInterface
     */
    protected $migrateMessage;

    /**
     * MigrateToolsCommands constructor.
     *
     * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
     *   Date formatter service.
     * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
     *   The key-value factory service.
     */
    public function __construct(DateFormatter $date_formatter, KeyValueFactoryInterface $key_value_factory)
    {
        parent::__construct();
        $this->dateFormatter = $date_formatter;
        $this->keyValue = $key_value_factory->get('migrate_last_imported');
    }

    /**
     * List all migrations with current status.
     *
     * @command migrate:status
     *
     * @drupal-dependencies migrate
     * @param string|null $migration_ids
     *   Restrict to a comma-separated list of migrations. Optional.
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
    public function status(?string $migration_ids = null, array $options = [
      'tag' => null,
      'names-only' => null,
    ]): RowsOfFields
    {
        $names_only = $options['names-only'];
        $list = $this->getMigrationList($migration_ids, $options);

        $table = [];
        // Take it one tag at a time, listing the migrations within each tag.
        foreach ($list as $tag => $migrations) {
            if ($tag) {
                $table[] = $this->prepareTableRow(['id' => dt('Tag: @name', ['@name' => $tag])], $names_only);
            }
            ksort($migrations);
            foreach ($migrations as $migration_id => $migration) {
                $printed_migration_id = ($tag ? ' ' : '') . $migration_id;
                if ($names_only) {
                    $table[] = $this->prepareTableRow(['id' => $printed_migration_id], $names_only);
                    // No future processing is needed. We're done with this row.
                    continue;
                }

                try {
                    $map = $migration->getIdMap();
                    $imported = $map->importedCount();
                    $source_plugin = $migration->getSourcePlugin();
                } catch (\Exception $e) {
                    $arguments = ['@migration' => $migration_id, '@message' => $e->getMessage()];
                    $this->logger()->error(dt('Failure retrieving information on @migration: @message', $arguments));
                    continue;
                }

                try {
                    $source_rows = $source_plugin->count();
                    // -1 indicates uncountable sources.
                    if ($source_rows == -1) {
                        $source_rows = dt('N/A');
                        $unprocessed = dt('N/A');
                    } else {
                        $unprocessed = $source_rows - $map->processedCount();
                        if ($source_rows > 0 && $imported > 0) {
                            $imported .= ' (' . round(($imported / $source_rows) * 100, 1) . '%)';
                        }
                    }
                    $needing_update = count($map->getRowsNeedingUpdate($map->processedCount()));
                } catch (\Exception $e) {
                    $arguments = ['@migration' => $migration_id, '@message' => $e->getMessage()];
                    $this->logger()->error(dt('Could not retrieve source count from @migration: @message', $arguments));
                    $source_rows = dt('N/A');
                    $unprocessed = dt('N/A');
                    $needing_update = dt('N/A');
                }

                $status = $migration->getStatusLabel();
                if ($last_imported = $this->keyValue->get($migration->id(), '')) {
                    $last_imported = $this->dateFormatter->format(
                        $last_imported / 1000,
                        'custom',
                        'Y-m-d H:i:s'
                    );
                }

                $table[] = [
                    'id' => $printed_migration_id,
                    'status' => $status,
                    'total' => $source_rows,
                    'imported' => $imported,
                    'needing_update' => $needing_update,
                    'unprocessed' => $unprocessed,
                    'last_imported' => $last_imported,
                ];
            }

            // Add an empty row after a tag group.
            if ($tag) {
                $table[] = $this->prepareTableRow([], $names_only);
            }
        }

        return new RowsOfFields($table);
    }

    /**
     * Prepares a table row for migrate status.
     *
     * @param array $row
     *   The row to be prepared.
     * @param true|null $names_only
     *   If to output only the migration IDs.
     *
     * @return array
     *   The prepared row.
     */
    protected function prepareTableRow(array $row, ?bool $names_only): array
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
        if (!$names_only) {
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
     * @param string|null $migration_ids
     *   Comma-separated list of migration IDs.
     * @option all Process all migrations.
     * @option tag A comma-separated list of migration tags to import
     * @option limit Limit on the number of items to process in each migration
     * @option feedback Frequency of progress messages, in items processed
     * @option idlist Comma-separated list of IDs to import. As an ID may have more than one columns, concatenate the columns with the colon ':' separator
     * @option update In addition to processing unprocessed items from the source, update previously-imported items with the current data
     * @option force Force an operation to run, even if all dependencies are not satisfied
     * @option execute-dependencies Execute all dependent migrations first.
     * @option timestamp Show progress ending timestamp in progress messages.
     * @option total Show total processed item number in progress messages.
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
     * @aliases mim,migrate-import
     * @topics docs:migrate
     * @validate-module-enabled migrate
     *
     * @throws \Exception
     *   When not enough options were provided or no migration was found.
     */
    public function import(?string $migration_ids = null, array $options = [
        'all' => null,
        'tag' => null,
        'limit' => null,
        'feedback' => null,
        'idlist' => null,
        'update' => null,
        'force' => null,
        'execute-dependencies' => null,
        'timestamp' => null,
        'total' => null,
    ])
    {
        $tags = $options['tag'];
        $all = $options['all'];

        if (!$all && !$migration_ids && !$tags) {
            throw new \Exception(dt('You must specify --all, --tag or one or more migration names separated by commas'));
        }

        if (!$list = $this->getMigrationList($migration_ids)) {
            throw new \Exception(dt('No migrations found.'));
        }

        $user_data = [
            'options' => array_intersect_key($options, array_flip([
                'limit',
                'feedback',
                'idlist',
                'update',
                'force',
                'timestamp',
                'total',
            ])),
            'execute_dependencies' => $options['execute-dependencies'],
        ];


        // Include the file providing a 'migrate_prepare_row' hook implementation.
        require_once Path::join(DRUSH_BASE_PATH, 'includes/migrate_runner.inc');

        // Take it one group at a time, importing the migrations within each group.
        foreach ($list as $tag => $migrations) {
            array_walk($migrations, [static::class, 'executeMigration'], $user_data);
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
     * @param string $migration_id
     *   The migration ID (not used, just an artifact of array_walk()).
     * @param array $user_data
     *   Additional data passed to the callback.
     *
     * @throws \Exception
     *   If there are failed migrations.
     */
    protected function executeMigration(MigrationInterface $migration, string $migration_id, array $user_data): void
    {
        static $executed_migrations = [];

        if ($user_data['execute_dependencies']) {
            $dependencies = $migration->getMigrationDependencies()['required'];
            // Remove already executed migrations.
            $dependencies = array_diff($dependencies, $executed_migrations);
            if ($dependencies) {
                $required_migrations = $this->getMigrationPluginManager()->createInstances($dependencies);
                array_walk($required_migrations, [static::class, __FUNCTION__], $user_data);
            }
        }
        if (!empty($user_data['options']['force'])) {
            $migration->set('requirements', []);
        }
        if (!empty($user_data['options']['update'])) {
            if (empty($user_data['options']['idlist'])) {
                $migration->getIdMap()->prepareUpdate();
            } else {
                $source_id_values_list = MigrateUtils::parseIdList($user_data['options']['idlist']);
                $keys = array_keys($migration->getSourcePlugin()->getIds());
                foreach ($source_id_values_list as $source_id_values) {
                    $migration->getIdMap()->setUpdate(array_combine($keys, $source_id_values));
                }
            }
        }
        $executable = new MigrateExecutable($migration, $this->getMigrateMessage(), $user_data['options']);
        // drush_op() provides --simulate support.
        drush_op([$executable, 'import']);
        if ($count = $executable->getFailedCount()) {
            // Nudge Drush to use a non-zero exit code.
            throw new \Exception(dt('!name migration: !count failed.', ['!name' => $migration_id, '!count' => $count]));
        }

        // Keep track of executed migrations.
        $executed_migrations[] = $migration_id;
    }

    /**
     * Rollback one or more migrations.
     *
     * @command migrate:rollback
     *
     * @drupal-dependencies migrate
     * @param string|null $migration_ids
     *   Comma-separated list of migration IDs.
     * @option all Process all migrations.
     * @option tag A comma-separated list of migration tags to rollback
     * @option feedback Frequency of progress messages, in items processed
     * @usage migrate-rollback --all
     *   Perform all migrations
     * @usage migrate-rollback --tag=user,main_content
     *   Rollback all migrations tagged with user and main_content tags
     * @usage migrate-rollback classification,article
     *   Rollback imported terms and nodes
     * @aliases mr,migrate-rollback
     * @topics docs:migrate
     * @validate-module-enabled migrate
     *
     * @throws \Exception
     *   When not enough options were provided.
     */
    public function rollback(?string $migration_ids = null, array $options = [
      'all' => null,
      'tag' => null,
      'feedback' => null
    ]): void
    {
        $tags = $options['tag'];
        $all = $options['all'];

        if (!$all && !$migration_ids && !$tags) {
            throw new \Exception(dt('You must specify --all, --tag, or one or more migration names separated by commas'));
        }

        if (!$list = $this->getMigrationList($migration_ids, $options)) {
            $this->logger()->error(dt('No migrations found.'));
        }

        $executable_options = $options['feedback'] ? ['feedback' => $options['feedback']] : [];
        // Take it one tag at a time, rolling back the migrations within each tag.
        foreach ($list as $group_id => $migrations) {
            // Rollback in reverse order.
            $migrations = array_reverse($migrations);
            foreach ($migrations as $migration_id => $migration) {
                $executable = new MigrateExecutable($migration, $this->getMigrateMessage(), $executable_options);
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
     * @param string $migration_id
     *   The ID of migration to stop.
     * @aliases mst,migrate-stop
     * @topics docs:migrate
     * @validate-module-enabled migrate
     */
    public function stop(string $migration_id): void
    {
        /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
        $migration = $this->getMigrationPluginManager()->createInstance($migration_id);
        switch ($migration->getStatus()) {
            case MigrationInterface::STATUS_IDLE:
                $this->logger()->warning(dt('Migration @id is idle', ['@id' => $migration_id]));
                break;
            case MigrationInterface::STATUS_DISABLED:
                $this->logger()->warning(dt('Migration @id is disabled', ['@id' => $migration_id]));
                break;
            case MigrationInterface::STATUS_STOPPING:
                $this->logger()->warning(dt('Migration @id is already stopping', ['@id' => $migration_id]));
                break;
            default:
                $migration->interruptMigration(MigrationInterface::RESULT_STOPPED);
                $this->logger()->log('status', dt('Migration @id requested to stop', ['@id' => $migration_id]));
                break;
        }
    }

    /**
     * Reset an active migration's status to idle.
     *
     * @command migrate:reset-status
     *
     * @drupal-dependencies migrate
     * @param string $migration_id
     *   The ID of migration to reset.
     * @aliases mrs,migrate-reset-status
     * @topics docs:migrate
     * @validate-module-enabled migrate
     */
    public function resetStatus(string $migration_id): void
    {
        /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
        $migration = $this->getMigrationPluginManager()->createInstance($migration_id);
        if ($migration) {
            $status = $migration->getStatus();
            if ($status == MigrationInterface::STATUS_IDLE) {
                $this->logger()->warning(dt('Migration @id is already Idle', ['@id' => $migration_id]));
            } else {
                $migration->setStatus(MigrationInterface::STATUS_IDLE);
                $this->logger()->log('status', dt('Migration @id reset to Idle', ['@id' => $migration_id]));
            }
        } else {
            $this->logger()->error(dt('Migration @id does not exist', ['@id' => $migration_id]));
        }
    }

    /**
     * View any messages associated with a migration.
     *
     * @command migrate:messages
     *
     * @drupal-dependencies migrate
     * @param string $migration_id
     *   The ID of the migration.
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
    public function messages(string $migration_id): RowsOfFields
    {
        /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
        $migration = $this->getMigrationPluginManager()->createInstance($migration_id);
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
     * @param string $migration_id
     *   The ID of the migration.
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
    public function fieldsSource(string $migration_id): RowsOfFields
    {
        /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
        $migration = $this->getMigrationPluginManager()->createInstance($migration_id);
        $source = $migration->getSourcePlugin();
        $table = [];
        foreach ($source->fields() as $machine_name => $description) {
            $table[] = [
                'machine_name' => $machine_name,
                'description' => strip_tags($description),
            ];
        }
        return new RowsOfFields($table);
    }

    /**
     * Retrieves a list of active migrations.
     *
     * @param string|null $migration_ids A comma-separated list of migration IDs. If omitted, will return all
     *   migrations.
     * @param array $options (optional) Drush command passed options. Defaults to ['tag' => null, 'names-only' => null].
     *
     * @return \Drupal\migrate\Plugin\MigrationInterface[][] An array keyed by migration tag, each value containing an
     *   array of migrations or an empty array if no migrations match the input criteria.
     */
    protected function getMigrationList(?string $migration_ids, array $options = [
      'tag' => null,
      'names-only' => null,
    ]): array
    {
        $tags = $options['tag'];
        $migration_ids = array_filter(array_map('trim', explode(',', $migration_ids)));
        $migrations = $this->getMigrationPluginManager()->createInstances($migration_ids);

        // Check for invalid migration IDs.
        if ($invalid_migrations = array_diff_key(array_flip($migration_ids), $migrations)) {
            throw new \InvalidArgumentException('Invalid migration IDs: ' . implode(', ', array_flip($invalid_migrations)));
        }

        foreach ($migrations as $migration_id => $migration) {
            try {
                $source_plugin = $migration->getSourcePlugin();
                if ($source_plugin instanceof RequirementsInterface) {
                    $source_plugin->checkRequirements();
                }
            } catch (RequirementsException $exception) {
                $this->logger()->debug("Migration '{$migration_id}' is skipped as its source plugin has missed requirements: " . $exception->getRequirementsString());
                unset($migrations[$migration_id]);
            }
        }

        // If --tag was not passed, don't group on tags, use a single empty tag.
        if ($tags === null) {
            return [null => $migrations];
        }

        if ($tags !== true) {
            $tags = array_filter(array_map('trim', explode(',', $tags)));
        }

        $list = [];
        foreach ($migrations as $migration_id => $migration) {
            $migration_tags = (array)$migration->getMigrationTags();
            if ($tags !== true) {
                $common_tags = array_intersect($tags, $migration_tags);
                if ($tags && !$common_tags) {
                    // Skip if this a list of tags was passed and this migration is not tagged
                    // with any of the requested tag.
                    continue;
                }
                $grouping_tags = array_intersect($tags, $migration_tags);
            } else {
                $grouping_tags = $migration_tags;
            }

            foreach ($grouping_tags as $tag) {
                $list[$tag][$migration_id] = $migration;
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
     * @todo This service cannot be injected as the 'migrate' module might not be enabled and will throw the following
     *   exception:
     *   Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     *   The service "migrate_runner.commands" has a dependency on a non-existent service "plugin.manager.migration".
     *   Unfortunately, we cannot avoid the class instantiation, via an annotation (as @validate-module-enabled for
     *   methods), if a specific module is not installed. Open a followup to tackle this issue.
     */
    protected function getMigrationPluginManager(): MigrationPluginManagerInterface
    {
        if (!isset($this->migrationPluginManager)) {
            $this->migrationPluginManager = \Drupal::service('plugin.manager.migration');
        }
        return $this->migrationPluginManager;
    }
}
