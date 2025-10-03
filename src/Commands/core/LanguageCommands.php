<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

final class LanguageCommands extends DrushCommands
{
    #[Deprecated(reason: 'Use LanguageAddCommand::NAME')]
    const ADD = 'language:add';
    #[Deprecated(reason: 'Use LanguageInfoCommand::NAME')]
    const INFO = 'language:info';
}
