<?php

namespace Drush\Role;

use Drupal\user\Entity\Role;

class Role8 extends Role7 {
  public function anonymousRole() {
    return \Drupal\user\RoleInterface::ANONYMOUS_ID;
  }

  public function role_create($role_machine_name, $role_human_readable_name = '') {
    // In D6 and D7, when we create a new role, the role
    // machine name is specified, and the numeric rid is
    // auto-assigned (next available id); in D8, when we
    // create a new role, we need to specify both the rid,
    // which is now the role machine name, and also a human-readable
    // role name.  If the client did not provide a human-readable
    // name, then we'll use the role machine name in its place.
    if (empty($role_human_readable_name)) {
      $role_human_readable_name = ucfirst($role_machine_name);
    }
    $role = new Role(array(
      'id' => $role_machine_name,
      'label' => $role_human_readable_name,
    ), 'user_role');
    $role->save();
    return $role;
  }

  public function getPerms() {
    $role = Role::load($this->rid);
    $perms = $role->getPermissions();
    // $perms = user_role_permissions(array($this->rid => $this->name));
    return $perms;
  }

  public function getAllModulePerms() {
    $perms = \Drupal::service('user.permissions')->getPermissions();
    return array_keys($perms);
  }

  public function getModulePerms($module) {
    $module_perms = array();
    $perms = \Drupal::service('user.permissions')->getPermissions();
    foreach ($perms as $name => $perm) {
      if ($perm['provider'] == $module) {
        $module_perms[] = $name;
      }
    }
    return $module_perms;
  }

  public function delete() {
    $role = Role::load($this->rid);
    $role->delete();
  }

  public function grant_permissions($perms) {
    return drush_op('user_role_grant_permissions', $this->rid, $perms);
  }

  public function revoke_permissions($perms) {
    return drush_op('user_role_revoke_permissions', $this->rid, $perms);
  }
}
