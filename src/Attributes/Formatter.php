<?php

declare(strict_types=1);

namespace Drush\Attributes;

use Attribute;
use Consolidation\AnnotatedCommand\Parser\CommandInfo;
use Drush\Commands\DrushCommands;

#[Attribute(Attribute::TARGET_CLASS)]
class Formatter
{
    /**
     * @param string $returnType
     *     The command's return type, before formatting.
     * @param ?string $defaultFormatter
     *    The fallback formatter.
     */
    public function __construct(
        public string $returnType,
        public ?string $defaultFormatter = 'table',
    ) {
    }
}
