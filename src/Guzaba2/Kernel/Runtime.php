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

    public static function get_memory_limit(): int
    {
        return (int) ini_get('memory_limit');
    }

    public static function set_memory_limit(int $bytes): void
    {
        ini_set('memory_limit', $bytes);
        self::$memory_limit = $bytes;
    }

    public static function raise_memory_limit(int $bytes): bool
    {
        if ($bytes > self::get_memory_limit()) {
            self::set_memory_limit($bytes);
            return TRUE;
        }
        return FALSE;
    }

    public static function lower_memory_limit(int $bytes): bool
    {
        if ($bytes < self::get_memory_limit()) {
            self::set_memory_limit($bytes);
            return TRUE;
        }
        return FALSE;
    }
}