<?php

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class OptionsetTableSelection extends NoArgumentsBase
{
    const NAME = 'optionset_table_selection';
}
