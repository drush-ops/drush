<?php

declare(strict_types=1);

namespace Drush\Commands\sql;

use JetBrains\PhpStorm\Deprecated;

final class SqlSyncCommands
{
    #[Deprecated(reason: 'Use SqlSyncCommand::NAME')]
    const SYNC = 'sql:sync';
}
