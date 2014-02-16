<?php

/**
 * JSON cache storage backend.
 */
namespace Drush\Cache;
class JSONCache extends FileCache {
  const EXTENSION = '.json';

  function readFile($filename) {
    $item = file_get_contents($filename);
    return $item ? (object)drush_json_decode($item) : FALSE;
  }

  function writeFile($filename, $cache) {
    return file_put_contents($filename, drush_json_encode($cache));
  }
}
