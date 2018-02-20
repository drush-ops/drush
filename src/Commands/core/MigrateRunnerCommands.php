<?php

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\migrate\Plugin\MigrationInterface;
use Drush\Drupal\Migrate\MigrateMessage;
use Drush\Commands\DrushCommands;
use Drush\Drupal\Migrate\MigrateExecutable;

class MigrateRunnerCommands extends DrushCommands
{
    /**
     * Migrate message service.
     *
     * @var \Drupal\migrate\MigrateMessageInterface
     */
    protected $migrateMessage;

    /**
     * List all migrations with current status.
     *
     * @command migrate:status
     *
     * @param string $migration_ids
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
     * @bootstrap max
     *
     * @field-labels
     *   id: Migration ID
     *   status: Status
     *   total: Total
     *   imported: Imported
     *   unprocessed: Unprocessed
     *   last_imported: Last Imported
     * @default-fields id,status,total,imported,unprocessed,last_imported
     *
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     *   Migrations status formatted as table.
     */
    public function status($migration_ids = '', $options = ['tag' => NULL, 'names-only' => NULL])
    {
        $key_value = \Drupal::keyValue('migrate_last_imported');
        $date_formatter = \Drupal::service('date.formatter');

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
                    // No future processing is needed. We're don with this row.
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
                } catch (\Exception $e) {
                    $arguments = ['@migration' => $migration_id, '@message' => $e->getMessage()];
                    $this->logger()->error(dt('Could not retrieve source count from @migration: @message', $arguments));
                    $source_rows = dt('N/A');
                    $unprocessed = dt('N/A');
                }

