<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

final class CoreCommands extends DrushCommands
{
    #[Deprecated(reason: 'Use VersionCommand::NAME')]
    const VERSION = 'version';
    #[Deprecated(reason: 'Use CoreGlobalOptionsCommand::NAME')]
    const GLOBAL_OPTIONS = 'core:global-options';
}
