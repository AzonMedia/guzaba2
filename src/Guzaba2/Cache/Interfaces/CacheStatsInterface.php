<?php
declare(strict_types=1);

namespace Guzaba2\Cache\Interfaces;

/**
 * Interface CacheStatsInterface
 * @package Guzaba2\Cache\Interfaces
 */
interface CacheStatsInterface
{
    public function get_hits() : int;

    public function get_misses() : int;

    public function reset_hits() : void;

    public function reset_misses() : void;

    public function reset_stats() : void;
}
