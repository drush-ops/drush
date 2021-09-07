<?php

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Attributes\Generic;

#[Attribute(Attribute::TARGET_METHOD)]
class OptionsetGetEditor extends Generic
{
    const NAME = 'optionset_get_editor';
}
