<?php

declare(strict_types=1);

namespace Guzaba2\Cache;

use Guzaba2\Base\Base;
use Guzaba2\Cache\Interfaces\CacheInterface;
use Guzaba2\Cache\Interfaces\IndependentCacheInterface;

/**
 * Class NullCache
 * @package Guzaba2\Cache
 *
 * Does not cache anything, returns always NULL/FALSE when data is requested.
 */
class NullCache extends Base implements CacheInterface, IndependentCacheInterface
{
    /**
     * Adds/overwrites data in the cache
     * @param string $key
     * @param $data
     * @throws RunTimeException
     */
    public function set(string $prefix, string $key, /* mixed*/ $data): void
    {
    }

    /**
     * If no $key is provided everything from the given prefix will be deleted.
     * @param string $prefix
     * @param string $key
     * @throws RunTimeException
     */
    public function delete(string $prefix, string $key): void
    {
    }

    /**
     * Returns NULL if the key is not found.
     * @param string $key
     * @throws RunTimeException
     */
    public function get(string $prefix, string $key) /* mixed */
    {
        return NULL;
    }

    /**
     * @param string $prefix
     * @param string $key
     * @return bool
     */
    public function exists(string $prefix, string $key): bool
    {
        return FALSE;
    }

    public function get_stats(string $prefix = ''): array
    {
        return [];
    }

    public function clear_cache(string $prefix = '', int $percentage = 100): int
    {
        return 0;
    }
}
