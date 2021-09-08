<?php

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Attributes\NoArgumentsBase;

#[Attribute(Attribute::TARGET_METHOD)]
class OptionsetSql extends NoArgumentsBase
{
    const NAME = 'optionset_sql';
}
