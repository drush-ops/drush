<?php

/**
 * @file Drush cache API
 *
 * Provides a cache API for drush core and commands, forked from Drupal 7.
 *
 * The default storage backend uses the plain text files to store serialized php
 * objects, which can be extended or replaced by setting the cache-default-class
 * option in drushrc.php.
 */

/**
 * Indicates that the item should never be removed unless explicitly selected.
 *
 * The item may be removed using cache_clear_all() with a cache ID.
 */
define('DRUSH_CACHE_PERMANENT', 0);

/**
 * Indicates that the item should be removed at the next general cache wipe.
 */
define('DRUSH_CACHE_TEMPORARY', -1);

/**
 * Get the cache object for a cache bin.
 *
 * By default, this returns an instance of the DrushFileCache class.
 * Classes implementing DrushCacheInterface can register themselves both as a
 * default implementation and for specific bins.
 *
 * @see DrushCacheInterface
 *
 * @param $bin
 *   The cache bin for which the cache object should be returned.
 * @return DrushCacheInterface
 *   The cache object associated with the specified bin.
 */
function _drush_cache_get_object($bin) {
  static $cache_objects;

  if (!isset($cache_objects[$bin])) {
    $class = drush_get_option('cache-class-' . $bin, NULL);
    if (!isset($class)) {
      $class = drush_get_option('cache-default-class', 'DrushJSONCache');
    }
    $cache_objects[$bin] = new $class($bin);
  }
  return $cache_objects[$bin];
}

/**
 * Return data from the persistent cache
 *
 * Data may be stored as either plain text or as serialized data. _drush_cache_get
 * will automatically return unserialized objects and arrays.
 *
 * @param $cid
 *   The cache ID of the data to retrieve.
 * @param $bin
 *   The cache bin to store the data in.
 *
 * @return
 *   The cache or FALSE on failure.
 */
function drush_cache_get($cid, $bin = 'default') {
  $ret = _drush_cache_get_object($bin)->get($cid);
  $mess = $ret ? "HIT" : "MISS";
  drush_log(dt("Cache !mess cid: !cid", array('!mess' => $mess, '!cid' => $cid)), 'debug');
  return _drush_cache_get_object($bin)->get($cid);
}

/**
 * Return data from the persistent cache when given an array of cache IDs.
 *
 * @param $cids
 *   An array of cache IDs for the data to retrieve. This is passed by
 *   reference, and will have the IDs successfully returned from cache removed.
 * @param $bin
 *   The cache bin where the data is stored.
 * @return
 *   An array of the items successfully returned from cache indexed by cid.
 */
function drush_cache_get_multiple(array &$cids, $bin = 'default') {
  return _drush_cache_get_object($bin)->getMultiple($cids);
}

/**
 * Store data in the persistent cache.
 *
 * @param $cid
 *   The cache ID of the data to store.
 * @param $data
 *   The data to store in the cache.
 * @param $bin
 *   The cache bin to store the data in.
 * @param $expire
 *   One of the following values:
 *   - DRUSH_CACHE_PERMANENT: Indicates that the item should never be removed unless
 *     explicitly told to using cache_clear_all() with a cache ID.
 *   - DRUSH_CACHE_TEMPORARY: Indicates that the item should be removed at the next
 *     general cache wipe.
 *   - A Unix timestamp: Indicates that the item should be kept at least until
 *     the given time, after which it behaves like DRUSH_CACHE_TEMPORARY.
 */
function drush_cache_set($cid, $data, $bin = 'default', $expire = DRUSH_CACHE_PERMANENT) {
  $ret = _drush_cache_get_object($bin)->set($cid, $data, $expire);
  if ($ret) drush_log(dt("Cache SET cid: !cid", array('!cid' => $cid)), 'debug');
  return $ret;
}

/**
 * Expire data from the cache.
 *
 * If called without arguments, expirable entries will be cleared from all known
 * cache bins.
 *
 * @param $cid
 *   If set, the cache ID to delete. Otherwise, all cache entries that can
 *   expire are deleted.
 *
 * @param $bin
 *   If set, the bin $bin to delete from. Mandatory
 *   argument if $cid is set.
 *
 * @param $wildcard
 *   If $wildcard is TRUE, cache IDs starting with $cid are deleted in
 *   addition to the exact cache ID specified by $cid.  If $wildcard is
 *   TRUE and $cid is '*' then the entire bin $bin is emptied.
 */
function drush_cache_clear_all($cid = NULL, $bin = 'default', $wildcard = FALSE) {
  if (!isset($cid) && !isset($bin)) {
    foreach (drush_cache_get_bins() as $bin) {
      _drush_cache_get_object($bin)->clear();
    }
    return;
  }
  return _drush_cache_get_object($bin)->clear($cid, $wildcard);
}

/**
 * Check if a cache bin is empty.
 *
 * A cache bin is considered empty if it does not contain any valid data for any
 * cache ID.
 *
 * @param $bin
 *   The cache bin to check.
 * @return
 *   TRUE if the cache bin specified is empty.
 */
function _drush_cache_is_empty($bin) {
  return _drush_cache_get_object($bin)->isEmpty();
}

/**
 * Return drush cache bins and any bins added by
 * implementing hook_drush_flush_caches().
 */
function drush_cache_get_bins() {
  $drush = array('default');
  return array_merge(drush_command_invoke_all('drush_flush_caches'), $drush);
}

/**
 * Create a cache id from a given prefix, contexts, and any additional
 * parameters necessary.
 *
 * @param prefix
 *   A human readable cid prefix that will not be hashed.
 * @param contexts
 *   Optional. An array of drush contexts that will be used to build a unique hash.
 * @param params
 *   Optional. An array of any addition parameters to be hashed.
 *
 * @return
 *   A cache id string.
 */
