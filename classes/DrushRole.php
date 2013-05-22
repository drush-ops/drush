<?php

abstract class DrushRole {
  /**
   * Drupal 6 and Drupal 7:
   *   'rid' is numeric
   *   'name' is machine name (e.g. 'anonymous user')
   *
   * Drupal 8:
   *   'rid' is machine name (e.g. 'anonymous')
   *   'name' is human-readable name (e.g. 'Anonymous user').
   *
   * c.f. http://drupal.org/node/1619504
   */
  public $name;
  public $rid;

  /**
   * This is initialized to the result of the user_roles()
   * function, which returns an associative array of
   * rid => name pairs.
   */
  public $roles;

  /**
   * This constructor will allow the role to be selected either
   * via the role id or via the role name.
   */
  public function __construct($rid = DRUPAL_ANONYMOUS_RID) {
    $this->roles = user_roles();
    if (!is_numeric($rid)) {
      $role_name = $rid;
      if (in_array($role_name, $this->roles)) {
        $rid = array_search($role_name, $this->roles);
      }
    }

    if (isset($this->roles[$rid])) {
      $this->rid = $rid;
      $this->name = $this->roles[$rid];
    }
    else {
      throw new DrushRoleException(dt('Could not find the role: !role', array('!role' => $rid)));
    }
  }

  public function getPerms() {
    return array();
  }

  public function getModulePerms($module) {
    return array();
  }

  public function getAllModulePerms() {
    $permissions = array();
    $module_list = module_list();
    ksort($module_list);
    foreach ($module_list as $module) {
      if ($perms = $this->getModulePerms($module)) {
        $permissions = array_merge($permissions, $perms);
      }
    }
    return $permissions;
  }

  public function role_create($role_machine_name, $role_human_readable_name = '') {
  }

  public function delete() {
  }

  public function add($perm) {
    $perms = $this->getPerms();
    if (!in_array($perm, $perms)) {
      $this->grant_permissions(array($perm));
      return TRUE;
    }
    else {
      drush_log(dt('"!role" already has the permission "!perm"', array(
        '!perm' => $perm,
        '!role' => $this->name,
      )), 'ok');
      return FALSE;
    }
  }

  public function remove($perm) {
    $perms = $this->getPerms();
    if (in_array($perm, $perms)) {
      $this->revoke_permissions(array($perm));
      return TRUE;
    }
    else {
      drush_log(dt('"!role" does not have the permission "!perm"', array(
        '!perm' => $perm,
        '!role' => $this->name,
      )), 'ok');
      return FALSE;
    }
  }

  public function grant_permissions($perms) {
  }

  public function revoke_permissions($perms) {
  }
}

class DrushRole6 extends DrushRole {
  public $perms = array();

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
    drush_op('db_query', "UPDATE {permission} SET perm = '%s' FROM {role} WHERE role.rid = permission.rid AND role.rid= '%d'", $new_perms, $this->rid);
  }
}

class DrushRole7 extends DrushRole {
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

class DrushRole8 extends DrushRole7 {
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
    return user_role_save((object)array('name' => $role_human_readable_name, 'rid' => $role_machine_name));
  }
}

class DrushRoleException extends Exception {}
