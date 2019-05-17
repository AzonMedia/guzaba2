<?php

namespace Guzaba2\Registry\Interfaces;

//use Guzaba2\Patterns\Interfaces\SingletonInterface;

//interface RegistryInterface extends SingletonInterface
//there is no really need to be singleton as it will be instantiated only once and provided to the Kernel
//and the Registry is not supposed to be accessed from anywhere else except the Kernel
interface RegistryInterface
{
    public function get_config_value(string $class_name, string $key, /* mixed */ $default_value = NULL) /* mixed */ ;

    public function class_is_in_registry(string $class_name) : bool ;

    public function get_class_config_values(string $class_name) : array ;

}