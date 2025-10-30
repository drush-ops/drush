<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated('Use configure() method instead. See https://www.drush.org/latest/commands/')]
#[Attribute(Attribute::TARGET_METHOD)]
class Help extends \Consolidation\AnnotatedCommand\Attributes\Help
{
}
