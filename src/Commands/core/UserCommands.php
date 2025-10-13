<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use JetBrains\PhpStorm\Deprecated;

final class UserCommands
{
    #[Deprecated(reason: 'Use UserInformationCommand::NAME')]
    const INFORMATION = 'user:information';
    #[Deprecated(reason: 'Use UserBlockCommand::NAME')]
    const BLOCK = 'user:block';
    #[Deprecated(reason: 'Use UserUnblockCommand::NAME')]
    const UNBLOCK = 'user:unblock';
    #[Deprecated(reason: 'Use UserRoleAddCommand::NAME')]
    const ROLE_ADD = 'user:role:add';
    #[Deprecated(reason: 'Use UserRoleRemoveCommand::NAME')]
    const ROLE_REMOVE = 'user:role:remove';
    #[Deprecated(reason: 'Use UserCreateCommand::NAME')]
    const CREATE = 'user:create';
    #[Deprecated(reason: 'Use UserCancelCommand::NAME')]
    const CANCEL = 'user:cancel';
    #[Deprecated(reason: 'Use UserPasswordCommand::NAME')]
    const PASSWORD = 'user:password';
}
