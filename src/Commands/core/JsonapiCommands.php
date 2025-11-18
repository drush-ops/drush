<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

final class JsonapiCommands extends DrushCommands
{
    #[Deprecated(reason: 'Use JsonapiGetCommand::NAME')]
    const string GET = 'jn:get';
}
