<?php

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Attributes\Generic;

#[Attribute(Attribute::TARGET_METHOD)]
class OptionsetTableSelection extends Generic
{
    const NAME = 'optionset_table_selection';
}
