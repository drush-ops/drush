<?php

namespace Drush\Attributes;

use Consolidation\AnnotatedCommand\Attributes\AttributeInterface;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;

abstract class NoArgumentsBase
{
    protected const NAME = 'annotation-name';

    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $commandInfo->addAnnotation(static::NAME, null);
    }
}
