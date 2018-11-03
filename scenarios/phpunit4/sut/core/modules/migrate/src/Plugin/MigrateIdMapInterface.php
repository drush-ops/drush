<?php

namespace Drupal\migrate\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Row;

/**
 * Defines an interface for migrate ID mappings.
 *
 * Migrate ID mappings maintain a relation between source ID and destination ID
 * for audit and rollback purposes.
 */
interface MigrateIdMapInterface extends \Iterator, PluginInspectionInterface {

  /**
   * Codes reflecting the current status of a map row.
   */
  const STATUS_IMPORTED = 0;
  const STATUS_NEEDS_UPDATE = 1;
  const STATUS_IGNORED = 2;
  const STATUS_FAILED = 3;

  /**
   * Codes reflecting how to handle the destination item on rollback.
   */
  const ROLLBACK_DELETE = 0;
  const ROLLBACK_PRESERVE = 1;

  /**
   * Saves a mapping from the source identifiers to the destination identifiers.
   *
   * Called upon import of one row, we record a mapping from the source ID to
   * the destination ID. Also may be called, setting the third parameter to
   * NEEDS_UPDATE, to signal an existing record should be re-migrated.
   *
   * @param \Drupal\migrate\Row $row
   *   The raw source data. We use the ID map derived from the source object
   *   to get the source identifier values.
   * @param array $destination_id_values
   *   An array of destination identifier values.
   * @param int $status
   *   (optional) Status of the source row in the map. Defaults to
   *   self::STATUS_IMPORTED.
   * @param int $rollback_action
   *   (optional) How to handle the destination object on rollback. Defaults to
   *   self::ROLLBACK_DELETE.
   */
  public function saveIdMapping(Row $row, array $destination_id_values, $status = self::STATUS_IMPORTED, $rollback_action = self::ROLLBACK_DELETE);

  /**
   * Saves a message related to a source record in the migration message table.
   *
   * @param array $source_id_values
   *   The source identifier keyed values of the record, e.g. ['nid' => 5].
   * @param string $message
   *   The message to record.
   * @param int $level
   *   (optional) The message severity. Defaults to
   *   MigrationInterface::MESSAGE_ERROR.
   */
  public function saveMessage(array $source_id_values, $message, $level = MigrationInterface::MESSAGE_ERROR);

  /**
   * Retrieves an iterator over messages relate to source records.
   *
   * @param array $source_id_values
   *   (optional) The source identifier keyed values of the record, e.g.
   *   ['nid' => 5]. If empty (the default), all messages are retrieved.
   * @param int $level
   *   (optional) Message severity. If NULL (the default), retrieve messages of
   *   all severities.
   *
   * @return \Iterator
   *   Retrieves an iterator over the message rows.
   */
  public function getMessageIterator(array $source_id_values = [], $level = NULL);

  /**
   * Prepares to run a full update.
   *
   * Prepares this migration to run as an update - that is, in addition to
   * unmigrated content (source records not in the map table) being imported,
   * previously-migrated content will also be updated in place by marking all
   * previously-imported content as ready to be re-imported.
   */
  public function prepareUpdate();

  /**
   * Returns the number of processed items in the map.
   *
   * @return int
   *   The count of records in the map table.
   */
  public function processedCount();

  /**
   * Returns the number of imported items in the map.
   *
   * @return int
   *   The number of imported items.
   */
  public function importedCount();

  /**
   * Returns a count of items which are marked as needing update.
   *
   * @return int
   *   The number of items which need updating.
   */
  public function updateCount();

  /**
   * Returns the number of items that failed to import.
   *
   * @return int
   *   The number of items that errored out.
   */
  public function errorCount();

  /**
   * Returns the number of messages saved.
   *
   * @return int
   *   The number of messages.
   */
  public function messageCount();

  /**
   * Deletes the map and message entries for a given source record.
   *
   * @param array $source_id_values
   *   The source identifier keyed values of the record, e.g. ['nid' => 5].
   * @param bool $messages_only
   *   (optional) TRUE to only delete the migrate messages. Defaults to FALSE.
   */
  public function delete(array $source_id_values, $messages_only = FALSE);

