<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated('Use #[AsCommand] instead. See https://www.drush.org/latest/commands/')]
#[Attribute(Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Argument extends \Consolidation\AnnotatedCommand\Attributes\Argument
{
}
