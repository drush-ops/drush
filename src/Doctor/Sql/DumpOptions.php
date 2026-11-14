<?php

declare(strict_types=1);

namespace Drush\Doctor\Sql;

/**
 * The flags that shape a dump command.
 */
final readonly class DumpOptions
{
    public function __construct(
        public bool $dataOnly = false,
        public bool $orderedDump = false,
        public bool $createDb = false,
        public ?string $extraDump = null,
    ) {
    }
}
