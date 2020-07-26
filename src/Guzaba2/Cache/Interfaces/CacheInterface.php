<?php

declare(strict_types=1);

namespace Guzaba2\Cache\Interfaces;

use Guzaba2\Base\Exceptions\RunTimeException;

interface CacheInterface
{
    /**
     * Adds/overwrites data in the cache
     * @param string $key
     * @param $data
     * @throws RunTimeException
     */
    public function set(string $prefix, string $key, /* mixed*/ $data): void;

    /**
     * If no $key is provided everything from the given prefix will be deleted.
     * @param string $prefix
     * @param string $key
     * @throws RunTimeException
     */
    public function delete(string $prefix, string $key): void;

    /**
     * Returns NULL if the key is not found.
     * @param string $key
     * @throws RunTimeException
     */
    public function get(string $prefix, string $key) /* mixed */;

    public function exists(string $prefix, string $key): bool;

    public function get_stats(string $prefix = ''): array;


    public function clear_cache(string $prefix = '', int $percentage = 100): int;
    /*
    public function enable_caching() : void;

    public function disable_caching() : void;

    public function clear_cache() : void;
    */
}
