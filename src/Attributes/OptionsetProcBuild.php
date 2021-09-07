<?php

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Attributes\Generic;

#[Attribute(Attribute::TARGET_METHOD)]
class OptionsetProcBuild extends Generic
{
    const NAME = 'optionset_proc_build';
}
