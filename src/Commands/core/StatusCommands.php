<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Deprecated;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;

#[CLI\Bootstrap(DrupalBootLevels::NONE)]
final class StatusCommands extends DrushCommands
{
    #[Deprecated('Use StatusCommand::NAME')]
    const STATUS = 'core:status';
}
