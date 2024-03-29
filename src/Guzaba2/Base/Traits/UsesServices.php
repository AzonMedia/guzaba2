<?php

declare(strict_types=1);

namespace Guzaba2\Base\Traits;

use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Di\Container;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Translator\Translator as t;

trait UsesServices
{

    /**
     * Whether the classes has any class dependencies defined.
     * @return bool
     */
    public static function uses_class_dependencies(): bool
    {
        $ret = false;
        $called_class = get_called_class();
        if (defined($called_class . '::CONFIG_RUNTIME')) {
            $ret = !empty(static::CONFIG_RUNTIME['class_dependencies']);
        }
        return $ret;
    }

    /**
     * @param class-string $interface
     * @return bool
     */
    public static function uses_class_dependency(string $interface): bool
    {
        $called_class = get_called_class();
        if (!interface_exists($interface)) {
            $message = sprintf(
                t::_('There is no interface %1$s. Each class dependency name must be a valid interface name.'),
                $interface
            );
            throw new RunTimeException($message);
        }
        $class_dependencies = static::get_class_dependencies();
        return isset($class_dependencies[$interface]);
    }

    /**
     * @return array<class-string: class-string>
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public static function get_class_dependencies(): array
    {
        if (!static::uses_class_dependencies()) {
            $message = sprintf(
                t::_('The class %1$s does not use class dependencies. You can define class dependencies in %2$s::CONFIG_DEFAULTS[\'class_dependencies\']'),
                $called_class,
                $called_class
            );
        }
        return static::CONFIG_RUNTIME['class_dependencies'];
    }

    /**
     * Returns the implementing class for the given dependency (interface name).
     * The dependencies should always be interface names (even though technically they could be just any string).
     * @param class-string<CLASS> $interface
     * @return class-string<CLASS>
     */
    public static function get_class_dependency(string $interface): string
    {
        $called_class = get_called_class();
        if (!static::uses_class_dependency($interface)) {
            $message = sprintf(
                t::_('Class %1$s does not use class dependency %2$s.'),
                $called_class,
                $interface
            );
            throw new RunTimeException($message);
        }
        $dependencies = static::get_class_dependencies();
        return $dependencies[$interface];
    }
    
    /**
     * @return bool
     */
    public static function uses_services(): bool
    {
        $ret = false;
        $called_class = get_called_class();
        if (defined($called_class . '::CONFIG_RUNTIME')) {
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
            $cache[$called_class] = static::uses_services() ? in_array($service_name, static::get_services()) : false;
        }
        return $cache[$called_class];
    }

    public static function has_service(string $service_name): bool
    {
        return Kernel::has_service($service_name);
    }

    /**
     * @return array
     */
    public static function get_services(): array
    {
        $ret = [];
        $called_class = get_called_class();
        if (defined($called_class . '::CONFIG_RUNTIME')) {
            $ret = static::CONFIG_RUNTIME['services'];
        }
        return $ret;
    }

    /**
     * @param string $service_name
     * @return object
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public static function get_service(string $service_name): object
    {
        $called_class = get_called_class();
        if (!static::uses_service($service_name)) {
            $message = sprintf(
                t::_('The class %1$s does not use the service %2$s. If you need this service please check is it available in %3$s and then add it in %4$s::CONFIG_DEFAULTS[\'services\'].'),
                $called_class,
                $service_name,
                Container::class,
                $called_class
            );
            throw new RunTimeException($message);
        }

        $ret = Kernel::get_service($service_name);
        return $ret;
    }

    /**
     * @param string $event_name
     * @param callable $callback
     * @return bool
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public function add_callback(string $event_name, callable $callback): bool
    {
        /** @var Events $Events */
        $Events = self::get_service('Events');
        return $Events->add_object_callback($this, $event_name, $callback);
    }

//    public static function is_service_instantiated(string $service_name) : bool
//    {
//        $called_class = get_called_class();
//        if (!static::uses_service($service_name)) {
//            throw new RunTimeException(sprintf(t::_('The class %s does not use the service %s. If you need this service please check is it available in %s and then add it in %s::CONFIG_DEFAULTS[\'services\'].'), $called_class, $service_name, Container::class, $called_class));
//        }
//
//        $ret = Kernel::is_service_instantiated($service_name);
//        return $ret;
//    }

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
