<?php

namespace Drupal\taxonomy;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;

/**
 * Provides an interface defining a taxonomy term entity.
 */
interface TermInterface extends ContentEntityInterface, EntityChangedInterface, EntityPublishedInterface {

  /**
   * Gets the term's description.
   *
   * @return string
   *   The term description.
   */
  public function getDescription();

  /**
   * Sets the term's description.
   *
   * @param string $description
   *   The term's description.
   *
   * @return $this
   */
  public function setDescription($description);

  /**
   * Gets the text format name for the term's description.
   *
   * @return string
   *   The text format name.
   */
  public function getFormat();

  /**
   * Sets the text format name for the term's description.
   *
   * @param string $format
   *   The term's description text format.
   *
   * @return $this
   */
  public function setFormat($format);

  /**
   * Gets the name of the term.
   *
   * @return string
   *   The name of the term.
   */
  public function getName();

  /**
   * Sets the name of the term.
   *
   * @param string $name
   *   The term's name.
   *
   * @return $this
   */
  public function setName($name);

  /**
   * Gets the weight of this term.
   *
   * @return int
   *   The weight of the term.
   */
  public function getWeight();

  /**
   * Gets the weight of this term.
   *
   * @param int $weight
   *   The term's weight.
   *
   * @return $this
   */
  public function setWeight($weight);

  /**
   * Get the taxonomy vocabulary id this term belongs to.
   *
   * @return string
   *   The id of the vocabulary.
   *
   * @deprecated Scheduled for removal before Drupal 9.0.0. Use
   *   TermInterface::bundle() instead.
   */
  public function getVocabularyId();

}
