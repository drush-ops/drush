<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use JetBrains\PhpStorm\Deprecated;

final class RunserverCommands
{
    #[Deprecated(reason: 'Use RunserverCommand::NAME')]
    const RUNSERVER = 'runserver';
}
