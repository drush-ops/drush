<?php

declare(strict_types=1);

namespace Drush\Commands\field;

use JetBrains\PhpStorm\Deprecated;

final class FieldCreateCommands
{
    #[Deprecated(reason: 'Use FieldBaseOverrideCreateCommand::NAME')]
    const string CREATE = FieldCreateCommand::NAME;
}
