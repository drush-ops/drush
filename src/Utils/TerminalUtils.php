<?php

namespace Drush\Utils;

class TerminalUtils
{
    /**
     * isTty determines if the STDIN stream is a TTY. If we cannot tell,
     * then we return a default value.
     *
     * @param bool $default Result to assume when the posix functions
     *   are not available.
     * @return bool
     */
    public static function isTty($default = true)
    {
        if (!function_exists('posix_isatty')) {
            return $default;
        }

        return posix_isatty(STDIN);
    }
}
