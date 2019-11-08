<?php


namespace Guzaba2\Base\Traits;

use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Di\Container;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Translator\Translator as t;

trait UsesServices
{

    /**
     * @return bool
     */
    public static function uses_services(): bool
    {
        $ret = FALSE;
        $called_class = get_called_class();
        if (defined($called_class.'::CONFIG_RUNTIME')) {
            $ret = !empty(static::CONFIG_RUNTIME['services']);
        }
        return $ret;
    }

    /**
     * @param string $service_name
     * @return bool
     */
    public static function uses_service(string $service_name): bool
    {
        //return static::uses_services() ? in_array($service_name, static::get_services()) : FALSE;
        //as the configuration cant change it is safe to cache in a static var the result of this method
        //caching it saves several function calls
        $cache = [];
        $called_class = get_called_class();
        if (!array_key_exists($called_class, $cache)) {
            $cache[$called_class] = static::uses_services() ? in_array($service_name, static::get_services()) : FALSE;
        }
        return $cache[$called_class];
    }

    /**
     * @return array
     */
    public static function get_services(): array
    {
        $ret = [];
        $called_class = get_called_class();
        if (defined($called_class.'::CONFIG_RUNTIME')) {
            $ret = static::CONFIG_RUNTIME['services'];
        }
        return $ret;
    }

    public static function get_service(string $service_name) : object
    {
        $called_class = get_called_class();
//        if (strpos($service_name, '_')!==FALSE) {
//            throw new RunTimeException(sprintf(t::_('Static method %s::%s() does not exist.'), $called_class, $service_name));
//        }
        if (!static::uses_service($service_name)) {
            throw new RunTimeException(sprintf(t::_('The class %s does not use the service %s. If you need this service please check is it available in %s and then add it in %s::CONFIG_DEFAULTS[\'service\'].'), $called_class, $service_name, Container::class, $called_class));
        }

        $ret = Kernel::get_service($service_name);
        return $ret;
    }

//    /**
//     * @param string $service_name
//     * @param array $args
//     * @return object
//     * @throws RunTimeException
//     */
//    public static function __callStatic(string $service_name, array $args) : object
//    {
//        $called_class = get_called_class();
//        if (strpos($service_name, '_')!==FALSE) {
//            throw new RunTimeException(sprintf(t::_('Static method %s::%s() does not exist.'), $called_class, $service_name));
//        }
//        if (!static::uses_service($service_name)) {
//            throw new RunTimeException(sprintf(t::_('The class %s does not use the service %s. If you need this service please check is it available in %s and then add it in %s::CONFIG_DEFAULTS[\'service\'].'), $called_class, $service_name, Container::class, $called_class));
//        }
//
//        $ret = Kernel::get_service($service_name);
//        return $ret;
//    }
}
