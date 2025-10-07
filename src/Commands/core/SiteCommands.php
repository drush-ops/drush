<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use JetBrains\PhpStorm\Deprecated;

final class SiteCommands
{
    #[Deprecated(reason: 'Use SiteSetCommand::NAME')]
    const SET = 'site:set';
    #[Deprecated(reason: 'Use SiteAliasCommand::NAME')]
    const ALIAS = 'site:alias';
}
