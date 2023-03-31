<?php

declare(strict_types=1);

namespace Drush\Command;

use Consolidation\AnnotatedCommand\CommandInfoAltererInterface;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Drush\Commands\core\DocsCommands;

class DrushCommandInfoAlterer implements CommandInfoAltererInterface
{
    public function alterCommandInfo(CommandInfo $commandInfo, $commandFileInstance): void
    {
        // If a command has a @filter-default-field annotation, that
        // implies that it also has an implicit @filter-output annotation.
        if ($commandInfo->hasAnnotation('filter-default-field') && !$commandInfo->hasAnnotation('filter-output')) {
            $commandInfo->addAnnotation('filter-output', true);
        }
        // Automatically add the help topic for output formatters to
        // any command that has any annotations related to output filters
        if ($commandInfo->hasAnnotation('filter-output') || $commandInfo->hasAnnotation('field-labels')) {
            if ($commandInfo->hasAnnotation('topics')) {
                // Topic value may have multiple values separated by a comma.
                $values = $commandInfo->getAnnotationList('topics');
                $commandInfo->removeAnnotation('topics');
                $commandInfo->addAnnotation('topics', $values);
            }
            $commandInfo->addAnnotation('topics', DocsCommands::OUTPUT_FORMATS_FILTERS);
        }
    }
}
