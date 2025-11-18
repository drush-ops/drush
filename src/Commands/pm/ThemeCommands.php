<?php

declare(strict_types=1);

namespace Drush\Commands\pm;

use JetBrains\PhpStorm\Deprecated;

final class ThemeCommands
{
    #[Deprecated(reason: 'Use ThemeInstallCommand::NAME')]
    const string INSTALL = 'theme:install';
    #[Deprecated(reason: 'Use ThemeUninstallCommand::NAME')]
    const string UNINSTALL = 'theme:uninstall';
}
