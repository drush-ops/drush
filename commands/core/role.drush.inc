<?php

/**
 * Implementation of hook_drush_help().
 */
function role_drush_help($section) {
  switch ($section) {
    case 'meta:role:title':
      return dt('Role commands');
    case 'meta:role:summary':
      return dt('Interact with the role system.');
  }
}

/**
 * Implementation of hook_drush_command().
 */
function role_drush_command() {
  $items['role-create'] = array(
    'description' => 'Create a new role.',
    'examples' => array(
      "drush role-create 'test role'" => "Create a new role 'test role' on D6 or D7; auto-assign the rid. On D8, 'test role' is the rid, and the human-readable name is set to 'Test role'.",
      "drush role-create 'test role' 'Test role'" => "Create a new role with a machine name of 'test role', and a human-readable name of 'Test role'. On D6 and D7, behaves as the previous example."
    ),
    'arguments' => array(
      'machine name' => 'The symbolic machine name for the role. Required.',
      'human-readable name' => 'A descriptive name for the role. Optional; Drupal 8 only.  Ignored in D6 and D7.',
    ),
    'aliases' => array('rcrt'),
  );
  $items['role-delete'] = array(
    'description' => 'Delete a role.',
    'examples' => array(
      "drush role-delete 'test role'" => "Delete the role 'test role'.",
    ),
    'arguments' => array(
      'machine name' => 'The symbolic machine name for the role. Required.  In D6 and D7, this may also be a numeric role ID.',
    ),
    'aliases' => array('rdel'),
  );
  $items['role-add-perm'] = array(
    'description' => 'Grant a specified permission to a role.',
    'examples' => array(
      "drush role-add-perm 'anonymous user' 'post comments'" => 'Allow anonymous users to put their own advertisements on your site.',
      "drush role-add-perm 'authenticated user' --module=node" => 'Select a permission from "node" permissions to add to authenticated users.'
    ),
    'arguments' => array(
      'role' => 'The role to modify.  Required.',
      'permission' => 'The permission to grant. Required, unless the --module option is used.',
    ),
    'required-arguments' => 1,
    'options' => array(
      'module' => 'Select the permission to modify from an interactive list of all permissions available in the specified module.',
    ),
    'aliases' => array('rap'),
  );

  $items['role-remove-perm'] = array(
    'description' => 'Remove a specified permission from a role.',
    'examples' => array(
      "drush role-remove-perm 'anonymous user' 'access content'" => 'Hide content from anonymous users.',
    ),
    'arguments' => array(
      'role' => 'The role to modify.',
      'permission' => 'The permission to remove. Required, unless the --module option is used.',
    ),
    'required-arguments' => 1,
    'options' => array(
      'module' => 'Select the permission to modify from an interactive list of all permissions available in the specified module.',
    ),
    'aliases' => array('rmp'),
  );

  $items['role-list'] = array(
    'description' => 'Display a list of all roles defined on the system.  If a role name is provided as an argument, then all of the permissions of that role will be listed.  If a permission name is provided as an option, then all of the roles that have been granted that permission will be listed.',
    'examples' => array(
      "drush role-list --filter='administer nodes'" => 'Display a list of roles that have the administer nodes permission assigned.',
      "drush role-list 'anonymous user'" => 'Display all of the permissions assigned to the anonymous user role.'
    ),
    'arguments' => array(
      'role' => 'The role to list.  Optional; if specified, lists all permissions assigned to that role.  If no role is specified, lists all of the roles available on the system.',
    ),
    'options' => array(
      'filter' => 'Limits the list of roles to only those that have been assigned the specified permission. Optional; may not be specified if a role argument is provided.',
    ),
    'outputformat' => array(
      'default' => 'table',
      'pipe-format' => 'list',
      'field-labels' => array('rid' => 'ID', 'role' => 'Role Name', 'perm' => "Permission"),
      'output-data-type' => 'format-table',
    ),
    'aliases' => array('rls'),
  );

  return $items;
}

/**
 * Create the specified role
 */
