<?php

namespace Drush\Role;

class Role6 extends RoleBase {
  public $perms = array();

  public function anonymousRole() {
    return DRUPAL_ANONYMOUS_RID;
  }

  public function getPerms() {
    if (empty($this->perms)) {
      $perms = db_result(db_query("SELECT perm FROM {permission} pm LEFT JOIN {role} r ON r.rid = pm.rid WHERE r.rid = '%d'", $this->rid));
      $role_perms = explode(", ", $perms);
      $this->perms = array_filter($role_perms);
    }
    return $this->perms;
  }

  public function getModulePerms($module) {
    return module_invoke($module, 'perm');
  }

  public function role_create($role_machine_name, $role_human_readable_name = '') {
    $this->_admin_user_role_op($role_machine_name, t('Add role'));
    return TRUE;
  }

  public function delete() {
    $this->_admin_user_role_op($this->rid, t('Delete role'));
  }

  function _admin_user_role_op($role_machine_name, $op) {
    // c.f. http://drupal.org/node/283261
    require_once(drupal_get_path('module', 'user') . "/user.admin.inc");

    $form_id = "user_admin_new_role";
    $form_values = array();
    $form_values["name"] = $role_machine_name;
    $form_values["op"] = $op;
    $form_state = array();
    $form_state["values"] = $form_values;

    drupal_execute($form_id, $form_state);
  }

  public function grant_permissions($perms_to_add) {
    $perms = $this->getPerms();
    $this->perms = array_unique(array_merge($this->perms, $perms_to_add));
    $this->updatePerms();
  }

  public function revoke_permissions($perms_to_remove) {
    $perms = $this->getPerms();
    $this->perms = array_diff($this->perms, $perms_to_remove);
    $this->updatePerms();
  }

  function updatePerms() {
    $new_perms = implode(", ", $this->perms);
    drush_op('db_query', "UPDATE {permission} SET perm = '%s' WHERE rid= %d", $new_perms, $this->rid);
  }
}
