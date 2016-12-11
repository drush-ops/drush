<?php
namespace Drush\Commands\core;

use Consolidation\OutputFormatters\Options\FormatterOptions;
use Drush\Commands\DrushCommands;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Log\LogLevel;
use Drush\Role\RoleBase;


class RoleCommands extends DrushCommands {

  /**
   * Create a new role.
   *
   * @command role-create
   * @param $machine_name The symbolic machine name for the role.
   * @param $human_readable_name A descriptive name for the role.
   * @usage drush role-create 'test role'
   *   Create a new role 'test role'. On D8, the human-readable name will be 'Test role'.
   * @usage drush role-create 'test role' 'Test role'
   *   Create a new role with a machine name of 'test role', and a human-readable name of 'Test role'.
   * @aliases rcrt
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   */
  public function create($machine_name, $human_readable_name = NULL) {
    $role = $this->get_instance();
    $result = $role->role_create($machine_name, $human_readable_name);
    if ($result !== FALSE) {
      $this->logger()->log(dt('Created "!role"', array('!role' => $machine_name)), LogLevel::SUCCESS);
    }
    return $result;
  }

  /**
   * Delete a new role.
   *
   * @command role-delete
   * @param $machine_name The symbolic machine name for the role.
   * @usage drush role-delete 'test role'
   *   Delete the role 'test role'.
   * @aliases rdel
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   */
  public function delete($machine_name) {
    $role = $this->get_instance($machine_name);
    if ($role === FALSE) {
      return FALSE;
    }
    $result = $role->delete();
    if ($result !== FALSE) {
      $this->logger()->log(dt('Deleted "!role"', array('!role' => $machine_name)), LogLevel::SUCCESS);
    }
    return $result;
  }

  /**
   * Grant specified permission(s) to a role.
   *
   * @todo Add validation for permission names.
   *
   * @command role-add-perm
   * @param $machine_name The role to modify.
   * @param $permissions The list of permission to grant, delimited by commas.
   * @option cache-clear Set to 0 to suppress normal cache clearing; the caller should then clear if needed.
   * @usage  drush role-add-perm anonymous 'post comments'
   *   Allow anon users to post comments.
   * @usage drush role-add-perm anonymous "'post comments','access content'"
   *   Allow anon users to post comments and access content.
   * @usage drush pm-info --fields=permissions --format=csv aggregator
   *   Discover the permissions associated with  given module (then use this command as needed).
   * @aliases rap
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   */
  public function role_add_perm($machine_name, $permissions) {
    $perms = _convert_csv_to_array($permissions);
    $role = $this->get_instance($machine_name);

    $result = $role->grant_permissions($perms);
    if ($result === FALSE) {
      throw new \Exception(dt('Failed to add "!permissions" to "!role"', array('!permissions' => $permissions, '!role' => $result->name)));
    }
    else {
      $this->logger()->log(dt('Added "!permissions" to "!role"', array('!permissions' => $permissions, '!role' => $result->name)), LogLevel::SUCCESS);
      drush_drupal_cache_clear_all();
    }
  }

  /**
   * Remove specified permission(s) from a role.
   *
   * @command role-remove-perm
   * @param $machine_name The role to modify.
   * @param $permissions The list of permission to grant, delimited by commas.
   * @option cache-clear Set to 0 to suppress normal cache clearing; the caller should then clear if needed.
   * @usage drush role-remove-perm anonymous 'access content'
   *   Hide content from anon users.
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @aliases rmp
   */
  public function role_remove_perm($machine_name, $permissions) {
    $perms = _convert_csv_to_array($permissions);
    $role = $this->get_instance($machine_name);

    $result = $role->revoke_permissions($perms);
    if ($result === FALSE) {
      throw new \Exception(dt('Failed to remove "!permissions" to "!role"', array('!permissions' => $permissions, '!role' => $result->name)));
    }
    else {
      $this->logger()->log(dt('Removed "!permissions" to "!role"', array('!permissions' => $permissions, '!role' => $result->name)), LogLevel::SUCCESS);
      drush_drupal_cache_clear_all();
    }
  }

  /**
   * Display a list of all roles defined on the system.  If a role name is provided as an argument, then all of the permissions of that role will be listed.  If a permission name is provided as an option, then all of the roles that have been granted that permission will be listed.
   *
   * @command role-list
   * @option filter Limits the list of roles to only those that have been assigned the specified permission.
   * @usage drush role-list --filter='administer nodes'
   *   Display a list of roles that have the administer nodes permission assigned.
   * @aliases rls
   * @bootstrap DRUSH_BOOTSTRAP_DRUPAL_FULL
   * @field-labels
   *   rid: ID
   *   label: Role Label
   *   perms: Permissions
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   */
  public function rlist($options = ['format' => 'yaml', 'filter' => NULL]) {
    if (!$roles = \user_roles(FALSE, $options['filter'])) {
      return new \Exception(dt("No roles found."));
    }
    else {
      foreach ($roles as $key => $value) {
        $role = $this->get_instance($key);
        $rows[$role->rid] = array(
          'label' => $role->name,
          'perms' => $role->getPerms(),
        );
      }
      $result = new RowsOfFields($rows);
      $result->addRendererFunction([$this, 'renderPermsCell']);
      return $result;
    }
  }

  /*
   * Used in the unlikely event user specifies --format=table.
   */
  public function renderPermsCell($key, $cellData, FormatterOptions $options)  {
    if (is_array($cellData)) {
      return implode(',', $cellData);
    }
    return $cellData;
  }

  /**
   * Get core version specific Role handler instance.
   *
   * @param string $role_name
   * @return RoleBase
   *
   * @see drush_get_class().
   */
  static function get_instance($role_name = 'anonymous') {
    return drush_get_class('Drush\Role\Role', func_get_args());
  }
}