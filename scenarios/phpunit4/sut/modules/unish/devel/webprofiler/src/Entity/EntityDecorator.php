<?php

namespace Drupal\webprofiler\Entity;

use Drupal\webprofiler\Decorator;

/**
 * Class EntityDecorator
 */
class EntityDecorator extends Decorator {

  /**
   * @var array
   */
  protected $entities;

  /**
   * @return mixed
   */
  public function getEntities() {
    return $this->entities;
  }
  
}
