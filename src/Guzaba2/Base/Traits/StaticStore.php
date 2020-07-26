<?php

declare(strict_types=1);

namespace Guzaba2\Base\Traits;

use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Kernel\Kernel;

/**
 * Trait StaticStore
 * Because in Coroutine context static class properties should be avoided this trait provides set of static methods corresponding to the dynamic overloading methods for working with static data/properties.
 * This works by keeping a copy for each set of static data for each coroutine by using the coroutine context.
 * @uses \Guzaba2\Coroutine\Coroutine
 * @package Guzaba2\Base\Traits
 */
trait StaticStore
{
    private $static_store = [];

    public static function set_static(string $key, /* mixed */ $value): void
    {
        $class = get_called_class();
        //if (\Swoole\Coroutine::getCid() > 0) {
        if (Coroutine::inCoroutine()) {
            $Context = Coroutine::getContext();
            if (!array_key_exists($class, $Context->static_store)) {
                $Context->static_store[$class] = [];
            }
            //but using a reference will create an issue if the var is being set on a child class - the reference must be broken and only then the var set
            //!!!!!!! this wont work as if setting var on a parent class would have to set it on child but thisvar may already be set... and it is not known without additional flags was it set directly or because it is a child class
            unset($Context->static_store[$class][$key]);
            $Context->static_store[$class][$key] = $value;
            //and set the var for all child classes
            foreach (Kernel::get_class_all_children() as $child_class) {
                //setting the var on a parent class must not overwrite the var in a child class
                if (!array_key_exists($key, $Context->static_store[$child_class])) {
                    $Context->static_store[$child_class][$key] =& $Context->static_store[$class][$key];
                    //but using a reference will create an issue if the var is being set on a child class - the reference must be broken and only then the var set
                }
            }
        } else {
        }
    }

    public static function get_static(string $key) /* mixed */
    {
        $class = get_called_class();
        if (Coroutine::inCoroutine()) {
            $Context = Coroutine::getContext();
            if (!array_key_exists($key, $Context->static_store[$class])) {
                //throw
            }
            $ret = $Context->static_store[$class][$key];
        } else {
        }
        //$class = get_called_class();
        //return Coroutine::getData($class, $key);
        return $ret;
    }

    public static function isset_static(string $key): bool
    {
        $class = get_called_class();
        return Coroutine::issetData($class, $key);
    }

    public static function unset_static(string $key): void
    {
        $class = get_called_class();
        Coroutine::unsetData($class, $key);
    }


//    public static function set_static(string $key, /* mixed */ $value) : void
//    {
//        $class = get_called_class();
//        Coroutine::setData($class, $key, $value);
//    }
//
//    public static function get_static(string $key) /* mixed */
//    {
//        $class = get_called_class();
//        return Coroutine::getData($class, $key);
//    }
//
//    public static function isset_static(string $key) : bool
//    {
//        $class = get_called_class();
//        return Coroutine::issetData($class, $key);
//    }
//
//    public static function unset_static(string $key) : void
//    {
//        $class = get_called_class();
//        Coroutine::unsetData($class, $key);
//    }

//    public static function has_static() : bool
//    {
//        $class = get_called_class();
//        return Coroutine::hasData($class);
//    }
//
//    public static function unset_all_static() : void
//    {
//        $class = get_called_class();
//        Coroutine::unsetAllData($class);
//    }
}
