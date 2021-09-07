<?php

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Attributes\Generic;

#[Attribute(Attribute::TARGET_METHOD)]
class OptionsetSsh extends Generic
{
    const NAME = 'optionset_ssh';
}
