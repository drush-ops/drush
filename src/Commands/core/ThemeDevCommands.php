<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Deprecated;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

final class ThemeDevCommands extends DrushCommands
{
    use AutowireTrait;

    #[Deprecated('Use ThemeDevCommand::NAME')]
    const DEV = 'theme:dev';
}
