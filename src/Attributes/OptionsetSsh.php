<?php

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class OptionsetSsh extends NoArgumentsBase
{
    const NAME = 'optionset_ssh';
}
