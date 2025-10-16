<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Deprecated;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Drush\Exec\ExecTrait;

#[CLI\Bootstrap(DrupalBootLevels::NONE)]
final class SiteInstallCommands extends DrushCommands
{
    use ExecTrait;

    #[Deprecated('Use SiteInstallCommand::NAME instead.')]
    const INSTALL = 'site:install';
}
