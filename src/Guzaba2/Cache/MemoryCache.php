<?php
declare(strict_types=1);

namespace Guzaba2\Cache;

use Guzaba2\Base\Base;
use Guzaba2\Cache\Interfaces\CacheInterface;

/**
 * Class MemoryCache
 * @package Guzaba2\Cache
 * This caches stores data in memory
 * Useful for caching data within a worker.
 * Please note that in preemptive mode it is not safe to use this cache without locking!
 */
class MemoryCache extends Base implements CacheInterface
{

    private array $cache = [];

    /**
     * Adds/overwrites data in the cache
     * @param string $prefix
     * @param string $key
     * @param $data
     */
    public function set(string $prefix, string $key, /* mixed*/ $data) : void
    {
        if (!array_key_exists($prefix, $this->cache)) {
            $this->cache[$prefix] = [];
        }
        $this->cache[$prefix][$key] = $data;
    }

    /**
     * If no $key is provided everything from the given prefix will be deleted.
     * @param string $prefix
     * @param string $key
     * @throws RunTimeException
     */
    public function delete(string $prefix, string $key ) : void
    {
        unset($this->cache[$prefix][$key]);
    }

    /**
     * Returns NULL if the key is not found.
     * @param string $prefix
     * @param string $key
     * @return mixed|null
     */
    public function get(string $prefix, string $key) /* mixed */
    {
        return $this->cache[$prefix][$key] ?? NULL;
    }

    /**
     * @param string $prefix
     * @param string $key
     * @return bool
     */
    public function exists(string $prefix, string $key) : bool
    {
        return array_key_exists($prefix, $this->cache) && array_key_exists($key, $this->cache[$prefix]) ;
    }

    public function get_stats($prefix = ''): array
    {
        if ($prefix) {
            $ret = ['elements' => isset($this->cache[$prefix]) ? count($this->cache[$prefix]) : 0 ];
        } else {
            $ret = 0;
            foreach ($this->cache as $prefix=>$data) {
                $ret += count($data);
            }
        }
        return $ret;
    }

    public function clear_cache(string $prefix = ''): void
    {
        if ($prefix) {
            $this->cache[$prefix] = [];
        } else {
            $this->cache = [];
        }
    }
}