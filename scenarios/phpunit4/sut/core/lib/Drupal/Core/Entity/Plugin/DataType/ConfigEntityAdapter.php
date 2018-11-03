<?php

namespace Drupal\Core\Entity\Plugin\DataType;

use Drupal\Core\TypedData\Exception\MissingDataException;

/**
 * Enhances EntityAdapter for config entities.
 */
class ConfigEntityAdapter extends EntityAdapter {

  /**
   * The wrapped entity object.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function get($property_name) {
    if (!isset($this->entity)) {
      throw new MissingDataException("Unable to get property $property_name as no entity has been provided.");
    }
    return $this->getConfigTypedData()->get($property_name);
  }

  /**
   * {@inheritdoc}
   */
  public function set($property_name, $value, $notify = TRUE) {
    if (!isset($this->entity)) {
      throw new MissingDataException("Unable to set property $property_name as no entity has been provided.");
    }
    $this->entity->set($property_name, $value, $notify);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProperties($include_computed = FALSE) {
    if (!isset($this->entity)) {
      throw new MissingDataException('Unable to get properties as no entity has been provided.');
    }
    return $this->getConfigTypedData()->getProperties($include_computed);
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name) {
    if (isset($this->entity)) {
      // Let the entity know of any changes.
      $this->getConfigTypedData()->onChange($property_name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator() {
    if (isset($this->entity)) {
      return $this->getConfigTypedData()->getIterator();
    }
    return new \ArrayIterator([]);
  }

  /**
   * Gets the typed data manager.
   *
   * @return \Drupal\Core\Config\TypedConfigManagerInterface
   *   The typed data manager.
   */
  public function getTypedDataManager() {
    if (empty($this->typedDataManager)) {
      $this->typedDataManager = \Drupal::service('config.typed');
    }

    return $this->typedDataManager;
  }

  /**
   * {@inheritdoc}
   */
  public function applyDefaultValue($notify = TRUE) {
    // @todo Figure out what to do for this method, see
    //   https://www.drupal.org/project/drupal/issues/2945635.
    throw new \BadMethodCallException('Method not supported');
  }

  /**
   * Gets typed data for config entity.
   *
   * @return \Drupal\Core\TypedData\ComplexDataInterface
   *   The typed data.
   */
  protected function getConfigTypedData() {
    return $this->getTypedDataManager()->createFromNameAndData($this->entity->getConfigDependencyName(), $this->entity->toArray());
  }

}
