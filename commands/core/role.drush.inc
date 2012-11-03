<?php

/**
 * Implementation of hook_drush_command().
 */
function role_drush_command() {
  $items['role-add-perm'] = array(
    'description' => '',
    'arguments' => array(
      'role' => 'role',
      'perm' => 'perm',
    ),
    'options' => array(
      'module' => 'List the permissions provided by a specific module.',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_MAX,
  );

  $items['role-remove-perm'] = array(
    'description' => '',
    'arguments' => array(
      'role' => 'role',
      'perm' => 'perm',
    ),
    'options' => array(
      'module' => 'List the permissions provided by a specific module.',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_MAX,
  );

  $items['role-list'] = array(
    'description' => 'Display a list of roles.',
    'aliases' => array('rls'),
    'options' => array(
      'perm' => 'Perform a search on roles that have a certain permission',
    ),
    'examples' => array(
      "drush role-list --perm='administer nodes'" => 'Display a list of roles that have the administer nodes permission assigned.',
    ),
  );

  return $items;
}

function drush_role_add_perm($rid, $permission = NULL) {
  drush_role_perm('add', $rid, $permission);
}

function drush_role_remove_perm($rid, $permission = NULL) {
  drush_role_perm('remove', $rid, $permission);
}

function drush_role_perm($action, $rid, $permission = NULL) {
  require dirname(__FILE__) . '/DrushRole.php';
  $permissions = array();
  $role = drush_role_get_class($rid);

  // If a permission wasn't provided, but the module option is specified,
  // provide a list of permissions provided by that module.
  if (!$permission && $module = drush_get_option('module', FALSE)) {
    if (!module_exists($module)) {
      drush_set_error('DRUSH_ROLE_ERROR', dt('!module not enabled!', array('!module' => $module)));
      return;
    }

    $module_perms = $role->getModulePerms($module);
    $choice = drush_choice($module_perms, "Enter a number to choose which permission to $action.");
    if ($choice !== FALSE) {
      $permission = $module_perms[$choice];
    }
  }
  else {
    foreach (module_list(FALSE, FALSE, TRUE) as $module) {
      if ($perms = $role->getModulePerms($module)) {
        $permissions = array_merge($permissions, $perms);
      }
    }
    if (!in_array($permission, $permissions)) {
      drush_set_error(dt('Could not find the permission: !perm', array('!perm' => $permission)));
    }
  }

  $role_perms = $role->getPerms();

  $role->{$action}($permission);
  drush_print(dt(ucfirst($action) . 'ed "!perm" to "!role"', array('!perm' => $permission, '!role' => $role->name)));

  cache_clear_all();
}

function drush_role_get_class($role_name) {
  $version = drush_drupal_major_version();
  $class = 'DrushRole' . $version;
  if (class_exists($class)) {
    $role = new $class($role_name);
    return $role;
  }
  else {
    drush_set_error('DRUSH_ROLE_ERROR', dt('Drupal %d not supported!', $version));
  }
}

/**
 * Displays a list of roles
 */
function drush_role_list() {
  // get options passed
  $perm = drush_get_option('perm');
  $roles = array();

  // get all roles - if $perm is empty user_roles retrieves all roles
  $roles = user_roles(FALSE, $perm);

  if (!empty($roles)) {
    $rows[] = array('rid',dt('Name'));
    foreach($roles as $rid => $name) {
      $rows[] = array($rid, $name);
      drush_print_pipe($rid .','. $name ."\n");
    }
    $num = count($roles);

    drush_print_table($rows, TRUE);
  }

  else {
    drush_set_error('No roles found.');
  }
}
