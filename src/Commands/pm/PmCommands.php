<?php

declare(strict_types=1);

namespace Drush\Commands\pm;

use JetBrains\PhpStorm\Deprecated;

final class PmCommands
{
    #[Deprecated(reason: 'Use PmInstallCommand::NAME')]
    const INSTALL = 'pm:install';
    #[Deprecated(reason: 'Use PmUninstallCommand::NAME')]
    const UNINSTALL = 'pm:uninstall';
    #[Deprecated(reason: 'Use PmListCommand::NAME')]
    const LIST = 'pm:list';
}
