<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;

/**
 * Mark commands as obsolete. These commands are omitted from help list and when
 * user tries to run one, the command's description is shown. Example usage at https://github.com/drush-ops/drush/blob/13.x/src/Commands/LegacyCommands.php
 */
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
