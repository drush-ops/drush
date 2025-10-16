<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use JetBrains\PhpStorm\Deprecated;

final class RoleCommands
{
    #[Deprecated(reason: 'Use RoleCreateCommand::NAME')]
    const CREATE = 'role:create';
    #[Deprecated(reason: 'Use RoleDeleteCommand::NAME')]
    const DELETE = 'role:delete';
    #[Deprecated(reason: 'Use RolePermAddCommand::NAME')]
    const PERM_ADD = 'role:perm:add';
    #[Deprecated(reason: 'Use RolePermRemoveCommand::NAME')]
    const PERM_REMOVE = 'role:perm:remove';
    #[Deprecated(reason: 'Use RoleListCommand::NAME')]
    const LIST = 'role:list';
}