                $status = $migration->getStatusLabel();
                if ($last_imported = $key_value->get($migration->id(), '')) {
                    $last_imported = $date_formatter->format(
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
     * @param null|true $names_only
     *   If to output only the migration IDs.
     *
     * @return array
     *   The prepared row.
     */
    protected function prepareTableRow(array $row, $names_only)
    {
        $defaults = array_fill_keys(['id', 'status', 'total', 'imported', 'unprocessed', 'last_imported'], NULL);
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
     * @param string $migration_ids
     *   Comma-separated list of migration IDs.
     * @option all Process all migrations.
     * @option tag A comma-separated list of migration tags to import
     * @option limit Limit on the number of items to process in each migration
     * @option feedback Frequency of progress messages, in items processed
     * @option idlist Comma-separated list of IDs to import
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
     * @bootstrap full
     *
     * @throws \Exception
     *   When not enough options were provided.
     */
    public function import($migration_ids = '', $options = [
        'all' => NULL,
        'tag' => NULL,
        'limit' => NULL,
        'feedback' => NULL,
        'idlist' => NULL,
        'update' => NULL,
        'force' => NULL,
        'execute-dependencies' => NULL,
        'timestamp' => NULL,
        'total' => NULL,
    ])
    {
        $tags = $options['tag'];
        $all = $options['all'];

        if (!$all && !$migration_ids && !$tags) {
            throw new \Exception(dt('You must specify --all, --tag or one or more migration names separated by commas'));
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

        if (!$list = $this->getMigrationList($migration_ids)) {
            $this->logger()->error(dt('No migrations found.'));
        }

        // Take it one group at a time, importing the migrations within each group.
        foreach ($list as $tag => $migrations) {
            array_walk($migrations, [static::class, 'executeMigration'], $user_data);
        }
    }

    /**
     * Rollback one or more migrations.
     *
     * @command migrate:rollback
     *
     * @param string $migration_ids
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
     * @bootstrap max
     *
     * @throws \Exception
     *   When not enough options were provided.
     */
    public function rollback($migration_ids = '', $options = [
        'all' => NULL,
        'tag' => NULL,
        'feedback' => NULL
    ])
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
                drush_op(array($executable, 'rollback'));
            }
        }
    }

    /**
     * Stop an active migration operation.
     *
     * @command migrate:stop
     *
     * @param string $migration_id
     *   The ID of migration to stop.
     * @aliases mst,migrate-stop
     * @bootstrap max
     */
    public function stop($migration_id)
    {
        /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
        $migration = \Drupal::service('plugin.manager.migration')->createInstance($migration_id);
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
     * @param string $migration_id
     *   The ID of migration to reset.
     * @aliases mrs,migrate-reset-status
     * @bootstrap max
     */
    public function resetStatus($migration_id)
    {
        /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
        $migration = \Drupal::service('plugin.manager.migration')->createInstance($migration_id);
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
     * @param string $migration_id
     *   The ID of the migration.
     * @usage migrate-messages article
     *   Show all messages for the article migration
     * @aliases mmsg,migrate-messages
     * @bootstrap max
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
    public function messages($migration_id)
    {
        /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
        $migration = \Drupal::service('plugin.manager.migration')->createInstance($migration_id);
        $table = [];
        foreach ($migration->getIdMap()->getMessageIterator() as $row) {
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
     * @param string $migration_id
     *   The ID of the migration.
     * @usage migrate-fields-source article
     *   List fields for the source in the article migration
     * @aliases mfs,migrate-fields-source
     * @bootstrap max
     *
     * @field-labels
     *   machine_name: Field name
     *   description: Description
     * @default-fields machine_name,description
     *
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     *   Migration messages status formatted as table.
     */
    public function fieldsSource($migration_id)
    {
        /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
        $migration = \Drupal::service('plugin.manager.migration')->createInstance($migration_id);
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
     * @param string $migration_ids (optional) A comma-separated list of migration IDs. If omitted, will return all
     *   migrations.
     * @param array $options (optional) Drush command passed options. Defaults to ['tag' => NULL, 'names-only' => NULL].
     *
     * @return \Drupal\migrate\Plugin\MigrationInterface[][] An array keyed by migration tag, each value containing an
     *   array of migrations or an empty array if no migrations match the input criteria.
     */
    protected function getMigrationList($migration_ids = '', $options = ['tag' => NULL, 'names-only' => NULL])
    {
        $tags = $options['tag'];
        $migration_ids = array_filter(array_map('trim', explode(',', $migration_ids)));

        /** @var \Drupal\migrate\Plugin\MigrationInterface[] $migrations */
        $migrations = \Drupal::service('plugin.manager.migration')
            ->createInstances($migration_ids);

        // If --tag was not passed, don't group on tags, use a single empty tag.
        if ($tags === NULL) {
            return [NULL => $migrations];
        }

        if ($tags !== TRUE) {
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
     * Executes a single migration.
     *
     * If the --execute-dependencies option was given, the migration's
     * dependencies will also be executed first.
     *
     * @param \Drupal\migrate\Plugin\MigrationInterface $migration
     *   The migration to execute.
     * @param string $migration_id
     *   The migration ID (not used, just an artifact of array_walk()).
     * @param array $data
     *   Additional data passed to the callback.
     */
    protected function executeMigration(MigrationInterface $migration, $migration_id, array $data = [])
    {
        if ($data['execute_dependencies']) {
            $dependencies = $migration->getMigrationDependencies();
            $required_ids = isset($dependencies['required']) ? $dependencies['required'] : NULL;
            if ($required_ids) {
                $required_migrations = \Drupal::service('plugin.manager.migration')
                    ->createInstances($required_ids);
                $data['is_dependency'] = TRUE;
                array_walk($required_migrations, [static::class, __FUNCTION__], $data);
            }
        }
        if (!empty($data['options']['force'])) {
            $migration->set('requirements', []);
        }
        if (!empty($data['options']['update'])) {
            $migration->getIdMap()->prepareUpdate();
        }
        $executable = new MigrateExecutable($migration, $this->getMigrateMessage(), $data['options']);
        // drush_op() provides --simulate support.
        drush_op([$executable, 'import']);
    }

    /**
     * Returns the migrate message logger.
     *
     * @return \Drupal\migrate\MigrateMessageInterface
     *   The migrate message logger.
     */
    protected function getMigrateMessage()
    {
        if (!isset($this->migrateMessage)) {
            $this->migrateMessage = new MigrateMessage($this->logger());
        }
        return $this->migrateMessage;
    }
}
