<?php


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

    public static function initialize_hooks_data() : void
    {

    }

    public static function get_property_validation_hooks() : array
    {
        return array_merge(static::get_dynamic_property_validation_hooks(), static::get_static_property_validation_hooks() );
    }

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
            self::get_before_set_property_hooks($class_name),
            self::get_after_set_property_hooks($class_name),
            self::get_before_get_property_hooks($class_name),
            self::get_after_get_property_hooks($class_name),
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