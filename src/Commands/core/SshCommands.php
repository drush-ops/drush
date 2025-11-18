<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use JetBrains\PhpStorm\Deprecated;

final class SshCommands
{
    #[Deprecated(reason: 'Use SshCommand::NAME')]
    const string SSH = 'site:ssh';
}
