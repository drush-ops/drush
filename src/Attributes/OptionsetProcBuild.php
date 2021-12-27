<?php

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class OptionsetProcBuild extends NoArgumentsBase
{
    const NAME = 'optionset_proc_build';
}
