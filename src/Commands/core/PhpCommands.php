<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Consolidation\AnnotatedCommand\Input\StdinAwareInterface;
use Consolidation\AnnotatedCommand\Input\StdinAwareTrait;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

#[CLI\Bootstrap(DrupalBootLevels::NONE)]
final class PhpCommands extends DrushCommands implements StdinAwareInterface
{
    use StdinAwareTrait;

    #[Deprecated(reason: 'Use PhpScriptCommand::NAME')]
    const SCRIPT = 'php:script';
    #[Deprecated(reason: 'Use PhpEvalCommand::NAME')]
    const EVAL = 'php:eval';
}
