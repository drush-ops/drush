<?php

namespace Drush\Utils;

class TerminalUtils
{
    /**
     * isTty determines if the STDIN stream is a TTY. We use this function
     * to determine whether or not we should redirect input of a process
     * using our STDIN stream. If we cannot tell, then we return a default value.
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

    /**
     * useTty determines if both the STDIN and STDOUT streams connect to a TTY.
     * We use this to determine whether we should use tty mode with our process
     * component. If we cannot tell, then we return a default value.
     *
     * @param bool $default Result to assume when the posix functions
     *   are not available.
     * @return bool
     */
    public static function useTty($default = true)
    {
        if (!function_exists('posix_isatty')) {
            return $default;
        }

        return posix_isatty(STDOUT) && posix_isatty(STDIN);
    }
}
