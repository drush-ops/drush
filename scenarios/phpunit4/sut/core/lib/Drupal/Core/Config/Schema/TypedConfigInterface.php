<?php

namespace Drupal\Core\Config\Schema;

use Drupal\Core\TypedData\TraversableTypedDataInterface;

/**
 * Interface for a typed configuration object that contains multiple elements.
 *
 * A list of typed configuration contains any number of items whose type
 * will depend on the configuration schema but also on the configuration
 * data being parsed.
 *
 * When implementing this interface which extends Traversable, make sure to list
 * IteratorAggregate or Iterator before this interface in the implements clause.
 */
interface TypedConfigInterface extends TraversableTypedDataInterface {

  /**
   * Determines whether the data structure is empty.
   *
   * @return bool
   *   TRUE if the data structure is empty, FALSE otherwise.
   */
  public function isEmpty();

  /**
   * Gets an array of contained elements.
   *
   * @return array
   *   Array of \Drupal\Core\TypedData\TypedDataInterface objects.
   */
  public function getElements();

  /**
   * Gets a contained typed configuration element.
   *
   * @param $name
   *   The name of the property to get; e.g., 'title' or 'name'. Nested
   *   elements can be get using multiple dot delimited names, for example,
   *   'page.front'.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   The property object.
   *
   * @throws \InvalidArgumentException
   *   If an invalid property name is given.
   */
  public function get($name);

  /**
   * Returns an array of all property values.
   *
   * @return array
   *   An array of property values, keyed by property name.
   */
  public function toArray();

}
