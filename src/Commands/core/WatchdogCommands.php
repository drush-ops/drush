<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use JetBrains\PhpStorm\Deprecated;

final class WatchdogCommands
{
    #[Deprecated(reason: 'Use WatchdogShowCommand::NAME')]
    const SHOW = 'watchdog:show';
    #[Deprecated(reason: 'Use WatchdogListCommand::NAME')]
    const LIST = 'watchdog:list';
    #[Deprecated(reason: 'Use WatchdogTailCommand::NAME')]
    const TAIL = 'watchdog:tail';
    #[Deprecated(reason: 'Use WatchdogDeleteCommand::NAME')]
    const DELETE = 'watchdog:delete';
    #[Deprecated(reason: 'Use WatchdogShowOneCommand::NAME')]
    const SHOW_ONE = 'watchdog:show-one';
}
