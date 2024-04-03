<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drupal\user\Entity\Role;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;

final class RoleCommands extends DrushCommands
{
    use AutowireTrait;

    const CREATE = 'role:create';
    const DELETE = 'role:delete';
    const PERM_ADD = 'role:perm:add';
    const PERM_REMOVE = 'role:perm:remove';
    const LIST = 'role:list';

    public function __construct(
        private readonly SiteAliasManagerInterface $siteAliasManager
    ) {
        parent::__construct();
    }

    /**
     * Create a new role.
     */
    #[CLI\Command(name: self::CREATE, aliases: ['rcrt', 'role-create'])]
    #[CLI\Argument(name: 'machine_name', description: 'The symbolic machine name for the role.')]
    #[CLI\Argument(name: 'human_readable_name', description: 'A descriptive name for the role.')]
    #[CLI\Usage(name: "drush role:create 'test_role' 'Test role'", description: "Create a new role with a machine name of 'test_role', and a human-readable name of 'Test role'.")]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function createRole($machine_name, $human_readable_name = null)
    {
        $role = Role::create([
            'id' => $machine_name,
            'label' => $human_readable_name ?: ucfirst($machine_name),
        ]);
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
    #[CLI\Complete(method_name_or_callable: 'roleComplete')]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function delete($machine_name): void
    {
        $role = Role::load($machine_name);
        $role->delete();
        $this->logger()->success(dt('Deleted "!role"', ['!role' => $machine_name]));
    }

    /**
     * Grant specified permission(s) to a role.
     */
    #[CLI\Command(name: self::PERM_ADD, aliases: ['rap', 'role-add-perm'])]
    #[CLI\Argument(name: 'machine_name', description: 'The role to modify.')]
    #[CLI\Argument(name: 'permissions', description: 'The list of permission to grant, delimited by commas.')]
    #[CLI\Usage(name: "drush role:perm:add anonymous 'post comments'", description: 'Allow anon users to post comments.')]
    #[CLI\Usage(name: "drush role:perm:add anonymous 'post comments,access content'", description: 'Allow anon users to post comments and access content.')]
    #[CLI\ValidateEntityLoad(entityType: 'user_role', argumentName: 'machine_name')]
    #[CLI\ValidatePermissions(argName: 'permissions')]
    #[CLI\Complete(method_name_or_callable: 'roleComplete')]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function roleAddPerm($machine_name, $permissions): void
    {
        $perms = StringUtils::csvToArray($permissions);
        user_role_grant_permissions($machine_name, $perms);
        $this->logger()->success(dt('Added "!permissions" to "!role"', ['!permissions' => $permissions, '!role' => $machine_name]));
        $this->processManager()->drush($this->siteAliasManager->getSelf(), CacheRebuildCommands::REBUILD);
    }

    /**
     * Remove specified permission(s) from a role.
     */
    #[CLI\Command(name: self::PERM_REMOVE, aliases: ['rmp', 'role-remove-perm'])]
    #[CLI\Argument(name: 'machine_name', description: 'The role to modify.')]
    #[CLI\Argument(name: 'permissions', description: 'The list of permission to grant, delimited by commas.')]
    #[CLI\Usage(name: "drush role:remove-perm anonymous", description: 'Remove 2 permissions from anon users.')]
    #[CLI\ValidateEntityLoad(entityType: 'user_role', argumentName: 'machine_name')]
    #[CLI\ValidatePermissions(argName: 'permissions')]
    #[CLI\Complete(method_name_or_callable: 'roleComplete')]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function roleRemovePerm($machine_name, $permissions): void
    {
        $perms = StringUtils::csvToArray($permissions);
        user_role_revoke_permissions($machine_name, $perms);
        $this->logger()->success(dt('Removed "!permissions" to "!role"', ['!permissions' => $permissions, '!role' => $machine_name]));
        $this->processManager()->drush($this->siteAliasManager->getSelf(), CacheRebuildCommands::REBUILD);
    }

    /**
     * Display roles and their permissions.
     */
    #[CLI\Command(name: self::LIST, aliases: ['rls', 'role-list'])]
    #[CLI\Usage(name: "drush role:list --filter='administer nodes'", description: 'Display a list of roles that have the administer nodes permission assigned.')]
    #[CLI\Usage(name: "drush role:list --filter='rid=anonymous'", description: 'Display only the anonymous role.')]
    #[CLI\FieldLabels(labels: ['rid' => 'ID', 'label' => 'Role Label', 'perms' => 'Permissions'])]
    #[CLI\FilterDefaultField(field: 'perms')]
    #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
    public function roleList($options = ['format' => 'yaml']): RowsOfFields
    {
        $rows = [];
        $roles = Role::loadMultiple();
        foreach ($roles as $role) {
            $rows[$role->id()] = [
                'rid' => $role->id(),
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
    public function renderPermsCell($key, $cellData, FormatterOptions $options): string
    {
        if (is_array($cellData)) {
            return implode(',', $cellData);
        }
        return $cellData;
    }

    public function roleComplete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('machine_name')) {
            $suggestions->suggestValues(array_keys(Role::loadMultiple()));
        }
    }
}
