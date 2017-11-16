<?php

namespace Drush\Utils;

use Drush\Drush;

class StringUtils
{

    /**
     * Convert a csv string, or an array of items which
     * may contain csv strings, into an array of items.
     *
     * @param $args
     *   A simple csv string; e.g. 'a,b,c'
     *   or a simple list of items; e.g. array('a','b','c')
     *   or some combination; e.g. array('a,b','c') or array('a,','b,','c,')
     *
     * @return array
     *   A simple list of items (e.g. array('a','b','c')
     */
    public static function csvToArray($args)
    {
        //
        // Step 1: implode(',',$args) converts from, say, array('a,','b,','c,') to 'a,,b,,c,'
        // Step 2: explode(',', ...) converts to array('a','','b','','c','')
        // Step 3: array_filter(...) removes the empty items
        // Step 4: array_map(...) trims extra whitespace from each item
        // (handles csv strings with extra whitespace, e.g. 'a, b, c')
        //
        return array_map('trim', array_filter(explode(',', is_array($args) ? implode(',', $args) : $args)));
    }

    /**
     * Replace placeholders in a string.
     *
     * Examples:
     *   interpolate('Hello, {var}', ['var' => 'world']) ==> 'Hello, world'
     *   interpolate('Do !what', ['!what' => 'work'])    ==> 'Do work'
     *
     * @param string $message
     *   The string with placeholders to be interpolated.
     * @param array $context
     *   An associative array of values to be inserted into the message.
     * @return string
     *   The resulting string with all placeholders filled in.
     */
    public static function interpolate($message, array $context = [])
    {
        // Take no action if there is no context
        if (empty($context)) {
            return $message;
        }

        // build a replacement array with braces around the context keys
        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace[static::interpolationKey($key)] = $val;
            }
        }

        // interpolate replacement values into the message and return
        return strtr($message, $replace);
    }

    /**
     * Wrap simple strings (with no special characters) in {}s
     *
     * @param string $key
     *   A key from an interpolation context.
     * @return string
     *   The key prepared for interpolation.
     */
    private static function interpolationKey($key)
    {
        if (ctype_alpha($key)) {
            return sprintf('{%s}', $key);
        }
        return $key;
    }

    /**
     * Replace tilde in a path with the HOME directory.
     *
     * @param $path
     *   A path that may contain a ~ at front.
     *
     * @param $home
     *   The effective home dir for this request.
     *
     * @return string The path with tilde replaced, if applicable.
     * The path with tilde replaced, if applicable.
     */
    public static function replaceTilde($path, $home)
    {
        $replacement = $home . '/';
        $match = '#^~/#';
        if (preg_match($match, $path)) {
            return preg_replace($match, $replacement, $path);
        }
        return $path;
    }
}
