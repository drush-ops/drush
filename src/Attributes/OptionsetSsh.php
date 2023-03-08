<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Drush\Commands\DrushCommands;

#[Attribute(Attribute::TARGET_METHOD)]
class OptionsetSsh
{
    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $commandInfo->addOption('ssh-options', 'A string appended to ssh command during rsync, sql-sync, etc.', [], DrushCommands::REQ);
    }
}
