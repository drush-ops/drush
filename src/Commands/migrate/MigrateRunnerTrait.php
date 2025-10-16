<?php

declare(strict_types=1);

namespace Drush\Commands\migrate;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate\Plugin\RequirementsInterface;
use Drush\Drupal\Migrate\MigrateMessage;
use Drush\Utils\StringUtils;

trait MigrateRunnerTrait
{
    protected ?MigrationPluginManagerInterface $migrationPluginManager = null;
    protected KeyValueStoreInterface $keyValue;
    private MigrateMessage $migrateMessage;

    /**
     * Provide a migration plugin manager.
     */
    public function setMigrationPluginManager(MigrationPluginManagerInterface $migrationPluginManager)
    {
        $this->migrationPluginManager = $migrationPluginManager;
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
            // null indicates uncountable/unretrievable sources.
            if (is_null($sourceRowsCount)) {
                return null;
            }
            return $sourceRowsCount;
        } catch (\Exception $exception) {
            $arguments = [
              '@migration' => $migration->id(),
              '@message' => $exception->getMessage(),
            ];
            $this->logger->error(dt('Could not retrieve source count from @migration: @message', $arguments));
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
            $this->logger->error(dt('Failure retrieving information on @migration: @message', $arguments));
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
    protected function getMigrationLastImportedTime(MigrationInterface $migration, DateFormatterInterface $dateFormatter): string
    {
        if ($lastImported = $this->keyValue->get($migration->id(), '')) {
            $lastImported = $dateFormatter->format(round($lastImported / 1000), 'custom', 'Y-m-d H:i:s');
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
                $this->logger->debug("Migration '$migrationId' is skipped as its source plugin has missed requirements: {$exception->getRequirementsString()}");
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
            $this->migrateMessage = new MigrateMessage($this->logger);
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
}
