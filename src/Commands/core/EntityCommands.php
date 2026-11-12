<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\Input\StdinAwareInterface;
use Consolidation\AnnotatedCommand\Input\StdinAwareTrait;
use Drush\Commands\DrushCommands;
use Drush\Commands\entity\EntityDeleteCommand;
use Drush\Commands\entity\EntitySaveCommand;
use JetBrains\PhpStorm\Deprecated;

final class EntityCommands extends DrushCommands implements StdinAwareInterface
{
    use StdinAwareTrait;

    #[Deprecated(reason: 'Use EntityDeleteCommand::NAME')]
    const string DELETE = EntityDeleteCommand::NAME;
    #[Deprecated(reason: 'Use EntitySaveCommand::NAME')]
    const string SAVE = EntitySaveCommand::NAME;
}
