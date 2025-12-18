<?php

declare(strict_types=1);

namespace Drush\Commands\field;

use JetBrains\PhpStorm\Deprecated;

final class FieldDefinitionCommands
{
    #[Deprecated(reason: 'Use FieldTypesCommand::NAME')]
    const string TYPES = FieldTypesCommand::NAME;

    #[Deprecated(reason: 'Use FieldWidgetsCommand::NAME')]
    const string WIDGETS = FieldWidgetsCommand::NAME;

    #[Deprecated(reason: 'Use FieldFormattersCommand::NAME')]
    const string FORMATTERS = FieldFormattersCommand::NAME;
}
