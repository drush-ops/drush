<?php

namespace Drupal\webprofiler\Entity\Decorators\Config;

use Drupal\user\RoleStorageInterface;

/**
 * Class RoleStorageDecorator
 */
class RoleStorageDecorator extends ConfigEntityStorageDecorator implements RoleStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function isPermissionInRoles($permission, array $rids) {
    return $this->getOriginalObject()->isPermissionInRoles($permission, $rids);
  }

}
