<?php

declare(strict_types=1);

namespace Drush\Commands\sql\sanitize;

use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

#[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
final class SanitizeCommands extends DrushCommands
{
    #[Deprecated('This constant will soon move to a new class. Use the string value instead.')]
    const SANITIZE = 'sql:sanitize';
    #[Deprecated('This constant will soon move to a new class. Use the string value instead.')]
    const CONFIRMS = 'sql-sanitize-confirms';
}
