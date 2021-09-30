<?php

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Topics extends \Consolidation\AnnotatedCommand\Attributes\Topics
{
}
