<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use JetBrains\PhpStorm\Deprecated;

final class ViewsCommands
{
    #[Deprecated(reason: 'Use ViewsDevCommand::NAME')]
    const DEV = 'views:dev';
    #[Deprecated(reason: 'Use ViewsExecuteCommand::NAME')]
    const EXECUTE = 'views:execute';
    #[Deprecated(reason: 'Use ViewsListCommand::NAME')]
    const LIST = 'views:list';
    #[Deprecated(reason: 'Use ViewsEnableCommand::NAME')]
    const ENABLE = 'views:enable';
    #[Deprecated(reason: 'Use ViewsDisableCommand::NAME')]
    const DISABLE = 'views:disable';
}
