<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Drush\Internal\Config\Yaml;

use Drush\Internal\Config\Yaml\Exception\ParseException;

/**
 * Yaml offers convenience methods to load and dump YAML.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Yaml
{
    const DUMP_OBJECT = 1;
    const PARSE_EXCEPTION_ON_INVALID_TYPE = 2;
    const PARSE_OBJECT = 4;
    const PARSE_OBJECT_FOR_MAP = 8;
    const DUMP_EXCEPTION_ON_INVALID_TYPE = 16;
    const PARSE_DATETIME = 32;
    const DUMP_OBJECT_AS_MAP = 64;
    const DUMP_MULTI_LINE_LITERAL_BLOCK = 128;
    const PARSE_CONSTANT = 256;
    const PARSE_CUSTOM_TAGS = 512;
    const DUMP_EMPTY_ARRAY_AS_SEQUENCE = 1024;
    const PARSE_KEYS_AS_STRINGS = 2048;

    /**
     * Parses YAML into a PHP value.
     *
     *  Usage:
     *  <code>
     *   $array = Yaml::parse(file_get_contents('config.yml'));
     *   print_r($array);
     *  </code>
     *
     * @param string $input A string containing YAML
     * @param int    $flags A bit field of PARSE_* constants to customize the YAML parser behavior
     *
     * @return mixed The YAML converted to a PHP value
     *
     * @throws ParseException If the YAML is not valid
     */
    public static function parse($input, $flags = 0)
    {
        if (is_bool($flags)) {
            @trigger_error('Passing a boolean flag to toggle exception handling is deprecated since version 3.1 and will be removed in 4.0. Use the PARSE_EXCEPTION_ON_INVALID_TYPE flag instead.', E_USER_DEPRECATED);

            if ($flags) {
                $flags = self::PARSE_EXCEPTION_ON_INVALID_TYPE;
            } else {
                $flags = 0;
            }
        }

        if (func_num_args() >= 3) {
            @trigger_error('Passing a boolean flag to toggle object support is deprecated since version 3.1 and will be removed in 4.0. Use the PARSE_OBJECT flag instead.', E_USER_DEPRECATED);

            if (func_get_arg(2)) {
                $flags |= self::PARSE_OBJECT;
            }
        }

        if (func_num_args() >= 4) {
            @trigger_error('Passing a boolean flag to toggle object for map support is deprecated since version 3.1 and will be removed in 4.0. Use the Yaml::PARSE_OBJECT_FOR_MAP flag instead.', E_USER_DEPRECATED);

            if (func_get_arg(3)) {
                $flags |= self::PARSE_OBJECT_FOR_MAP;
            }
        }

        $yaml = new Parser();

        return $yaml->parse($input, $flags);
    }
}
