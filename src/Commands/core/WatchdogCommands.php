<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Commands\watchdog\WatchdogDeleteCommand;
use Drush\Commands\watchdog\WatchdogListCommand;
use Drush\Commands\watchdog\WatchdogShowCommand;
use Drush\Commands\watchdog\WatchdogShowOneCommand;
use Drush\Commands\watchdog\WatchdogTailCommand;
use JetBrains\PhpStorm\Deprecated;

final class WatchdogCommands
{
    #[Deprecated(reason: 'Use WatchdogShowCommand::NAME')]
    const string SHOW = WatchdogShowCommand::NAME;
    #[Deprecated(reason: 'Use WatchdogListCommand::NAME')]
    const string LIST = WatchdogListCommand::NAME;
    #[Deprecated(reason: 'Use WatchdogTailCommand::NAME')]
    const string TAIL = WatchdogTailCommand::NAME;
    #[Deprecated(reason: 'Use WatchdogDeleteCommand::NAME')]
    const string DELETE = WatchdogDeleteCommand::NAME;
    #[Deprecated(reason: 'Use WatchdogShowCommand::NAME with --id; watchdog:show-one itself is deprecated')]
    const string SHOW_ONE = WatchdogShowOneCommand::NAME;
}
