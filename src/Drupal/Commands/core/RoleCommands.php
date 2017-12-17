<?php
namespace Drush\Drupal\Commands\core;

use Consolidation\OutputFormatters\Options\FormatterOptions;
use Drupal\user\Entity\Role;
use Drush\Commands\DrushCommands;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\Log\LogLevel;
use Drush\Role\RoleBase;

class RoleCommands extends DrushCommands
{
    /**
     * Create a new role.
     *
     * @command role:create
     * @param $machine_name The symbolic machine name for the role.
     * @param $human_readable_name A descriptive name for the role.
     * @usage drush role:create 'test role'
     *   Create a new role 'test role'. On D8, the human-readable name will be 'Test role'.
     * @usage drush role:create 'test role' 'Test role'
     *   Create a new role with a machine name of 'test role', and a human-readable name of 'Test role'.
     * @aliases rcrt,role-create
     */
    public function create($machine_name, $human_readable_name = null)
    {
        $role = Role::create([
        'id' => $machine_name,
        'label' => $human_readable_name,
        ], 'user_role');
        $role->save();
        $this->logger()->success(dt('Created "!role"', ['!role' => $machine_name]));
        return $role;
    }

    /**
     * Delete a new role.
     *
     * @command role:delete
     * @param $machine_name The symbolic machine name for the role.
     * @validate-entity-load user_role machine_name
     * @usage drush role:delete 'test role'
     *   Delete the role 'test role'.
     * @aliases rdel,role-delete
     */
    public function delete($machine_name)
    {
        $role = Role::load($machine_name);
        $role->delete();
        $this->logger()->success(dt('Deleted "!role"', ['!role' => $machine_name]));
    }

    /**
     * Grant specified permission(s) to a role.
     *
     * @todo Add validation for permission names.
     *
     * @command role:perm:add
     * @validate-entity-load user_role machine_name
     * @validate-permissions permissions
     * @param $machine_name The role to modify.
     * @param $permissions The list of permission to grant, delimited by commas.
     * @option cache-clear Set to 0 to suppress normal cache clearing; the caller should then clear if needed.
     * @usage  drush role-add-perm anonymous 'post comments'
     *   Allow anon users to post comments.
     * @usage drush role:add-perm anonymous "'post comments','access content'"
     *   Allow anon users to post comments and access content.
     * @usage drush pm:info --fields=permissions --format=csv aggregator
     *   Discover the permissions associated with  given module (then use this command as needed).
     * @aliases rap,role-add-perm
     */
    public function roleAddPerm($machine_name, $permissions)
    {
        $perms = _convert_csv_to_array($permissions);
        user_role_grant_permissions($machine_name, $perms);
        $this->logger()->success(dt('Added "!permissions" to "!role"', ['!permissions' => $permissions, '!role' => $machine_name]));
        drush_drupal_cache_clear_all();
    }

    /**
     * Remove specified permission(s) from a role.
     *
     * @command role:perm:remove
     * @validate-entity-load user_role machine_name
     * @validate-permissions permissions
     * @param $machine_name The role to modify.
     * @param $permissions The list of permission to grant, delimited by commas.
     * @option cache-clear Set to 0 to suppress normal cache clearing; the caller should then clear if needed.
     * @usage drush role:remove-perm anonymous 'access content'
     *   Hide content from anon users.
     * @aliases rmp,role-remove-perm
     */
    public function roleRemovePerm($machine_name, $permissions)
    {
        $perms = _convert_csv_to_array($permissions);
        user_role_revoke_permissions($machine_name, $perms);
        $this->logger()->success(dt('Removed "!permissions" to "!role"', ['!permissions' => $permissions, '!role' => $result->name]));
        drush_drupal_cache_clear_all();
    }

    /**
     * Display a list of all roles defined on the system.
     *
     * If a role name is provided as an argument, then all of the permissions of
     * that role will be listed.  If a permission name is provided as an option,
     * then all of the roles that have been granted that permission will be listed.
     *
     * @command role:list
     * @validate-permissions filter
     * @option filter Limits the list of roles to only those that have been assigned the specified permission.
     * @usage drush role:list --filter='administer nodes'
     *   Display a list of roles that have the administer nodes permission assigned.
     * @aliases rls,role-list
     * @field-labels
     *   rid: ID
     *   label: Role Label
     *   perms: Permissions
     *
     * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
     */
    public function roleList($options = ['format' => 'yaml', 'filter' => self::REQ])
    {
        $rows = [];
        $roles = Role::loadMultiple();
        foreach ($roles as $role) {
            if ($options['filter'] && !$role->hasPermission($options['filter'])) {
                continue;
            }
            $rows[$role->id()] = [
            'label' => $role->label(),
            'perms' => $role->getPermissions(),
            ];
        }
        $result = new RowsOfFields($rows);
        $result->addRendererFunction([$this, 'renderPermsCell']);
        return $result;
    }

    /*
     * Used in the unlikely event user specifies --format=table.
     */
    public function renderPermsCell($key, $cellData, FormatterOptions $options)
    {
        if (is_array($cellData)) {
            return implode(',', $cellData);
        }
        return $cellData;
    }
}
