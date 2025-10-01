<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class HelpLinks
{
    /**
     * @param \Drush\Command\HelpLinks[] $links
     *  An array of HelpLink Enum references which get appended to detailed help output.
     */
    public function __construct(
        public array $links,
    ) {
    }
}
