<?php

namespace Drupal\node;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\RevisionableEntityBundleInterface;

/**
 * Provides an interface defining a node type entity.
 */
interface NodeTypeInterface extends ConfigEntityInterface, RevisionableEntityBundleInterface {

  /**
   * Determines whether the node type is locked.
   *
   * @return string|false
   *   The module name that locks the type or FALSE.
   */
  public function isLocked();

  /**
   * Gets whether a new revision should be created by default.
   *
   * @return bool
   *   TRUE if a new revision should be created by default.
   *
   * @deprecated in Drupal 8.3.0 and will be removed before Drupal 9.0.0. Use
   *   Drupal\Core\Entity\RevisionableEntityBundleInterface::shouldCreateNewRevision()
   *   instead.
   */
  public function isNewRevision();

  /**
   * Sets whether a new revision should be created by default.
   *
   * @param bool $new_revision
   *   TRUE if a new revision should be created by default.
   */
  public function setNewRevision($new_revision);

  /**
   * Gets whether 'Submitted by' information should be shown.
   *
   * @return bool
   *   TRUE if the submitted by information should be shown.
   */
  public function displaySubmitted();

  /**
   * Sets whether 'Submitted by' information should be shown.
   *
   * @param bool $display_submitted
   *   TRUE if the submitted by information should be shown.
   */
  public function setDisplaySubmitted($display_submitted);

  /**
   * Gets the preview mode.
   *
   * @return int
   *   DRUPAL_DISABLED, DRUPAL_OPTIONAL or DRUPAL_REQUIRED.
   */
  public function getPreviewMode();

  /**
   * Sets the preview mode.
   *
   * @param int $preview_mode
   *   DRUPAL_DISABLED, DRUPAL_OPTIONAL or DRUPAL_REQUIRED.
   */
  public function setPreviewMode($preview_mode);

  /**
   * Gets the help information.
   *
   * @return string
   *   The help information of this node type.
   */
  public function getHelp();

  /**
   * Gets the description.
   *
   * @return string
   *   The description of this node type.
   */
  public function getDescription();

}
