<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use JetBrains\PhpStorm\Deprecated;

final class RoleCommands
{
    #[Deprecated(reason: 'Use RoleCreateCommand::NAME')]
    const string CREATE = 'role:create';
    #[Deprecated(reason: 'Use RoleDeleteCommand::NAME')]
    const string DELETE = 'role:delete';
    #[Deprecated(reason: 'Use RolePermAddCommand::NAME')]
    const string PERM_ADD = 'role:perm:add';
    #[Deprecated(reason: 'Use RolePermRemoveCommand::NAME')]
    const string PERM_REMOVE = 'role:perm:remove';
    #[Deprecated(reason: 'Use RoleListCommand::NAME')]
    const string LIST = 'role:list';
}
