<?php

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Hook extends \Consolidation\AnnotatedCommand\Attributes\Hook
{
}
