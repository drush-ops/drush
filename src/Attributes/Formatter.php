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
     *     The command's return type, before formatting. Usually this is a class
     *     name like RowsOfFields::class.
     * @param ?string $defaultFormatter
     *    The fallback formatter name. See the list at
     *    https://github.com/consolidation/output-formatters/blob/a112df9a74854c8438b33b334ed619fa43edf31a/src/FormatterManager.php#L43-L57
     */
    public function __construct(
        public ?string $returnType = null,
        public ?string $defaultFormatter = 'table',
    ) {
    }
}
