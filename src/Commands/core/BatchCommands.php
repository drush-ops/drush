<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

class BatchCommands extends DrushCommands
{
    #[Deprecated(replacement: 'BatchProcessCommand::NAME')]
    const PROCESS = 'batch:process';
}
