<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use JetBrains\PhpStorm\Deprecated;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class DefaultTableFields extends \Consolidation\AnnotatedCommand\Attributes\DefaultTableFields
{
}
