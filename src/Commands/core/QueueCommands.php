<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use JetBrains\PhpStorm\Deprecated;

final class QueueCommands
{
    #[Deprecated(reason: 'Use QueueRunCommand::NAME')]
    const RUN = 'queue:run';
    #[Deprecated(reason: 'Use QueueListCommand::NAME')]
    const LIST = 'queue:list';
    #[Deprecated(reason: 'Use QueueDeleteCommand::NAME')]
    const DELETE = 'queue:delete';
}
