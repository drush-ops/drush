<?php

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class HookSelector extends \Consolidation\AnnotatedCommand\Attributes\HookSelector
{
}
