<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

final class DrupalDirectoryCommands extends DrushCommands
{
    use AutowireTrait;

    #[Deprecated(reason: 'Use DrupalDirectoryCommand::NAME')]
    const string DIRECTORY = 'drupal:directory';
}
