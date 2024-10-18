<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class FilterDefaultField extends \Consolidation\AnnotatedCommand\Attributes\FilterDefaultField
{
}
