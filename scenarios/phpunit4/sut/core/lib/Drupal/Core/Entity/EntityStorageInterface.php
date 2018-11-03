<?php

namespace Drupal\Core\Entity;

/**
 * Defines the interface for entity storage classes.
 *
 * For common default implementations, see
 * \Drupal\Core\Entity\Sql\SqlContentEntityStorage for content entities and
 * \Drupal\Core\Config\Entity\ConfigEntityStorage for config entities. Those
 * implementations are used by default when the @ContentEntityType or
 * @ConfigEntityType annotations are used.
 *
 * @ingroup entity_api
 */
interface EntityStorageInterface {

  /**
   * Load the most recent version of an entity's field data.
   */
  const FIELD_LOAD_CURRENT = 'FIELD_LOAD_CURRENT';

  /**
   * Load the version of an entity's field data specified in the entity.
   */
  const FIELD_LOAD_REVISION = 'FIELD_LOAD_REVISION';

  /**
   * Resets the internal, static entity cache.
   *
   * @param $ids
   *   (optional) If specified, the cache is reset for the entities with the
   *   given ids only.
   */
  public function resetCache(array $ids = NULL);

  /**
   * Loads one or more entities.
   *
   * @param $ids
   *   An array of entity IDs, or NULL to load all entities.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of entity objects indexed by their IDs. Returns an empty array
   *   if no matching entities are found.
   */
  public function loadMultiple(array $ids = NULL);

  /**
   * Loads one entity.
   *
   * @param mixed $id
   *   The ID of the entity to load.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   An entity object. NULL if no matching entity is found.
   */
  public function load($id);

  /**
   * Loads an unchanged entity from the database.
   *
   * @param mixed $id
   *   The ID of the entity to load.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The unchanged entity, or NULL if the entity cannot be loaded.
   *
   * @todo Remove this method once we have a reliable way to retrieve the
   *   unchanged entity from the entity object.
   */
  public function loadUnchanged($id);

  /**
   * Load a specific entity revision.
   *
   * @param int|string $revision_id
   *   The revision id.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The specified entity revision or NULL if not found.
   *
   * @todo Deprecated in Drupal 8.5.0 and will be removed before Drupal 9.0.0.
   *   Use \Drupal\Core\Entity\RevisionableStorageInterface instead.
   *
   * @see https://www.drupal.org/node/2926958
   * @see https://www.drupal.org/node/2927226
   */
  public function loadRevision($revision_id);

  /**
   * Delete a specific entity revision.
   *
   * A revision can only be deleted if it's not the currently active one.
   *
   * @param int $revision_id
   *   The revision id.
   *
   * @todo Deprecated in Drupal 8.5.0 and will be removed before Drupal 9.0.0.
   *   Use \Drupal\Core\Entity\RevisionableStorageInterface instead.
   *
   * @see https://www.drupal.org/node/2926958
   * @see https://www.drupal.org/node/2927226
   */
  public function deleteRevision($revision_id);

  /**
   * Load entities by their property values.
   *
   * @param array $values
   *   An associative array where the keys are the property names and the
   *   values are the values those properties must have.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of entity objects indexed by their ids.
   */
  public function loadByProperties(array $values = []);

  /**
   * Constructs a new entity object, without permanently saving it.
   *
   * @param array $values
   *   (optional) An array of values to set, keyed by property name. If the
   *   entity type has bundles, the bundle key has to be specified.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   A new entity object.
   */
  public function create(array $values = []);

  /**
   * Deletes permanently saved entities.
   *
   * @param array $entities
   *   An array of entity objects to delete.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   In case of failures, an exception is thrown.
   */
  public function delete(array $entities);

  /**
   * Saves the entity permanently.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to save.
   *
   * @return
   *   SAVED_NEW or SAVED_UPDATED is returned depending on the operation
   *   performed.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   In case of failures, an exception is thrown.
   */
  public function save(EntityInterface $entity);

  /**
   * Determines if the storage contains any data.
   *
   * @return bool
   *   TRUE if the storage contains data, FALSE if not.
   */
  public function hasData();

  /**
   * Gets an entity query instance.
   *
   * @param string $conjunction
   *   (optional) The logical operator for the query, either:
   *   - AND: all of the conditions on the query need to match.
   *   - OR: at least one of the conditions on the query need to match.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The query instance.
   *
   * @see \Drupal\Core\Entity\EntityStorageBase::getQueryServiceName()
   */
  public function getQuery($conjunction = 'AND');

  /**
   * Gets an aggregated query instance.
   *
   * @param string $conjunction
   *   (optional) The logical operator for the query, either:
   *   - AND: all of the conditions on the query need to match.
   *   - OR: at least one of the conditions on the query need to match.
   *
   * @return \Drupal\Core\Entity\Query\QueryAggregateInterface
   *   The aggregated query object that can query the given entity type.
   *
   * @see \Drupal\Core\Entity\EntityStorageBase::getQueryServiceName()
   */
  public function getAggregateQuery($conjunction = 'AND');

  /**
   * Gets the entity type ID.
   *
   * @return string
   *   The entity type ID.
   */
  public function getEntityTypeId();

  /**
   * Gets the entity type definition.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   Entity type definition.
   */
  public function getEntityType();

}
