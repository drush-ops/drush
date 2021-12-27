<?php

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Help extends \Consolidation\AnnotatedCommand\Attributes\Help
{
}
