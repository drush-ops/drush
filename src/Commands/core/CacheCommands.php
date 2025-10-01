<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

/*
 * Interact with Drupal's Cache API.
 */
final class CacheCommands extends DrushCommands
{
    #[Deprecated(reason: 'Use CacheGetCommand::NAME')]
    const GET = 'cache:get';
    #[Deprecated(reason: 'Use CacheTagsCommand::NAME')]
    const TAGS = 'cache:tags';
    #[Deprecated(reason: 'Use CacheClearCommand::NAME')]
    const CLEAR = 'cache:clear';
    #[Deprecated(reason: 'Use CacheSetCommand::NAME')]
    const SET = 'cache:set';
    #[Deprecated(reason: 'Use CacheRebuildCommand::NAME')]
    const REBUILD = 'cache:rebuild';
    #[Deprecated(reason: 'Use CacheClearCommand::EVENT_CLEAR')]
    const EVENT_CLEAR = 'cache-clear';
}
