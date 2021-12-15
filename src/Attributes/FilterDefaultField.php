<?php

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class FilterDefaultField extends \Consolidation\AnnotatedCommand\Attributes\FilterDefaultField
{
}
