<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\CronInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Url;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Drush\Drupal\DrupalUtil;
use JetBrains\PhpStorm\Deprecated;

final class DrupalCommands extends DrushCommands
{
    use AutowireTrait;

    #[Deprecated(reason: 'Use CronCommand::NAME')]
    const CRON = 'core:cron';
    #[Deprecated(reason: 'Use RequirementsCommand::NAME')]
    const REQUIREMENTS = 'core:requirements';
    #[Deprecated(reason: 'Use RouteCommand::NAME')]
    const ROUTE = 'core:route';
}
