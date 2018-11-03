<?php

namespace Drupal\Core\Entity;

/**
 * A storage that supports revisionable entity types.
 */
interface RevisionableStorageInterface {

  /**
   * Creates a new revision starting off from the specified entity object.
   *
   * @param \Drupal\Core\Entity\EntityInterface|\Drupal\Core\Entity\RevisionableInterface $entity
   *   The revisionable entity object being modified.
   * @param bool $default
   *   (optional) Whether the new revision should be marked as default. Defaults
   *   to TRUE.
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\Core\Entity\RevisionableInterface
   *   A new entity revision object.
   */
  public function createRevision(RevisionableInterface $entity, $default = TRUE);

  /**
   * Loads a specific entity revision.
   *
   * @param int $revision_id
   *   The revision ID.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The specified entity revision or NULL if not found.
   */
  public function loadRevision($revision_id);

  /**
   * Loads multiple entity revisions.
   *
   * @param array $revision_ids
   *   An array of revision IDs to load.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of entity revisions keyed by their revision ID, or an empty
   *   array if none found.
   */
  public function loadMultipleRevisions(array $revision_ids);

  /**
   * Deletes a specific entity revision.
   *
   * A revision can only be deleted if it's not the currently active one.
   *
   * @param int $revision_id
   *   The revision ID.
   */
  public function deleteRevision($revision_id);

  /**
   * Returns the latest revision identifier for an entity.
   *
   * @param int|string $entity_id
   *   The entity identifier.
   *
   * @return int|string|null
   *   The latest revision identifier or NULL if no revision could be found.
   */
  public function getLatestRevisionId($entity_id);

}
