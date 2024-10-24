<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated(replacement: 'Call \Drush\Formatters\FormatterTrait::configureFormatter during configure()')]
#[Attribute(Attribute::TARGET_METHOD)]
class DefaultFields extends \Consolidation\AnnotatedCommand\Attributes\DefaultFields
{
}
