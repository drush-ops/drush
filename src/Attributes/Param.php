<?php

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Param extends \Consolidation\AnnotatedCommand\Attributes\Param
{
}
