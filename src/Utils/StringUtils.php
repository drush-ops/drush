<?php

namespace Drush\Utils;

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
     * @returns array
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
     */
    public static function interpolate($message, array $context = [])
    {
        // Take no action if there is no context
        if (empty($context)) {
            return $message;
        }

        // build a replacement array with braces around the context keys
        $replace = array();
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
     */
    private static function interpolationKey($key)
    {
        if (ctype_alpha($key)) {
            return sprintf('{%s}', $key);
        }
        return $key;
    }
}
