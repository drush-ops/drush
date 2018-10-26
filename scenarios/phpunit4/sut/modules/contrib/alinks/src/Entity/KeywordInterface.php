<?php

namespace Drupal\alinks\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Keyword entities.
 *
 * @ingroup alinks
 */
interface KeywordInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the Keyword name.
   *
   * @return string
   *   Name of the Keyword.
   */
  public function getName();

  /**
   * Sets the Keyword name.
   *
   * @param string $name
   *   The Keyword name.
   *
   * @return \Drupal\alinks\Entity\KeywordInterface
   *   The called Keyword entity.
   */
  public function setName($name);

  /**
   * Gets the Keyword creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Keyword.
   */
  public function getCreatedTime();

  /**
   * Sets the Keyword creation timestamp.
   *
   * @param int $timestamp
   *   The Keyword creation timestamp.
   *
   * @return \Drupal\alinks\Entity\KeywordInterface
   *   The called Keyword entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Keyword published status indicator.
   *
   * Unpublished Keyword are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Keyword is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Keyword.
   *
   * @param bool $published
   *   TRUE to set this Keyword to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\alinks\Entity\KeywordInterface
   *   The called Keyword entity.
   */
  public function setPublished($published);

}
