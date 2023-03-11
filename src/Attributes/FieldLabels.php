<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class FieldLabels extends \Consolidation\AnnotatedCommand\Attributes\FieldLabels
{
}
