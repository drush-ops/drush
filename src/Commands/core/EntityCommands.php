<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\Input\StdinAwareInterface;
use Consolidation\AnnotatedCommand\Input\StdinAwareTrait;
use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

final class EntityCommands extends DrushCommands implements StdinAwareInterface
{
    use StdinAwareTrait;

    #[Deprecated(reason: 'Use EntityDeleteCommand::NAME')]
    const DELETE = 'entity:delete';
    #[Deprecated(reason: 'Use EntitySaveCommand::NAME')]
    const SAVE = 'entity:save';
}
