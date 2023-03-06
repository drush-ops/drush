<?php

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;

#[Attribute(Attribute::TARGET_METHOD)]
class OptionsetGetEditor
{
    public static function handle(\ReflectionAttribute $attribute, CommandInfo $commandInfo)
    {
        $commandInfo->addOptionOrArgumentDescription($commandInfo->options(), 'editor' ,'A string of bash which launches user\'s preferred text editor. Defaults to <info>${VISUAL-${EDITOR-vi}}</info>.', [],'');
        $commandInfo->addOptionOrArgumentDescription($commandInfo->options(), 'bg' ,'Launch editor in background process.', [], false);
    }
}
