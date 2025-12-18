<?php

declare(strict_types=1);

namespace Drush\Commands\field;

use JetBrains\PhpStorm\Deprecated;

final class FieldBaseOverrideCreateCommands
{
    #[Deprecated(reason: 'Use FieldBaseOverrideCreateCommand::NAME')]
    const string BASE_OVERRIDE_CREATE = FieldBaseOverrideCreateCommand::NAME;
}
