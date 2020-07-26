<?php

declare(strict_types=1);

namespace Guzaba2\Cache\Interfaces;

/**
 * Interface IndependentCacheInterface
 * @package Guzaba2\Cache\Interfaces
 * To be implemented by caches that use storage outside the memory of the current process
 */
interface IndependentCacheInterface extends ProcessCacheInterface
{

}
