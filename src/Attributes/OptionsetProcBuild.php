<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Drush\Commands\DrushCommands;

#[Attribute(Attribute::TARGET_METHOD)]
class OptionsetProcBuild
{
    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $commandInfo->addOption('ssh-options', 'A string of extra options that will be passed to the ssh command (e.g. <info>-p 100</info>)', [], DrushCommands::REQ);
        $commandInfo->addOption('tty', 'Create a tty (e.g. to run an interactive program).', [], false);
    }
}
