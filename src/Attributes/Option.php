<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated('Use a native Console command and its configure() method instead. See https://www.drush.org/latest/commands/')]
#[Attribute(Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Option extends \Consolidation\AnnotatedCommand\Attributes\Option
{
}
