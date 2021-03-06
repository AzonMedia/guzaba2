<?php

declare(strict_types=1);

namespace Guzaba2\Cache;

use Azonmedia\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Base;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Cache\Interfaces\CacheInterface;
use Guzaba2\Coroutine\Exceptions\ContextDestroyedException;
use Guzaba2\Translator\Translator as t;

/**
 * Class Cache
 * @package Guzaba2\Coroutine
 * Caches data in the coroutine context.
 * This means that the cached data is valid until the current coroutine (request) is over.
 * Can be used also in non-coroutine mode - in this case it stores the data in a static property.
 * Any cached data in non-coroutine mode is not ported into coroutine mode.
 * In non-coroutine mode this is equivalent to MemoryCache
 */
class ContextCache extends Base implements CacheInterface
{

    /**
     * @var array
     */
    private array $cache = [];

    /**
     * Adds/overwrites data in the cache
     * @param string $prefix
     * @param string $key
     * @param $data
     * @throws RunTimeException
     * @throws InvalidArgumentException
     * @throws ContextDestroyedException
     * @throws \ReflectionException
     */
    public function set(string $prefix, string $key, /* mixed*/ $data): void
    {
        if (Coroutine::inCoroutine()) {
            $Context = Coroutine::getContext();
            if (!property_exists($Context, self::class)) {
                $Context->{self::class} = [];
            }
            if (!array_key_exists($prefix, $Context->{self::class})) {
                $Context->{self::class}[$prefix] = [];
            }
            $Context->{self::class}[$prefix][$key] = $data;
        } else {
            $this->cache[$prefix][$key] = $data;
        }
    }

    /**
     * If no $key is provided everything from the given prefix will be deleted.
     * @param string $prefix
     * @param string $key
     * @throws RunTimeException
     * @throws InvalidArgumentException
     * @throws ContextDestroyedException
     * @throws \ReflectionException
     */
    public function delete(string $prefix, string $key): void
    {
        if (Coroutine::inCoroutine()) {
            $Context = Coroutine::getContext();
            if (!property_exists($Context, self::class)) {
                $Context->{self::class} = [];
            }
            if ($key) {
                unset($Context->{self::class}[$prefix][$key]);
            } else {
                unset($Context->{self::class}[$prefix]);
            }
        } else {
            if ($key) {
                unset($this->cache[$prefix][$key]);
            } else {
                unset($this->cache[$prefix][$key]);
            }
        }
    }

    /**
     * Returns NULL if the key is not found.
     * @param string $prefix
     * @param string $key
     * @return mixed|null
     * @throws RunTimeException
     * @throws InvalidArgumentException
     * @throws ContextDestroyedException
     * @throws \ReflectionException
     */
    public function get(string $prefix, string $key) /* mixed */
    {
        if (Coroutine::inCoroutine()) {
            $Context = Coroutine::getContext();
            if (!property_exists($Context, self::class)) {
                $Context->{self::class} = [];
            }
            $ret = $Context->{self::class}[$prefix][$key] ?? null;
        } else {
            $ret = $this->cache[$prefix][$key] ?? null;
        }
        return $ret;
    }

    /**
     * @param string $prefix
     * @param string $key
     * @return bool
     * @throws RunTimeException
     * @throws InvalidArgumentException
     * @throws ContextDestroyedException
     * @throws \ReflectionException
     */
    public function exists(string $prefix, string $key): bool
    {
        $ret = false;
        if (Coroutine::inCoroutine()) {
            $Context = Coroutine::getContext();
            if (!property_exists($Context, self::class)) {
                $Context->{self::class} = [];
            }
            if (array_key_exists($prefix, $Context->{self::class}) && array_key_exists($key, $Context->{self::class}[$key])) {
                $ret = true;
            }
        } else {
            if (array_key_exists($prefix, $this->cache) && array_key_exists($key, $this->cache[$prefix])) {
                $ret = true;
            }
        }
        return $ret;
    }

    public function get_stats(string $prefix = ''): array
    {
        // TODO: Implement get_stats() method.
    }

    public function clear_cache(string $prefix = '', int $percentage = 100): int
    {
        // TODO: Implement clear_cache() method.
        return 0;
    }
}
