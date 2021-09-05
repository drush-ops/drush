<?php

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Attributes\AttributeInterface;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;

#[Attribute(Attribute::TARGET_METHOD)]
class OptionsetGetEditor extends Generic
{
    const NAME = 'optionset_get_editor';
}
