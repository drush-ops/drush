<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;

/**
 * This Attribute is designed to be used with Console style commands, not Annotated Commands.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Formatter
{
    /**
     * @param ?string $returnType
     *     The command's return type, before formatting.
     * @param ?string $defaultFormatter
     *    The fallback formatter.
     */
    public function __construct(
        public ?string $returnType = null,
        public ?string $defaultFormatter = 'table',
    ) {
    }
}
