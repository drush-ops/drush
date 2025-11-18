<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use Drush\Commands\DrushCommands;
use JetBrains\PhpStorm\Deprecated;

final class EditCommands extends DrushCommands
{
    #[Deprecated(reason: 'Use EditCommand::NAME')]
    const string EDIT = 'core:edit';
}
