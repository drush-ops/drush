<?php

namespace Drush\Command;

/**
 * A value object for Symfony Console hyperlinks.
 */
class ConsoleLink
{
    public function __construct(
        public string $path,
        public string $text,
    ) {
    }
}
