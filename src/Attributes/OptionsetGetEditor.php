<?php

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Attributes\NoArgumentsBase;

#[Attribute(Attribute::TARGET_METHOD)]
class OptionsetGetEditor extends NoArgumentsBase
{
    const NAME = 'optionset_get_editor';
}
