<?php

namespace Drush\Drupal\Commands\core;

use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drupal\user\Entity\Role;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drush\SiteAlias\SiteAliasManagerAwareInterface;
use Drush\Utils\StringUtils;

final class RoleCommands extends DrushCommands implements SiteAliasManagerAwareInterface
{
    use SiteAliasManagerAwareTrait;

    const CREATE = 'role:create';
    const DELETE = 'role:delete';
    const PERM_ADD = 'role:perm:add';
    const PERM_REMOVE = 'role:perm:remove';
    const LIST = 'role:list';

    /**
     * Create a new role.
     *
     * @command role:create
     * @param $machine_name The symbolic machine name for the role.
     * @param $human_readable_name A descriptive name for the role.
     * @usage drush role:create 'test_role' 'Test role'
     *   Create a new role with a machine name of 'test_role', and a human-readable name of 'Test role'.
     * @aliases rcrt,role-create
     */
    #[CLI\Command(name: self::CREATE, aliases: ['rcrt', 'role-create'])]
    #[CLI\Argument(name: 'machine_name', description: 'The symbolic machine name for the role.')]
    #[CLI\Argument(name: 'human_readable_name', description: 'A descriptive name for the role.')]
    #[CLI\Usage(name: "drush role:create 'test_role' 'Test role'", description: "Create a new role with a machine name of 'test_role', and a human-readable name of 'Test role'.")]
    public function create($machine_name, $human_readable_name = null)
    {
        $role = Role::create([
            'id' => $machine_name,
            'label' => $human_readable_name ?: ucfirst($machine_name),
        ], 'user_role');
        $role->save();
        $this->logger()->success(dt('Created "!role"', ['!role' => $machine_name]));
        return $role;
    }

    /**
     * Delete a role.
     */
    #[CLI\Command(name: self::DELETE, aliases: ['rdel', 'role-delete'])]
    #[CLI\Argument(name: 'machine_name', description: 'The symbolic machine name for the role.')]
    #[CLI\ValidateEntityLoad(entityType: 'user_role', argumentName: 'machine_name')]
    #[CLI\Usage(name: "drush role:delete 'test_role'", description: "Delete the role 'test_role'.")]
    public function delete($machine_name): void
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
     * @usage  drush role:perm:add anonymous 'post comments'
     *   Allow anon users to post comments.
     * @usage drush role:perm:add anonymous 'post comments,access content'
     *   Allow anon users to post comments and access content.
     * @aliases rap,role-add-perm
     */
    #[CLI\Command(name: self::PERM_ADD, aliases: ['rap', 'role-add-perm'])]
    #[CLI\Argument(name: 'machine_name', description: 'The role to modify.')]
    #[CLI\Argument(name: 'permissions', description: 'The list of permission to grant, delimited by commas.')]
    #[CLI\Usage(name: "drush role:perm:add anonymous 'post comments'", description: 'Allow anon users to post comments.')]
    #[CLI\Usage(name: "drush role:perm:add anonymous 'post comments,access content'", description: 'Allow anon users to post comments and access content.')]
    #[CLI\ValidateEntityLoad(entityType: 'user_role', argumentName: 'machine_name')]
    #[CLI\ValidatePermissions(argName: 'permissions')]
    public function roleAddPerm($machine_name, $permissions): void
    {
        $perms = StringUtils::csvToArray($permissions);
        user_role_grant_permissions($machine_name, $perms);
        $this->logger()->success(dt('Added "!permissions" to "!role"', ['!permissions' => $permissions, '!role' => $machine_name]));
        $this->processManager()->drush($this->siteAliasManager()->getSelf(), 'cache:rebuild');
    }

    /**
     * Remove specified permission(s) from a role.
     *
     * @command role:perm:remove
     * @validate-entity-load user_role machine_name
     * @validate-permissions permissions
     * @param $machine_name The role to modify.
     * @param $permissions The list of permission to grant, delimited by commas.
     * @usage drush role:remove-perm anonymous 'post comments,access content'
     *   Remove 2 permissions from anon users.
     * @aliases rmp,role-remove-perm
     */
    #[CLI\Command(name: self::PERM_REMOVE, aliases: ['rmp', 'role-remove-perm'])]
    #[CLI\Argument(name: 'machine_name', description: 'The role to modify.')]
    #[CLI\Argument(name: 'permissions', description: 'The list of permission to grant, delimited by commas.')]
    #[CLI\Usage(name: "drush role:remove-perm anonymous", description: 'Remove 2 permissions from anon users.')]
    #[CLI\ValidateEntityLoad(entityType: 'user_role', argumentName: 'machine_name')]
    #[CLI\ValidatePermissions(argName: 'permissions')]
    public function roleRemovePerm($machine_name, $permissions): void
    {
        $perms = StringUtils::csvToArray($permissions);
        user_role_revoke_permissions($machine_name, $perms);
        $this->logger()->success(dt('Removed "!permissions" to "!role"', ['!permissions' => $permissions, '!role' => $machine_name]));
        $this->processManager()->drush($this->siteAliasManager()->getSelf(), 'cache:rebuild');
    }

    /**
     * Display a list of all roles defined on the system.
     *
     * If a role name is provided as an argument, then all of the permissions of
     * that role will be listed.  If a permission name is provided as an option,
     * then all of the roles that have been granted that permission will be listed.
     *
     * @command role:list
     * @usage drush role:list --filter='administer nodes'
     *   Display a list of roles that have the administer nodes permission assigned.
     * @aliases rls,role-list
     * @field-labels
     *   rid: ID
     *   label: Role Label
     *   perms: Permissions
     *
     * @filter-default-field perms
     */
    #[CLI\Command(name: self::LIST, aliases: ['rls', 'role-list'])]
    #[CLI\Usage(name: "drush role:list --filter='administer nodes'", description: 'Display a list of roles that have the administer nodes permission assigned.')]
    #[CLI\FieldLabels(labels: ['rid' => 'ID', 'label' => 'Role Label', 'perms' => 'Permissions'])]
    #[CLI\FilterDefaultField(field: 'perms')]
    public function roleList($options = ['format' => 'yaml']): RowsOfFields
    {
        $rows = [];
        $roles = Role::loadMultiple();
        foreach ($roles as $role) {
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
