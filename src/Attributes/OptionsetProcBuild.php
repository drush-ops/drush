<?php

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Attributes\NoArgumentsBase;

#[Attribute(Attribute::TARGET_METHOD)]
class OptionsetProcBuild extends NoArgumentsBase
{
    const NAME = 'optionset_proc_build';
}
