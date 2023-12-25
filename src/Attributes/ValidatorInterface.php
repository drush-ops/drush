<?php

namespace Drush\Attributes;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;

interface ValidatorInterface
{
    public function validate(CommandData $commandData): ?CommandError;
}
