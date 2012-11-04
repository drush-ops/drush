<?php

abstract class DrushRole {
  public $name;
  public $rid;
  public $roles;

  public function __construct($rid = 'anonymous user') {
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

  public function add($perm) {
    $perms = $this->getPerms();
    if (!in_array($perm, $perms)) {
      $this->grant_permissions(array($perm));
      return TRUE;
    }
    else {
      drush_print(dt('"!role" already has the permission "!perm"', array(
        '!perm' => $perm,
        '!role' => $this->name,
      )));
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
      drush_print(dt('"!role" does not have the permission "!perm"', array(
        '!perm' => $perm,
        '!role' => $this->name,
      )));
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

  public function grant_permissions($perms_to_add) {
    $perms = $this->getPerms();
    $this->perms += $perms_to_add;
    $this->updatePerms();
  }

  public function revoke_permissions($perms_to_remove) {
    $perms = $this->getPerms();
    $this->perms = array_diff($this->perms, $perms_to_remove);
    $this->updatePerms();
  }

  function updatePerms() {
    $new_perms = implode(", ", $this->perms);
    db_query("UPDATE {permission} SET perm = '%s' FROM {role} WHERE role.rid = permission.rid AND role.rid= '%d'", $new_perms, $this->rid);
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

  public function grant_permissions($perms) {
    user_role_grant_permissions($this->rid, $perms);
  }

  public function revoke_permissions($perms) {
    return user_role_revoke_permissions($this->rid, $perms);
  }
}

class DrushRoleException extends Exception {}
