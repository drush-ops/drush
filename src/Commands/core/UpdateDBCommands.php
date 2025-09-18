<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

#[CLI\Bootstrap(DrupalBootLevels::NONE)]
final class UpdateDBCommands extends DrushCommands
{
    use AutowireTrait;

    #[Deprecated('Moved. Use \Drush\Commands\core\UpdateDbCommand::NAME')]
    const UPDATEDB = 'updatedb';
    #[Deprecated('Moved. Use \Drush\Commands\core\UpdateDbStatusCommand::NAME')]
    const STATUS = 'updatedb:status';
    #[Deprecated('Moved. Use \Drush\Commands\core\UpdateDbBatchProcessCommand::NAME')]
    const BATCH_PROCESS = 'updatedb:batch-process';
}
