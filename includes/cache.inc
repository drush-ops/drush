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
      $class = drush_get_option('cache-default-class', '\Drush\Cache\JSONCache');
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
  return $ret;
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
