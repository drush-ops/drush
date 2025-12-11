<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use JetBrains\PhpStorm\Deprecated;

// @todo Create a listeners docs page.
#[Deprecated('Use Listeners instead. See https://www.drush.org/latest/listeners/')]
#[Attribute(Attribute::TARGET_METHOD)]
class Hook extends \Consolidation\AnnotatedCommand\Attributes\Hook
{
}
