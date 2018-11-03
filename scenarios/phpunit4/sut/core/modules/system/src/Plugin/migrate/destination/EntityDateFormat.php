<?php

namespace Drupal\system\Plugin\migrate\destination;

use Drupal\Core\Entity\EntityInterface;
use Drupal\migrate\Plugin\migrate\destination\EntityConfigBase;

/**
 * @MigrateDestination(
 *   id = "entity:date_format"
 * )
 */
class EntityDateFormat extends EntityConfigBase {

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Datetime\DateFormatInterface $entity
   *   The date entity.
   */
  protected function updateEntityProperty(EntityInterface $entity, array $parents, $value) {
    if ($parents[0] == 'pattern') {
      $entity->setPattern($value);
    }
    else {
      parent::updateEntityProperty($entity, $parents, $value);
    }
  }

}