function drush_role_create($rid, $role_name = '') {
  $role = drush_role_get_class();
  $result = $role->role_create($rid, $role_name);
  if ($result !== FALSE) {
    drush_log(dt('Created "!role"', array('!role' => $rid)), 'success');
  }
  return $result;
}

/**
 * Create the specified role
 */
function drush_role_delete($rid) {
  $role = drush_role_get_class($rid);
  if ($role === FALSE) {
    return FALSE;
  }
  $result = $role->delete();
  if ($result !== FALSE) {
    drush_log(dt('Deleted "!role"', array('!role' => $rid)), 'success');
  }
  return $result;
}

/**
 * Add a permission to the specified role.
 */
function drush_role_add_perm($rid, $permission = NULL) {
  $result = drush_role_perm('add', $rid, $permission);
  if ($result !== FALSE) {
    drush_log(dt('Added "!perm" to "!role"', array('!perm' => $permission, '!role' => $result->name)), 'success');
    drush_drupal_cache_clear_all();
  }
  return $result;
}

/**
 * Remove a permission from the specified role.
 */
function drush_role_remove_perm($rid, $permission = NULL) {
  $result = drush_role_perm('remove', $rid, $permission);
  if ($result !== FALSE) {
    drush_log(dt('Removed "!perm" from "!role"', array('!perm' => $permission, '!role' => $result->name)), 'ok');
    drush_drupal_cache_clear_all();
  }
  return $result;
}

/**
 * Implement permission add / remove operations.
 */
function drush_role_perm($action, $rid, $permission = NULL) {
  $role = drush_role_get_class($rid);
  if (!$role) {
    return FALSE;
  }

  // If a permission wasn't provided, but the module option is specified,
  // provide a list of permissions provided by that module.
  if (!$permission && $module = drush_get_option('module', FALSE)) {
    if (!module_exists($module)) {
      return drush_set_error('DRUSH_ROLE_ERROR', dt('!module not enabled!', array('!module' => $module)));
    }
    $module_perms = $role->getModulePerms($module);
    if (empty($module_perms)) {
      return drush_set_error('DRUSH_ROLE_NO_PERMISSIONS', dt('No permissions found for module !module', array('!module' => $module)));
    }
    $choice = drush_choice($module_perms, "Enter a number to choose which permission to $action.");
    if ($choice === FALSE) {
      return FALSE;
    }
    $permission = $module_perms[$choice];
  }
  else {
    $permissions = $role->getAllModulePerms();
    if (!in_array($permission, $permissions)) {
      return drush_set_error(dt('Could not find the permission: !perm', array('!perm' => $permission)));
    }
  }

  $role_perms = $role->getPerms();

  $result = $role->{$action}($permission);
  if ($result === FALSE) {
    return FALSE;
  }
  return $role;
}

/**
 * Wrapper for drush_get_class; instantiates an instance of the version-specific
 * DrushRole class suitable for the current version of Drupal.
 */
function drush_role_get_class($role_name = DRUPAL_ANONYMOUS_RID) {
  return drush_get_class('DrushRole', array($role_name));
}

/**
 * Displays a list of roles
 */
function drush_role_list($rid = '') {
  $result = array();
  if (empty($rid)) {
    drush_hide_output_fields(array('perm'));
    // get options passed
    $perm = drush_get_option('filter');
    $roles = array();

    // get all roles - if $perm is empty user_roles retrieves all roles
    $roles = user_roles(FALSE, $perm);
    if (empty($roles)) {
      return drush_set_error('DRUSH_NO_ROLES', dt("No roles found."));
    }
    foreach ($roles as $rid => $role_name) {
      $result[$role_name] = array(
        'rid' => $rid,
        'role' => $role_name,
      );
    }
  }
  else {
    drush_hide_output_fields(array('rid', 'role'));
    $role = drush_role_get_class($rid);
    if (!$role) {
      return FALSE;
    }
    $perms = $role->getPerms();
    foreach ($perms as $permission) {
      $result[$permission] = array(
        'perm' => $permission);
    }
  }
  return $result;
}
