<?php

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class OptionsetSql extends NoArgumentsBase
{
    const NAME = 'optionset_sql';
}
