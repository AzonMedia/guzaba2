<?php

declare(strict_types=1);

namespace Guzaba2\Cache\Interfaces;

/**
 * Interface IntCacheInterface
 * @package Guzaba2\Cache\Interfaces
 * Usually used to store microtime of last modification
 */
interface IntCacheInterface
{
    /**
     * Adds/overwrites data in the cache
     * @param string $key
     * @param $data
     * @throws RunTimeException
     */
    public function set(string $prefix, string $key, int $data): void;

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
    public function get(string $prefix, string $key): ?int;

    public function exists(string $prefix, string $key): bool;
}
