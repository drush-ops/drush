<?php

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Command extends \Consolidation\AnnotatedCommand\Attributes\Command
{
}
