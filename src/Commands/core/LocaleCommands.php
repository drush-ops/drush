<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use JetBrains\PhpStorm\Deprecated;

final class LocaleCommands
{
    #[Deprecated(reason: 'Use LocaleCheckCommand::NAME')]
    const string CHECK = 'locale:check';
    #[Deprecated(reason: 'Use LocaleClearStatusCommand::NAME')]
    const string CLEAR = 'locale:clear-status';
    #[Deprecated(reason: 'Use LocaleUpdateCommand::NAME')]
    const string UPDATE = 'locale:update';
    #[Deprecated(reason: 'Use LocaleExportCommand::NAME')]
    const string EXPORT = 'locale:export';
    #[Deprecated(reason: 'Use LocaleImportCommand::NAME')]
    const string IMPORT = 'locale:import';
    #[Deprecated(reason: 'Use LocaleImportAllCommand::NAME')]
    const string IMPORT_ALL = 'locale:import-all';
}
