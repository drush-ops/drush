<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated('Create an Attribute class that commands can use.')]
#[Attribute(Attribute::TARGET_METHOD  | \Attribute::IS_REPEATABLE)]
class HookSelector extends \Consolidation\AnnotatedCommand\Attributes\HookSelector
{
}
