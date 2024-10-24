<?php

declare(strict_types=1);

namespace Drush\Commands\sql\sanitize;

use JetBrains\PhpStorm\Deprecated;

#[Deprecated('Moved to Drush\Commands\sql\sanitize\SanitizeCommand.')]
final class SanitizeCommands
{
    const SANITIZE = 'sql:sanitize';
    const CONFIRMS = 'sql-sanitize-confirms';
}
