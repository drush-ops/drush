<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;

#[Attribute(Attribute::TARGET_METHOD)]
class Obsolete extends NoArgumentsBase
{
    const NAME = 'obsolete';

    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        parent::handle($attribute, $commandInfo);
        $commandInfo->setHidden(true);
    }
}
