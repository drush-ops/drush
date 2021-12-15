<?php

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Option extends \Consolidation\AnnotatedCommand\Attributes\Option
{
}
