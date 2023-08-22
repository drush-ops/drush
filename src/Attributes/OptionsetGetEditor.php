<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Drush\Commands\DrushCommands;

#[Attribute(Attribute::TARGET_METHOD)]
class OptionsetGetEditor
{
    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $commandInfo->addOption('editor', 'A string of bash which launches user\'s preferred text editor. Defaults to <info>${VISUAL-${EDITOR-vi}}</info>.', [], DrushCommands::REQ);
        $commandInfo->addOption('bg', 'Launch editor in background process.', [], false);
    }
}
