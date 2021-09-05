<?php

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class OptionsetProcBuild extends Generic
{
    const NAME = 'optionset_proc_build';
}
