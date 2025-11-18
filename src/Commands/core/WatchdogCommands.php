<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use JetBrains\PhpStorm\Deprecated;

final class WatchdogCommands
{
    #[Deprecated(reason: 'Use WatchdogShowCommand::NAME')]
    const string SHOW = 'watchdog:show';
    #[Deprecated(reason: 'Use WatchdogListCommand::NAME')]
    const string LIST = 'watchdog:list';
    #[Deprecated(reason: 'Use WatchdogTailCommand::NAME')]
    const string TAIL = 'watchdog:tail';
    #[Deprecated(reason: 'Use WatchdogDeleteCommand::NAME')]
    const string DELETE = 'watchdog:delete';
    #[Deprecated(reason: 'Use WatchdogShowOneCommand::NAME')]
    const string SHOW_ONE = 'watchdog:show-one';
}