  /**
   * Deletes the map and message table entries for a given destination row.
   *
   * @param array $destination_id_values
   *   The destination identifier key value pairs we should do the deletes for.
   */
  public function deleteDestination(array $destination_id_values);

  /**
   * Clears all messages from the map.
   */
  public function clearMessages();

  /**
   * Retrieves a row from the map table based on source identifier values.
   *
   * @param array $source_id_values
   *   The source identifier keyed values of the record, e.g. ['nid' => 5].
   *
   * @return array
   *   The raw row data as an associative array.
   */
  public function getRowBySource(array $source_id_values);

  /**
   * Retrieves a row by the destination identifiers.
   *
   * @param array $destination_id_values
   *   The destination identifier keyed values of the record, e.g. ['nid' => 5].
   *
   * @return array
   *   The row(s) of data.
   */
  public function getRowByDestination(array $destination_id_values);

  /**
   * Retrieves an array of map rows marked as needing update.
   *
   * @param int $count
   *   The maximum number of rows to return.
   *
   * @return array
   *   Array of map row objects that need updating.
   */
  public function getRowsNeedingUpdate($count);

  /**
   * Looks up the source identifier.
   *
   * Given a (possibly multi-field) destination identifier value, return the
   * (possibly multi-field) source identifier value mapped to it.
   *
   * @param array $destination_id_values
   *   The destination identifier keyed values of the record, e.g. ['nid' => 5].
   *
   * @return array
   *   The source identifier keyed values of the record, e.g. ['nid' => 5], or
   *   an empty array on failure.
   */
  public function lookupSourceId(array $destination_id_values);

  /**
   * Looks up the destination identifier corresponding to a source key.
   *
   * Given a (possibly multi-field) source identifier value, return the
   * (possibly multi-field) destination identifier value it is mapped to.
   *
   * @param array $source_id_values
   *   The source identifier keyed values of the record, e.g. ['nid' => 5].
   *
   * @return array
   *   The destination identifier values of the record, or empty on failure.
   *
   * @deprecated in Drupal 8.1.x, will be removed before Drupal 9.0.x. Use
   *   lookupDestinationIds() instead.
   *
   * @see https://www.drupal.org/node/2725809
   */
  public function lookupDestinationId(array $source_id_values);

  /**
   * Looks up the destination identifiers corresponding to a source key.
   *
   * This can look up a subset of source keys if only some are provided, and
   * will return all destination keys that match.
   *
   * @param array $source_id_values
   *   The source identifier keyed values of the records, e.g. ['nid' => 5].
   *   If unkeyed, the first count($source_id_values) keys will be assumed.
   *
   * @return array
   *   An array of arrays of destination identifier values.
   *
   * @throws \Drupal\migrate\MigrateException
   *   Thrown when $source_id_values contains unknown keys, or is the wrong
   *   length.
   */
  public function lookupDestinationIds(array $source_id_values);

  /**
   * Looks up the destination identifier currently being iterated.
   *
   * @return array
   *   The destination identifier values of the record, or NULL on failure.
   */
  public function currentDestination();

  /**
   * Looks up the source identifier(s) currently being iterated.
   *
   * @return array
   *   The source identifier values of the record, or NULL on failure.
   */
  public function currentSource();

  /**
   * Removes any persistent storage used by this map.
   *
   * For example, remove the map and message tables.
   */
  public function destroy();

  /**
   * Gets the qualified map table.
   *
   * @todo Remove this as this is SQL only and so doesn't belong to the interface.
   */
  public function getQualifiedMapTableName();

  /**
   * Sets the migrate message service.
   *
   * @param \Drupal\migrate\MigrateMessageInterface $message
   *   The migrate message service.
   */
  public function setMessage(MigrateMessageInterface $message);

  /**
   * Sets a specified record to be updated, if it exists.
   *
   * @param array $source_id_values
   *   The source identifier values of the record.
   */
  public function setUpdate(array $source_id_values);

}
