<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

final class DrupalCommands extends DrushCommands
{
    use AutowireTrait;

    #[Deprecated(reason: 'Use CronCommand::NAME')]
    const string CRON = 'core:cron';
    #[Deprecated(reason: 'Use RequirementsCommand::NAME')]
    const string REQUIREMENTS = 'core:requirements';
    #[Deprecated(reason: 'Use RouteCommand::NAME')]
    const string ROUTE = 'core:route';
}
