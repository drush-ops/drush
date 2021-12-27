<?php

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Usage extends \Consolidation\AnnotatedCommand\Attributes\Usage
{
}
