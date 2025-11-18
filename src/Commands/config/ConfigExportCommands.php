<?php

declare(strict_types=1);

namespace Drush\Commands\config;

use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

final class ConfigExportCommands extends DrushCommands
{
    #[Deprecated(reason: 'Use ConfigExportCommand::NAME')]
    const string EXPORT = 'config:export';
}
