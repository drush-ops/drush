<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class TableEmptyMessage extends \Consolidation\AnnotatedCommand\Attributes\TableEmptyMessage
{
}
