<?php


namespace Guzaba2\Base\Traits;


use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Di\Container;
use Guzaba2\Kernel\Kernel;

trait UsesServices
{

    public static function uses_services(): bool
    {
        return !empty(static::$CONFIG_RUNTIME['services']);
    }

    public static function uses_service(string $service_name) : bool
    {
        return self::uses_services() ? in_array($service_name, static::$CONFIG_RUNTIME['services']) : FALSE;
    }

    public static function get_services(): array
    {
        return static::$CONFIG_RUNTIME['services'];
    }

    public static function __callStatic(string $service_name, array $args) : object
    {
        $called_class = get_called_class();
        if (!self::uses_service($service_name)) {
             throw new RunTimeException(sprintf(t::_('The class %s does not use the service %s. If you need this service please check is it available in %s and then add it in %s::CONFIG_DEFAULTS[\'service\'].'), $called_class, $service_name, Container::class, $called_class));
        }
        $ret = Kernel::get_service($service_name);
        return $ret;
    }
}