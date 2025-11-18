<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use JetBrains\PhpStorm\Deprecated;

final class UserCommands
{
    #[Deprecated(reason: 'Use UserInformationCommand::NAME')]
    const string INFORMATION = 'user:information';
    #[Deprecated(reason: 'Use UserBlockCommand::NAME')]
    const string BLOCK = 'user:block';
    #[Deprecated(reason: 'Use UserUnblockCommand::NAME')]
    const string UNBLOCK = 'user:unblock';
    #[Deprecated(reason: 'Use UserRoleAddCommand::NAME')]
    const string ROLE_ADD = 'user:role:add';
    #[Deprecated(reason: 'Use UserRoleRemoveCommand::NAME')]
    const string ROLE_REMOVE = 'user:role:remove';
    #[Deprecated(reason: 'Use UserCreateCommand::NAME')]
    const string CREATE = 'user:create';
    #[Deprecated(reason: 'Use UserCancelCommand::NAME')]
    const string CANCEL = 'user:cancel';
    #[Deprecated(reason: 'Use UserPasswordCommand::NAME')]
    const string PASSWORD = 'user:password';
}
