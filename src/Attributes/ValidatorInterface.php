<?php

namespace Drush\Attributes;

use Consolidation\AnnotatedCommand\CommandData;

interface ValidatorInterface
{
    public static function validate(CommandData $commandData, \ReflectionAttribute $attribute);
}
