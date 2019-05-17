<?php

namespace Guzaba2\Base\Interfaces;

/**
 * Interface ConfigInterface
 * Classes that support configuration managed by the Kernel::autoloader should implement this interface
 * @package Guzaba2\Base\Interfaces
 */
interface ConfigInterface
{

    //public const CONFIG_DEFALUTS = [];//must be defined but should not be public but protected instead

    public static function initialize_runtime_configuration(array $runtime_config) : void ;

    //once the configuration is set there should no be methods for accessing it outside the class as this breaks the principle of separation of configuration
    //the configuration of a class should be accessible only to this class and its children
    //though when a new child class is autoloaded it must inherit the configuration of its parent
    //while the runtime configuration is set from the registry it is kept in a static property which can be modified by the class at runtime
    //this means that between a child class is being autoloaded its parent which may have been already autoloaded to have modified its runtime configuration
    //and the child class must obtain the current runtime configuration of its parent not the initial runtime config that was set by the kernel
    //because of this a method accessible by the kernel is needed

    public static function get_runtime_configuration() : array ;

    //no need of is_configured_method() as if a class is autoloaded by Kernel then it will always be configured at the time this method is invoked
}