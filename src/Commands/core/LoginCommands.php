<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

#[CLI\Bootstrap(DrupalBootLevels::NONE)]
final class LoginCommands extends DrushCommands
{
    #[Deprecated(reason: 'Use UserLoginCommand::NAME')]
    const LOGIN = 'user:login';
}
