<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

final class CliCommands extends DrushCommands
{
    #[Deprecated(reason: 'Use PhpCliCommand::NAME')]
    const PHP = 'php:cli';
}
