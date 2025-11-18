<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use JetBrains\PhpStorm\Deprecated;

final class SiteCommands
{
    #[Deprecated(reason: 'Use SiteSetCommand::NAME')]
    const string SET = 'site:set';
    #[Deprecated(reason: 'Use SiteAliasCommand::NAME')]
    const string ALIAS = 'site:alias';
}
