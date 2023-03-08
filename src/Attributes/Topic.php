<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Topic extends \Consolidation\AnnotatedCommand\Attributes\Topic
{
}
