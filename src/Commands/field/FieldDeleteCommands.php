<?php

declare(strict_types=1);

namespace Drush\Commands\field;

use JetBrains\PhpStorm\Deprecated;

final class FieldDeleteCommands
{
    #[Deprecated(reason: 'Use FieldDeleteCommand::NAME')]
    const string DELETE = FieldDeleteCommand::NAME;
}
