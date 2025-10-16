<?php

declare(strict_types=1);

namespace Drush\Commands\generate;

use Deprecated;
use Drush\Commands\DrushCommands;

final class GenerateCommands extends DrushCommands
{
    #[Deprecated('Use GenerateCommand::NAME')]
    const GENERATE = 'generate';
}
