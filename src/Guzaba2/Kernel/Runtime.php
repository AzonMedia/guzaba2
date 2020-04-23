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
        if (stripos($limit,'K') !== FALSE) {
            $multiply = 1024;
        } elseif (stripos($limit, 'M') !== FALSE) {
            $multiply = 1024 * 1024;
        } elseif (stripos($limit, 'G') !== FALSE) {
            $multiply = 1024 * 1024 * 1024;
        }
        $limit = (int) str_ireplace(['K', 'M', 'G'],'', $limit);
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
            return TRUE;
        }
        return FALSE;
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
            return TRUE;
        }
        return FALSE;
    }

    /**
     * @return int
     */
    public static function gc_collect_cycles(): int
    {
        return gc_collect_cycles();
    }



}