<?php

namespace Drush\Role;

abstract class RoleBase {
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
      // In D8+ Role is an object.
      $this->name = is_object($this->roles[$rid]) ? $this->roles[$rid]->label() : $this->roles[$rid];
    }
    else {
      throw new RoleException(dt('Could not find the role: !role', array('!role' => $rid)));
    }
  }

  /*
   * Get all perms for a given Role.
   */
  public function getPerms() {
    return array();
  }

  /*
   * Get all perms for a given module.
   */
  public function getModulePerms($module) {
    return array();
  }

  /*
   * Get all permissions site-wide.
   */
  public function getAllModulePerms() {
    $permissions = array();
    drush_include_engine('drupal', 'environment');
    $module_list = drush_module_list();
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
