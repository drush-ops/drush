<?php

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Attributes\NoArgumentsBase;

#[Attribute(Attribute::TARGET_METHOD)]
class OptionsetTableSelection extends NoArgumentsBase
{
    const NAME = 'optionset_table_selection';
}
