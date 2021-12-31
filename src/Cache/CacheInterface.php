<?php

/**
 * @file
 * Definition of Drush\Cache\CacheInterface.
 */

namespace Drush\Cache;

/**
 * Interface for cache implementations.
 *
 * All cache implementations have to implement this interface.
 * JSONCache provides the default implementation, which can be
 * consulted as an example.
 *
 * To make Drush use your implementation for a certain cache bin, you have to
 * set a variable with the name of the cache bin as its key and the name of
 * your class as its value. For example, if your implementation of
 * CacheInterface was called MyCustomCache, the following line in
 * drushrc.php would make Drush use it for the 'example' bin:
 * @code
 *  $options['cache-class-example'] = 'MyCustomCache;
 * @endcode
 *
 * Additionally, you can register your cache implementation to be used by
 * default for all cache bins by setting the option 'cache-default-class' to
 * the name of your implementation of the CacheInterface, e.g.
 * @code
 *  $options['cache-default-class'] = 'MyCustomCache;
 * @endcode
 *
 * @see \Drupal\Core\Cache\CacheBackendInterface
 *
 * @deprecated
 */
interface CacheInterface
{

    /**
     * Constructor.
     *
     * @param $bin
     *   The cache bin for which the object is created.
     */
    public function __construct($bin);

    /**
     * Return data from the persistent cache.
     *
     * @param string $cid
     *   The cache ID of the data to retrieve.
     *
     * @return
     *   The cache or FALSE on failure.
     */
    public function get($cid);

    /**
     * Return data from the persistent cache when given an array of cache IDs.
     *
     * @param array $cids
     *   An array of cache IDs for the data to retrieve. This is passed by
     *   reference, and will have the IDs successfully returned from cache
     *   removed.
     *
     * @return
     *   An array of the items successfully returned from cache indexed by cid.
     */
    public function getMultiple(&$cids);

    /**
     * Store data in the persistent cache.
     *
     * @param string $cid
     *   The cache ID of the data to store.
     * @param array $data
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
    public function set($cid, $data, $expire = DRUSH_CACHE_PERMANENT);

    /**
     * Expire data from the cache. If called without arguments, expirable
     * entries will be cleared from all known cache bins.
     *
     * @param string $cid
     *   If set, the cache ID to delete. Otherwise, all cache entries that can
     *   expire are deleted.
     * @param bool $wildcard
     *   If set to TRUE, the $cid is treated as a substring
     *   to match rather than a complete ID. The match is a right hand
     *   match. If '*' is given as $cid, the bin $bin will be emptied.
     */
    public function clear($cid = null, $wildcard = false);

    /**
     * Check if a cache bin is empty.
     *
     * A cache bin is considered empty if it does not contain any valid data for
     * any cache ID.
     *
     * @return
     *   TRUE if the cache bin specified is empty.
     */
    public function isEmpty();
}
