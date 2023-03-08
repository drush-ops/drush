<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Argument extends \Consolidation\AnnotatedCommand\Attributes\Argument
{
}
