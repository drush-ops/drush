<?php

/**
 * @file
 * Definition of Drush\Cache\JSONCache.
 */

namespace Drush\Cache;

/**
 * JSON cache storage backend.
 */
class JSONCache extends FileCache
{
    const EXTENSION = '.json';

    public function readFile($filename)
    {
        $item = file_get_contents($filename);
        return $item ? (object)json_decode($item, true) : false;
    }

    public function writeFile($filename, $cache)
    {
        $json = json_encode($cache, JSON_PRETTY_PRINT);
        // json_encode() does not escape <, > and &, so we do it with str_replace().
        $json = str_replace(array('<', '>', '&'), array('\u003c', '\u003e', '\u0026'), $json);
        return file_put_contents($filename, $json);
    }
}
