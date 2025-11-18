<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

final class CoreCommands extends DrushCommands
{
    #[Deprecated(reason: 'Use VersionCommand::NAME')]
    const string VERSION = 'version';
    #[Deprecated(reason: 'Use CoreGlobalOptionsCommand::NAME')]
    const string GLOBAL_OPTIONS = 'core:global-options';
}