function drush_get_cid($prefix, $contexts = array(), $params = array()) {
  $cid = array();

  foreach ($contexts as $context) {
    $c = drush_get_context($context);
    if (!empty($c)) {
      $cid[] = is_scalar($c) ? $c : serialize($c);
    }
  }

  foreach ($params as $param) {
    $cid[] = $param;
  }

  return DRUSH_VERSION . '-' . $prefix . '-' . md5(implode("", $cid));
}

/**
 * Interface for cache implementations.
 *
 * All cache implementations have to implement this interface.
 * DrushDatabaseCache provides the default implementation, which can be
 * consulted as an example.
 *
 * To make Drush use your implementation for a certain cache bin, you have to
 * set a variable with the name of the cache bin as its key and the name of
 * your class as its value. For example, if your implementation of
 * DrushCacheInterface was called MyCustomCache, the following line in
 * drushrc.php would make Drush use it for the 'example' bin:
 * @code
 *  $options['cache-class-example'] = 'MyCustomCache;
 * @endcode
 *
 * Additionally, you can register your cache implementation to be used by
 * default for all cache bins by setting the option 'cache-default-class' to
 * the name of your implementation of the DrushCacheInterface, e.g.
 * @code
 *  $options['cache-default-class'] = 'MyCustomCache;
 * @endcode
 *
 * @see _drush_cache_get_object()
 * @see DrupalCacheInterface
 */
interface DrushCacheInterface {
  /**
   * Constructor.
   *
   * @param $bin
   *   The cache bin for which the object is created.
   */
  function __construct($bin);

  /**
   * Return data from the persistent cache.
   *
   * @param $cid
   *   The cache ID of the data to retrieve.
   * @return
   *   The cache or FALSE on failure.
   */
  function get($cid);

  /**
   * Return data from the persistent cache when given an array of cache IDs.
   *
   * @param $cids
   *   An array of cache IDs for the data to retrieve. This is passed by
   *   reference, and will have the IDs successfully returned from cache
   *   removed.
   * @return
   *   An array of the items successfully returned from cache indexed by cid.
   */
   function getMultiple(&$cids);

  /**
   * Store data in the persistent cache.
   *
   * @param $cid
   *   The cache ID of the data to store.
   * @param $data
   *   The data to store in the cache.
   * @param $expire
   *   One of the following values:
   *   - DRUSH_CACHE_PERMANENT: Indicates that the item should never be removed unless
   *     explicitly told to using _drush_cache_clear_all() with a cache ID.
   *   - DRUSH_CACHE_TEMPORARY: Indicates that the item should be removed at the next
   *     general cache wipe.
   *   - A Unix timestamp: Indicates that the item should be kept at least until
   *     the given time, after which it behaves like CACHE_TEMPORARY.
   */
  function set($cid, $data, $expire = DRUSH_CACHE_PERMANENT);

  /**
   * Expire data from the cache. If called without arguments, expirable
   * entries will be cleared from all known cache bins.
   *
   * @param $cid
   *   If set, the cache ID to delete. Otherwise, all cache entries that can
   *   expire are deleted.
   * @param $wildcard
   *   If set to TRUE, the $cid is treated as a substring
   *   to match rather than a complete ID. The match is a right hand
   *   match. If '*' is given as $cid, the bin $bin will be emptied.
   */
  function clear($cid = NULL, $wildcard = FALSE);

  /**
   * Check if a cache bin is empty.
   *
   * A cache bin is considered empty if it does not contain any valid data for
   * any cache ID.
   *
   * @return
   *   TRUE if the cache bin specified is empty.
   */
  function isEmpty();
}

/**
 * Default cache implementation.
 *
 * This is Drush's default cache implementation. It uses plain text files
 * containing serialized php to store cached data. Each cache bin corresponds
 * to a directory by the same name.
 */
class DrushFileCache implements DrushCacheInterface {
  const EXTENSION = '.cache';
  protected $bin;

  function __construct($bin) {
    $this->bin = $bin;
    $this->directory = $this->cacheDirectory();
  }

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
        if (!file_exists($filename)) throw new Exception;

        $item = $this->readFile($filename);
        if ($item) {
          $cache[$cid] = $item;
        }
      }
      $cids = array_diff($cids, array_keys($cache));
      return $cache;
    }
    catch (Exception $e) {
      return array();
    }
  }

  function readFile($filename) {
    $item = file_get_contents($filename);
    return $item ? unserialize($item) : FALSE;
  }

  function set($cid, $data, $expire = DRUSH_CACHE_PERMANENT, array $headers = NULL) {
    $created = time();

    $cache = new stdClass;
    $cache->cid = $cid;
    $cache->data = is_object($data) ? clone $data : $data;
    $cache->created = $created;
    $cache->headers = $headers;
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
        // Delete immediately instead of drush_register_file_for_deletion().
        unlink($f);
      }
    }
  }

  function isEmpty() {
    $files = drush_scan_directory($dir, "//", array('.', '..'));
    return empty($files);
  }

  /**
   * Converts a cache id to a full path.
   *
   * @param $cid
   *   The cache ID of the data to retrieve.
   * @return
   *   The full path to the cache file.
   */
  protected function getFilePath($cid) {
    return $this->directory . '/' . str_replace(array(':'), '.', $cid) . self::EXTENSION;
  }
}

/**
 * JSON cache storage backend.
 */
class DrushJSONCache extends DrushFileCache {
  const EXTENSION = '.json';

  function readFile($filename) {
    $item = file_get_contents($filename);
    return $item ? (object)drush_json_decode($item) : FALSE;
  }

  function writeFile($filename, $cache) {
    return file_put_contents($filename, drush_json_encode($cache));
  }
}
