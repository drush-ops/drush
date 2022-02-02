<?php

namespace Drush\Symfony;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandProcessor;
use Consolidation\AnnotatedCommand\ParameterInjector;
use Drush\Style\DrushStyle;

class DrushStyleInjector implements ParameterInjector
{
    public function get(CommandData $commandData, $interfaceName): DrushStyle
    {
        return new DrushStyle($commandData->input(), $commandData->output());
    }
}
