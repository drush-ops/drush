<?php

namespace Drush\Cache;

use Consolidation\AnnotatedCommand\Cache\SimpleCacheInterface;

/**
 * Command cache implementation.
 *
 * This wrapper implements a cache usable with the annotated-command
 * library's command cache. It uses a Drush JSONCache for its back-end.
 */
class CommandCache implements SimpleCacheInterface
{

    protected $cacheBackend;

    public function __construct(CacheInterface $cacheBackend)
    {
        $this->cacheBackend = $cacheBackend;
    }

    /**
     * Test for an entry from the cache
     * @param string $key
     * @return boolean
     */
    public function has($key)
    {
        $cacheItem = $this->cacheBackend->get($key);
        return $this->valid($cacheItem);
    }
    /**
     * Get an entry from the cache
     * @param string $key
     * @return array
     */
    public function get($key)
    {
        $cacheItem = $this->cacheBackend->get($key);
        if (!$this->valid($cacheItem)) {
            return [];
        }
        // TODO: FileCache::get() should just return the
        // data element, not the entire cacheItem. Then we
        // could make it implement SimpleCacheInterface & do
        // away with this adapter class.
        return $cacheItem->data;
    }
    /**
     * Store an entry in the cache
     * @param string $key
     * @param array $data
     */
    public function set($key, $data)
    {
        $this->cacheBackend->set($key, $data);
    }

    protected function valid($cacheItem)
    {
        return is_object($cacheItem) && isset($cacheItem->data);
    }
}
