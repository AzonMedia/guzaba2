<?php

namespace Guzaba2\Registry\Interfaces;

use Guzaba2\Patterns\Interfaces\Singleton;

interface Registry extends Singleton
{
    public function get_config_value(string $class_name, string $key) /* mixed */ ;

    public function class_is_in_registry(string $class_name) : bool ;

    public function get_class_config_values(string $class_name) : array ;
}