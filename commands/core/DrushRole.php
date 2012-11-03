<?php

abstract class DrushRole {
  public $name;
  public $rid;
  public $roles;

  public function __construct($rid) {
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
    $perms = user_role_permissions(array($this->rid => $this->name));
    return array_keys($perms[$this->rid]);
  }

  public function getModulePerms($module) {
    $perms = module_invoke($module, 'permission');
    return $perms ? array_keys($perms) : array();
  }

  public function add($perm) {
    user_role_grant_permissions($this->rid, array($perm));
    return dt('Added "!perm" to "!role"', array('!perm' => $perm, '!role' => $this->name));
  }

  public function remove($perm) {
    return user_role_revoke_permissions($this->rid, array($perm));
    return dt('Removed "!perm" to "!role"', array('!perm' => $perm, '!role' => $this->name));
  }
}

class DrushRole6 extends DrushRole {
  public $perms = array();

  public function getModulePerms($module) {
    return module_invoke($module, 'perm');
  }

  public function add($perm) {
    $perms = $this->getPerms();
    if (!in_array($perm, $this->perms)) {
      $this->perms[] = $perm;
      $new_perms = implode(", ", $this->perms);
      db_query("UPDATE {permission} SET perm = '%s' FROM {role} WHERE role.rid = permission.rid AND role.rid= '%d'", $new_perms, $this->rid);
    }
    else {
      drush_print(dt('"!role" already has the permission "!perm"', array(
        '!perm' => $perm,
        '!role' => $this->name,
      )));
    }
  }

  public function getPerms() {
    if (empty($this->perms)) {
      $perms = db_result(db_query("SELECT perm FROM {permission} pm LEFT JOIN {role} r ON r.rid = pm.rid WHERE r.rid = '%d'", $this->rid));
      $role_perms = explode(", ", $perms);
      $this->perms = array_filter($role_perms);
    }
    return $this->perms;
  }
  public function remove($perm) {
  }
}

class DrushRole7 extends DrushRole {}

class DrushRoleException extends Exception {}
