<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

#[CLI\Bootstrap(DrupalBootLevels::NONE)]
final class RsyncCommands extends DrushCommands
{
    /**
     * These are arguments after the aliases and paths have been evaluated.
     * @see validate().
     */
    #[Deprecated(reason: 'Moved', replacement: RsyncCommand::NAME)]
    const RSYNC = 'core:rsync';
}
