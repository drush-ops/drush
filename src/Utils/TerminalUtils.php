<?php

namespace Drush\Utils;

class TerminalUtils
{
    public static function stdinIsTerminal($default = true)
    {
        if (!function_exists('posix_isatty')) {
            return $default;
        }

        return posix_isatty(STDIN);
    }
}
