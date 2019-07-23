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


    private static $data = [];

    public static function set_static(string $key, /* mixed */ $value) : void
    {
        $class = get_called_class();
        Coroutine::set_data($class, $key, $value);
    }

    public static function get_static(string $key) /* mixed */
    {
        $class = get_called_class();
        return Coroutine::get_data($class, $key);
    }

    public static function isset_static(string $key) : bool
    {
        $class = get_called_class();
        return Coroutine::isset_data($class, $key);
    }

    /**
     * Unsetting a static property is not possible but here we allow unsetting static data.
     * @param string $key
     */
    public static function unset_static(string $key) : void
    {
        $class = get_called_class();
        Coroutine::unset_data($class, $key);
    }
}