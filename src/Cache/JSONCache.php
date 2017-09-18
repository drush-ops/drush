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
        return $item ? (object)drush_json_decode($item) : false;
    }

    public function writeFile($filename, $cache)
    {
        return file_put_contents($filename, drush_json_encode($cache));
    }
}
