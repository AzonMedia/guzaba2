<?php
declare(strict_types=1);


namespace Guzaba2\Orm\Traits;


use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Translator\Translator as t;

/**
 * Trait ActiveRecordHooks
 * @package Guzaba2\Orm\Traits
 */
trait ActiveRecordHooks
{

    /**
     * Contains what hooks this class has.
     * To avoid to obtain at runtime this information.
     * 'crud','validation','property'
     * @var array
     */
    protected static array $hooks = [];

    public static function initialize_hooks() : void
    {
        $class_name = get_called_class();
        self::$hooks[$class_name] = [];
        self::$hooks[$class_name]['property'] = [];
        self::$hooks[$class_name]['property']['get'] = $class_name::get_get_property_hooks();
        self::$hooks[$class_name]['property']['set'] = $class_name::get_set_property_hooks();
        self::$hooks[$class_name]['validation'] = [];
        self::$hooks[$class_name]['validation']['static'] = $class_name::get_static_property_validation_hooks();
        self::$hooks[$class_name]['validation']['dynamic'] = $class_name::get_dynamic_property_validation_hooks();
        self::$hooks[$class_name]['crud'] = $class_name::get_crud_hooks();
    }

    public static function has_property_hooks() : bool
    {
        return self::has_get_property_hooks() || self::has_set_property_hooks() ;
    }

    public static function has_get_property_hooks() : bool
    {
        $class_name = get_called_class();
        return count(self::$hooks[$class_name]['property'['get']]) ? TRUE : FALSE;
    }

    public static function has_set_property_hooks() : bool
    {
        $class_name = get_called_class();
        return count(self::$hooks[$class_name]['property'['set']]) ? TRUE : FALSE;
    }

    public static function has_validation_hooks() : bool
    {
        return self::has_dynamic_validation_hooks() || self::has_static_validation_hooks() ;
    }

    public static function has_dynamic_validation_hooks() : bool
    {
        $class_name = get_called_class();
        return count(self::$hooks[$class_name]['validation']['dynamic']) ? TRUE : FALSE;
    }

    public static function has_static_validation_hooks() : bool
    {
        $class_name = get_called_class();
        return count(self::$hooks[$class_name]['validation']['static']) ? TRUE : FALSE;
    }

    public static function has_crud_hooks() : bool
    {
        $class_name = get_called_class();
        return count(self::$hooks[$class_name]['crud']) ? TRUE : FALSE;
    }

    /**
     * @return array
     */
    public static function get_crud_hooks() : array
    {
        $ret = [];
        $class_name = get_called_class();
        foreach (ActiveRecordInterface::CRUD_HOOKS as $method_name) {
            if (method_exists($class_name, $method_name)) {
                $ret[] = $method_name;
            }
        }
        return $ret;
    }

    /**
     * @return array
     */
    public static function get_property_validation_hooks() : array
    {
        return array_merge(static::get_dynamic_property_validation_hooks(), static::get_static_property_validation_hooks() );
    }

    /**
     * @return array
     */
    public static function get_dynamic_property_validation_hooks() : array
    {
        $ret = [];
        $class_name = get_called_class();
        $properties = $class_name::get_property_names();
        foreach ($properties as $property) {
            $method_name = '_validate_'.$property;
            if (method_exists($class_name, $method_name)) {
                $ret[] = $method_name;
            }
        }
        return $ret;
    }

    /**
     * @return array
     */
    public static function get_static_property_validation_hooks() : array
    {
        $ret = [];
        $class_name = get_called_class();
        $properties = $class_name::get_property_names();
        foreach ($properties as $property) {
            $method_name = '_validate_static_'.$property;
            if (method_exists($class_name, $method_name)) {
                $ret[] = $method_name;
            }
        }
        return $ret;
    }

    /**
     * Returns all property hooks. This includes: _before_set_PROPERTY, _after_set_PROPERTY, _before_get_PROPERTY, _after_get_PROPERTY
     * @param string $class_name
     * @return array
     */
    public static function get_property_hooks() : array
    {
        $class_name = get_called_class();

        $ret = array_merge(
            $class_name::get_get_property_hooks(),
            $class_name::get_set_property_hooks(),
        );
        return $ret;
    }

    /**
     * @return array
     */
    public static function get_get_property_hooks() : array
    {
        $class_name = get_called_class();

        $ret = array_merge(
            $class_name::get_before_get_property_hooks(),
            $class_name::get_after_get_property_hooks(),
        );
        return $ret;
    }

    /**
     * @return array
     */
    public static function get_set_property_hooks() : array
    {
        $class_name = get_called_class();

        $ret = array_merge(
            $class_name::get_before_set_property_hooks(),
            $class_name::get_after_set_property_hooks(),
        );
        return $ret;
    }

    /**
     * Returns the _before_set_PROPERTY hooks
     * @param string $class_name
     * @return array
     */
    public static function get_before_set_property_hooks() : array
    {
        $class_name = get_called_class();

        $ret = [];
        $properties = $class_name::get_property_names();
        foreach ($properties as $property) {
            $method = '_before_set_'.$property;
            if (method_exists($class_name, $method)) {
                $ret[] = $method;
            }
        }
        return $ret;
    }

    /**
     * Returns the _after_set_PROPERTY hooks
     * @param string $class_name
     * @return array
     */
    public static function get_after_set_property_hooks() : array
    {
        $class_name = get_called_class();

        $ret = [];
        $properties = $class_name::get_property_names();
        foreach ($properties as $property) {
            $method = '_after_set_'.$property;
            if (method_exists($class_name, $method)) {
                $ret[] = $method;
            }
        }
        return $ret;
    }

    /**
     * Returns the _before_get_PROPERTY hooks
     * @param string $class_name
     * @return array
     */
    public static function get_before_get_property_hooks() : array
    {
        $class_name = get_called_class();

        $ret = [];
        $properties = $class_name::get_property_names();
        foreach ($properties as $property) {
            $method = '_before_get_'.$property;
            if (method_exists($class_name, $method)) {
                $ret[] = $method;
            }
        }
        return $ret;
    }

    /**
     * Returns the _after_get_PROPERTY hooks
     * @param string $class_name
     * @return array
     */
    public static function get_after_get_property_hooks() : array
    {
        $class_name = get_called_class();

        $ret = [];
        $properties = $class_name::get_property_names();
        foreach ($properties as $property) {
            $method = '_after_get_'.$property;
            if (method_exists($class_name, $method)) {
                $ret[] = $method;
            }
        }
        return $ret;
    }
}