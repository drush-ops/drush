<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

final class MaintCommands extends DrushCommands
{
    const KEY = 'system.maintenance_mode';
    #[Deprecated(reason: 'Use MaintGetCommand::NAME')]
    const GET = 'maint:get';
    #[Deprecated(reason: 'Use MaintSetCommand::NAME')]
    const SET = 'maint:set';
    #[Deprecated(reason: 'Use MaintStatusCommand::NAME')]
    const STATUS = 'maint:status';
}
