<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

#[CLI\Bootstrap(DrupalBootLevels::NONE)]
final class CacheRebuildCommands extends DrushCommands
{
    use AutowireTrait;

    #[Deprecated(reason: 'Use CacheRebuildCommand::NAME')]
    const REBUILD = 'cache:rebuild';
}
