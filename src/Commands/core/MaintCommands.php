<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

final class MaintCommands extends DrushCommands
{
    const string KEY = 'system.maintenance_mode';
    #[Deprecated(reason: 'Use MaintGetCommand::NAME')]
    const string GET = 'maint:get';
    #[Deprecated(reason: 'Use MaintSetCommand::NAME')]
    const string SET = 'maint:set';
    #[Deprecated(reason: 'Use MaintStatusCommand::NAME')]
    const string STATUS = 'maint:status';
}
