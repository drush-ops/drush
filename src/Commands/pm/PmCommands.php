<?php

declare(strict_types=1);

namespace Drush\Commands\pm;

use JetBrains\PhpStorm\Deprecated;

final class PmCommands
{
    #[Deprecated(reason: 'Use PmInstallCommand::NAME')]
    const string INSTALL = 'pm:install';
    #[Deprecated(reason: 'Use PmUninstallCommand::NAME')]
    const string UNINSTALL = 'pm:uninstall';
    #[Deprecated(reason: 'Use PmListCommand::NAME')]
    const string LIST = 'pm:list';
}
