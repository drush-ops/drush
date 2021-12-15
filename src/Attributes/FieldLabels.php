<?php

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class FieldLabels extends \Consolidation\AnnotatedCommand\Attributes\FieldLabels
{
}
