<?php

declare(strict_types=1);

namespace Drush\Commands\core;

use JetBrains\PhpStorm\Deprecated;

final class StateCommands
{
    #[Deprecated(reason: 'Use StateGetCommand::NAME')]
    const GET = 'state:get';
    #[Deprecated(reason: 'Use StateSetCommand::NAME')]
    const SET = 'state:set';
    #[Deprecated(reason: 'Use StateDeleteCommand::NAME')]
    const DELETE = 'state:delete';
}
