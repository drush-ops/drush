<?php

namespace Drupal\user;

use Drupal\Core\Config\Entity\ConfigEntityStorage;

/**
 * Controller class for user roles.
 */
class RoleStorage extends ConfigEntityStorage implements RoleStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function isPermissionInRoles($permission, array $rids) {
    $has_permission = FALSE;
    foreach ($this->loadMultiple($rids) as $role) {
      /** @var \Drupal\user\RoleInterface $role */
      if ($role->isAdmin() || $role->hasPermission($permission)) {
        $has_permission = TRUE;
        break;
      }
    }

    return $has_permission;
  }

}
