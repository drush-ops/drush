<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use JetBrains\PhpStorm\Deprecated;

final class LocaleCommands
{
    #[Deprecated(reason: 'Use LocaleCheckCommand::NAME')]
    const CHECK = 'locale:check';
    #[Deprecated(reason: 'Use LocaleClearStatusCommand::NAME')]
    const CLEAR = 'locale:clear-status';
    #[Deprecated(reason: 'Use LocaleUpdateCommand::NAME')]
    const UPDATE = 'locale:update';
    #[Deprecated(reason: 'Use LocaleExportCommand::NAME')]
    const EXPORT = 'locale:export';
    #[Deprecated(reason: 'Use LocaleImportCommand::NAME')]
    const IMPORT = 'locale:import';
    #[Deprecated(reason: 'Use LocaleImportAllCommand::NAME')]
    const IMPORT_ALL = 'locale:import-all';
}
