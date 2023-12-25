<?php

namespace Drush\Attributes;

use Consolidation\AnnotatedCommand\CommandData;

interface ValidatorInterface
{
    public function validate(CommandData $commandData);
}
