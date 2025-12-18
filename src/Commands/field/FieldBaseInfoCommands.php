<?php

declare(strict_types=1);

namespace Drush\Commands\field;

use JetBrains\PhpStorm\Deprecated;

final class FieldBaseInfoCommands
{
    #[Deprecated(reason: 'Use FieldBaseInfoCommand::NAME')]
    const string BASE_INFO = FieldBaseInfoCommand::NAME;
}
