<?php

declare(strict_types=1);

namespace Guzaba2\Kernel;

/**
 * Class Runtime
 * @package Guzaba2\Kernel
 *
 * Contains various methods controlling PHP runtime. These methods are using ini_set().
 */
abstract class Runtime
{

    /**
     * Returns the memory limit in Bytes (no matter eve if it is set as 128M for example in php_ini).
     * @return int
     */
    public static function get_memory_limit(): int
    {
        $limit = ini_get('memory_limit');
        $multiply = 1;
        if (stripos($limit, 'K') !== false) {
            $multiply = 1024;
        } elseif (stripos($limit, 'M') !== false) {
            $multiply = 1024 * 1024;
        } elseif (stripos($limit, 'G') !== false) {
            $multiply = 1024 * 1024 * 1024;
        }
        $limit = (int) str_ireplace(['K', 'M', 'G'], '', $limit);
        return $limit * $multiply;
    }

    /**
     * Sets the memory limit.
     * @param int $bytes
     */
    public static function set_memory_limit(int $bytes): void
    {
        ini_set('memory_limit', (string) $bytes);
    }

    /**
     * Raises the memory limit to the provided $bytes.
     * If the current memory limit is higher it is not changed and the method returns false.
     * @param int $bytes
     * @return bool
     */
    public static function raise_memory_limit(int $bytes): bool
    {
        if ($bytes > self::get_memory_limit()) {
            self::set_memory_limit($bytes);
            return true;
        }
        return false;
    }

    /**
     * Lowers the memory limit to the provided $bytes.
     * If the current memory limit is lower it is not changed and the method returns false.
     * @param int $bytes
     * @return bool
     */
    public static function lower_memory_limit(int $bytes): bool
    {
        if ($bytes < self::get_memory_limit()) {
            self::set_memory_limit($bytes);
            return true;
        }
        return false;
    }

    /**
     * Returns the memory usage in bytes.
     * If $real_usage is provided will return the allocated memory (which is more than the used) in bytes.
     * @param bool $real_usage
     * @return int
     */
    public static function memory_get_usage(bool $real_usage = false): int
    {
        return memory_get_usage($real_usage);
    }

    /**
     * Alias of self::memory_get_usage()
     * @param bool $real_usage
     * @return int
     */
    public static function get_memory_usage(bool $real_usage = false): int
    {
        return self::memory_get_usage($real_usage);
    }

    /**
     * @return int
     */
    public static function gc_collect_cycles(): int
    {
        return gc_collect_cycles();
    }
}
