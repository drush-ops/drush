<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use JetBrains\PhpStorm\Deprecated;

final class ViewsCommands
{
    #[Deprecated(reason: 'Use ViewsDevCommand::NAME')]
    const string DEV = 'views:dev';
    #[Deprecated(reason: 'Use ViewsExecuteCommand::NAME')]
    const string EXECUTE = 'views:execute';
    #[Deprecated(reason: 'Use ViewsListCommand::NAME')]
    const string LIST = 'views:list';
    #[Deprecated(reason: 'Use ViewsEnableCommand::NAME')]
    const string ENABLE = 'views:enable';
    #[Deprecated(reason: 'Use ViewsDisableCommand::NAME')]
    const string DISABLE = 'views:disable';
}
