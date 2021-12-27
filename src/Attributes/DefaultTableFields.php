<?php

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class DefaultTableFields extends \Consolidation\AnnotatedCommand\Attributes\DefaultTableFields
{
}
