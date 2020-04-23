<?php
declare(strict_types=1);

namespace Guzaba2\Cache;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Cache\Interfaces\CacheInterface;
use Guzaba2\Translator\Translator as t;

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

    /**
     * @param string $prefix
     * @param int $percentage
     * @return int
     * @throws InvalidArgumentException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function clear_cache(string $prefix = '', int $percentage = 100): int
    {
        if (!$prefix && $percentage !== 100) {
            throw new InvalidArgumentException(sprintf(t::_('The $percentage argument must be 100 when no $prefix is provided.')));
        }
        if ($percentage === 0) {
            throw new InvalidArgumentException(sprintf(t::_('The $percentage argument can not be 0.')));
        }
        if ($percentage > 100) {
            throw new InvalidArgumentException(sprintf(t::_('The $percentage argument can not be higher than 100.')));
        }

        if ($prefix) {
            if ($percentage === 100) {
                $cleared_entries = count($this->cache[$prefix]);
                $this->cache[$prefix] = [];
            } else {
                $total_entries = count($this->cache[$prefix]);
                $entries_to_clean = round($total_entries * $percentage / 100, 1);
                //assuming the oldest added are to be clean using for each and cleaning the first $entries_to_be_clean should be OK
                $cleared_entries = 0;
                foreach ($this->cache[$prefix] as $key=>$value) {
                    unset($this->cache[$prefix][$key]);
                    $cleared_entries++;
                    if ($cleared_entries === $entries_to_clean) {
                        break;
                    }
                }
            }

        } else {
            $cleared_entries = 0;
            foreach ($this->cache as $prefix=>$data) {
                $cleared_entries += count($data);
            }
            $this->cache = [];
        }
        return $cleared_entries;
    }
}