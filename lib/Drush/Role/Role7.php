<?php

namespace Drush\Role;

class Role7 extends RoleBase {
  public function anonymousRole() {
    return DRUPAL_ANONYMOUS_RID;
  }

  public function getPerms() {
    $perms = user_role_permissions(array($this->rid => $this->name));
    return array_keys($perms[$this->rid]);
  }

  public function getModulePerms($module) {
    $perms = module_invoke($module, 'permission');
    return $perms ? array_keys($perms) : array();
  }

  public function role_create($role_machine_name, $role_human_readable_name = '') {
    return user_role_save((object)array('name' => $role_machine_name));
  }

  public function delete() {
    user_role_delete($this->rid);
  }

  public function grant_permissions($perms) {
    return drush_op('user_role_grant_permissions', $this->rid, $perms);
  }

  public function revoke_permissions($perms) {
    return drush_op('user_role_revoke_permissions', $this->rid, $perms);
  }
}
