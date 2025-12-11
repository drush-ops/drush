<?php

declare(strict_types=1);

namespace Drush\Commands\field;

use JetBrains\PhpStorm\Deprecated;

final class FieldDefinitionCommands
{
    #[Deprecated(reason: 'Use FieldTypesCommand::NAME')]
    const string TYPES = 'field:types';

    #[Deprecated(reason: 'Use FieldWidgetsCommand::NAME')]
    const string WIDGETS = 'field:widgets';

    #[Deprecated(reason: 'Use FieldFormattersCommand::NAME')]
    const string FORMATTERS = 'field:formatters';
}
