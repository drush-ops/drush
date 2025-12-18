<?php

declare(strict_types=1);

namespace Drush\Commands\field;

use JetBrains\PhpStorm\Deprecated;

final class FieldInfoCommands
{
    #[Deprecated(reason: 'Use FieldInfoCommand::NAME')]
    const string INFO = FieldInfoCommand::NAME;
}
