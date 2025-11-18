<?php

declare(strict_types=1);

namespace Drush\Commands\config;

use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

final class ConfigImportCommands extends DrushCommands
{
    #[Deprecated(reason: 'Use ConfigImportCommand::NAME')]
    const string IMPORT = 'config:import';
}
