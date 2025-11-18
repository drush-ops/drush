<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

final class TwigCommands extends DrushCommands
{
    use AutowireTrait;

    #[Deprecated('Use TwigCompileCommand::NAME instead.')]
    const string COMPILE = 'twig:compile';
    #[Deprecated('Use TwigUnusedCommand::UNUSED instead.')]
    const string UNUSED = 'twig:unused';
}
