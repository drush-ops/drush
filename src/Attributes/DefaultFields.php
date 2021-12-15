<?php

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class DefaultFields extends \Consolidation\AnnotatedCommand\Attributes\DefaultFields
{
}
