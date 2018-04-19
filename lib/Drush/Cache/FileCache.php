<?php

/**
 * @file
 * Definition of Drush\Cache\FileCache.
 */

namespace Drush\Cache;

/**
 * Default cache implementation.
 *
 * This cache implementation uses plain text files
 * containing serialized php to store cached data. Each cache bin corresponds
 * to a directory by the same name.
 */
class FileCache implements CacheInterface {
  const EXTENSION = '.cache';
  protected $bin;

  function __construct($bin) {
    $this->bin = $bin;
    $this->directory = $this->cacheDirectory();
  }

   /**
    * Returns the cache directory for the given bin.
    *
    * @param string $bin
    */
  function cacheDirectory($bin = NULL) {
    $bin = $bin ? $bin : $this->bin;
    return drush_directory_cache($bin);
  }

  function get($cid) {
    $cids = array($cid);
    $cache = $this->getMultiple($cids);
    return reset($cache);
  }

  function getMultiple(&$cids) {
    try {
      $cache = array();
      foreach ($cids as $cid) {
        $filename = $this->getFilePath($cid);
        if (!file_exists($filename)) throw new \Exception;

        $item = $this->readFile($filename);
        if ($item) {
          $cache[$cid] = $item;
        }
      }
      $cids = array_diff($cids, array_keys($cache));
      return $cache;
    }
    catch (\Exception $e) {
      return array();
    }
  }

  /**
   * Returns the contents of the given filename unserialized.
   *
   * @param string $filename
   *   Absolute path to filename to read contents from.
   */
  function readFile($filename) {
    $item = file_get_contents($filename);
    return $item ? unserialize($item) : FALSE;
  }

  function set($cid, $data, $expire = DRUSH_CACHE_PERMANENT) {
    $created = time();

    $cache = new \stdClass;
    $cache->cid = $cid;
    $cache->data = is_object($data) ? clone $data : $data;
    $cache->created = $created;
    if ($expire == DRUSH_CACHE_TEMPORARY) {
      $cache->expire = $created + 2591999;
    }
    // Expire time is in seconds if less than 30 days, otherwise is a timestamp.
    elseif ($expire != DRUSH_CACHE_PERMANENT && $expire < 2592000) {
      $cache->expire = $created + $expire;
    }
    else {
      $cache->expire = $expire;
    }

    // Ensure the cache directory still exists, in case a backend process
    // cleared the cache after the cache was initialized.
    drush_mkdir($this->directory);

    $filename = $this->getFilePath($cid);
    return $this->writeFile($filename, $cache);
  }

  /**
   * Serializes data and write it to the given filename.
   *
   * @param string $filename
   *   Absolute path to filename to write cache data.
   * @param $cache
   *   Cache data to serialize and write to $filename.
   */
  function writeFile($filename, $cache) {
    return file_put_contents($filename, serialize($cache));
  }

  function clear($cid = NULL, $wildcard = FALSE) {
    $bin_dir = $this->cacheDirectory();
    $files = array();
    if (empty($cid)) {
      drush_delete_dir($bin_dir, TRUE);
    }
    else {
      if ($wildcard) {
        if ($cid == '*') {
          drush_delete_dir($bin_dir, TRUE);
        }
        else {
          $matches = drush_scan_directory($bin_dir, "/^$cid/", array('.', '..'));
          $files = $files + array_keys($matches);
        }
      }
      else {
        $files[] = $this->getFilePath($cid);
      }

      foreach ($files as $f) {
        if (file_exists($f)) {
          unlink($f);
        }
      }
    }
  }

  function isEmpty() {
    $files = drush_scan_directory($this->directory, "//", array('.', '..'));
    return empty($files);
  }

  /**
   * Converts a cache id to a full path.
   *
   * @param $cid
   *   The cache ID of the data to retrieve.
   *
   * @return
   *   The full path to the cache file.
   */
  protected function getFilePath($cid) {
    return $this->directory . '/' . str_replace(array(':', '\\', '/'), '.', $cid) . self::EXTENSION;
  }
}
