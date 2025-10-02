<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\SiteAlias\HostPath;
use Consolidation\SiteAlias\SiteAliasManagerInterface;
use Drush\Attributes as CLI;
use Drush\Backend\BackendPathEvaluator;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

final class DrupalDirectoryCommands extends DrushCommands
{
    use AutowireTrait;

    #[Deprecated(reason: 'Use DrupalDirectoryCommand::NAME')]
    const DIRECTORY = 'drupal:directory';



}
