<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated('Use complete() method instead on a Console Command. See https://www.drush.org/latest/commands/')]
#[Attribute(Attribute::TARGET_METHOD)]
class Complete extends \Consolidation\AnnotatedCommand\Attributes\Complete
{
}
