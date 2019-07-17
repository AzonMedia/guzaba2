<?php


namespace Guzaba2\Base\Traits;


use Guzaba2\Coroutine\Coroutine;

/**
 * Trait StaticStore
 * Because in Coroutine context static class properties should be avoided this trait provides set of static methods corresponding to the dynamic overloading methods for working with static data/properties.
 * This works by keeping a copy for each set of static data for each coroutine by using the coroutine context.
 * @uses \Guzaba2\Coroutine\Coroutine
 * @package Guzaba2\Base\Traits
 */
trait StaticStore
{
    public static function set_static(string $key, /* mixed */ $value) : void
    {
        Coroutine::set_data($key, $value);
    }

    public static function get_static(string $key) /* mixed */
    {
        return Coroutine::get_data($key);
    }

    public static function isset_static(string $key) : bool
    {
        return Coroutine::isset_data($key);
    }

    /**
     * Unsetting a static property is not possible but here we allow unsetting static data.
     * @param string $key
     */
    public static function unset_static(string $key) : void
    {
        Coroutine::unset_data($key);
    }
}