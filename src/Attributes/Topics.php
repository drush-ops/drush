<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated('Use Help Links when converting to a Console command. See https://www.drush.org/latest/commands/')]
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Topics extends \Consolidation\AnnotatedCommand\Attributes\Topics
{
}
