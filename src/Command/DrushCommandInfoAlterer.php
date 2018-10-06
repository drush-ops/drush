<?php
namespace Drush\Command;

use Consolidation\AnnotatedCommand\CommandInfoAltererInterface;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;

class DrushCommandInfoAlterer implements CommandInfoAltererInterface
{
    public function alterCommandInfo(CommandInfo $commandInfo, $commandFileInstance)
    {
        // If a command has a @filter-default-field annotation, that
        // implies that it also has an implicit @filter-output annotation.
        if ($commandInfo->hasAnnotation('filter-default-field') && !$commandInfo->hasAnnotation('filter-output')) {
            $commandInfo->addAnnotation('filter-output', true);
        }
    }
}
