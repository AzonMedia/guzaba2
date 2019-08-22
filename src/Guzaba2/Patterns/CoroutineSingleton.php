<?php


namespace Guzaba2\Patterns;

use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Patterns\Interfaces\SingletonInterface;

/**
 * Class CoroutineSingleton
 * A singleton within the current coroutine.
 * Multiple CoroutineSingletons can exist in a single Request
 * If a singleton within the Request is needed please use RequestSingleton class
 * @package Guzaba2\Patterns
 */
abstract class CoroutineSingleton extends Singleton
{
    private static $instances = [];

    /**
     * @overrides
     * @return RequestSingleton
     */
    public static function &get_instance() : SingletonInterface
    {
        $called_class = get_called_class();
        $coroutine_id = Coroutine::getcid();
        if (!array_key_exists($coroutine_id, self::$instances)) {
            self::$instances[$coroutine_id] = [];
        }
        if (!array_key_exists($called_class, self::$instances[$coroutine_id]) || !self::$instances[$coroutine_id][$called_class] instanceof $called_class) {
            self::$instances[$coroutine_id][$called_class] = new $called_class();
        }
        return self::$instances[$coroutine_id][$called_class];
    }

    public static function get_coroutine_instances() : array
    {
        $coroutine_id = Coroutine::getcid();
        return self::$instances[$coroutine_id];
    }

    /**
     * Alias of get_request_instances()
     * @return array
     */
    public static function get_instances() : array
    {
        return self::get_coroutine_instances();
    }

    public static function get_all_instance() : array
    {
        return self::$instances;
    }

    /**
     * Destroys all ExecutionSingleton at the end of the execution.
     * Returns the number of destroyed objects
     * @return int
     */
    public static function cleanup() : int
    {
        $instances = self::get_coroutine_instances();
        $ret = count($instances);
        foreach ($instances as $instance) {
            $instance->destroy();
        }

        return $ret;
    }

    public function destroy() : void
    {
        $called_class = get_class($this);
        self::$instances[$called_class] = NULL;
        unset(self::$instances[$called_class]);
    }
}
