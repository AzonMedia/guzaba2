<?php

declare(strict_types=1);

namespace Guzaba2\Orm\Traits;

use Azonmedia\Reflection\ReflectionClass;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Translator\Translator as t;

trait ActiveRecordStructure
{

    /**
     * @return array
     * @throws RunTimeException
     */
    public static function get_structure(): array
    {
        if (empty(static::CONFIG_RUNTIME['structure'])) {
            throw new RunTimeException(sprintf(t::_('Class %s doesn\'t have structure defined in its configuration'), static::class));
        }
        return static::CONFIG_RUNTIME['structure'];
    }

    /**
     * To be called by ClassInitialization
     * @throws RunTimeException
     */
    public static function initialize_structure(): void
    {
        $Store = static::get_service('OrmStore');

        $called_class = get_called_class();

        if (empty(self::$columns_data[$called_class])) {
            $unified_columns_data = $Store->get_unified_columns_data($called_class);
            if (!count($unified_columns_data)) {
                throw new RunTimeException(sprintf(t::_('No data structure found for class %s. If you are using a StructuredStoreInterface please make sure the table defined in CONFIG_DEFAULTS[\'main_table\'] is correct or else that the class has defined CONFIG_DEFAULTS[\'structure\'].'), $called_class));
            }

            foreach ($unified_columns_data as $column_datum) {
                self::$columns_data[$called_class][$column_datum['name']] = $column_datum;
            }
        }

        if (empty(self::$primary_index_columns[$called_class])) {
            self::$primary_index_columns[$called_class] = [];
            foreach (self::$columns_data[$called_class] as $column_name => $column_data) {
                if (!empty($column_data['primary'])) {
                    self::$primary_index_columns[$called_class][] = $column_name;
                }
            }
        }

        if (empty(self::$properties_data[$called_class])) {
            self::$properties_data[$called_class] = [];
            $RClass = new ReflectionClass($called_class);
            $default_properties = $RClass->getDefaultProperties();
            //foreach ($RClass->getProperties() as $RProperty) {
            //foreach ($RClass->getOwnDynamicProperties(\ReflectionProperty::IS_PUBLIC) as $RProperty) {
            foreach ($RClass->getDynamicPropertiesUpToParentClass(\ReflectionProperty::IS_PUBLIC, ActiveRecord::class) as $RProperty) {
                //if ($RProperty->isPublic() && !$RProperty->isStatic()) {
//                    if ($RProperty->isInitialized()) {
//                        $default_value = $default_properties[$RProperty->getName()];
//                    } else {
//                        $default_value = NULL;//this is not really correct...
//                    }
                    if (array_key_exists($RProperty->getName(), $default_properties)) {
                        $default_value = $default_properties[$RProperty->getName()];
                    } else {
                        $default_value = null;//this is not correct as the property may not even be nullable... but we need to have some value
                    }
                    if ($RProperty->hasType()) {
                        $type = $RProperty->getType()->getName();
                        $nullable = $RProperty->getType()->allowsNull();
                        if ($nullable) {
                            $default_value = null;
                        }
                    } else {
                        //do not allow untyped properties
                        throw new RunTimeException(sprintf(t::_('The ActiveRecord class %s has a property %s which is missing type.'), $called_class, $RProperty->getName()));
                    }

                    self::$properties_data[$called_class][$RProperty->getName()] = [
                        'name'          => $RProperty->getName(),
                        'native_type'   => $type,
                        'php_type'      => $type,
                        'size'          => 0, //not applicable as for example the string type has no size
                        'nullable'      => $nullable,
                        'column_id'     => 0,//not a real column
                        'default_value' => $default_value,
                        'autoincrement' => 0,
                        'key_name'      => '',
                        'key_reference' => '',
                    ];
                //}
            }//end foreach
        }
    }

    /**
     * Returns the property/property PHP type as string.
     * Optionally by reference as second argument it will be assigned a boolean can it hold a NULL value.
     * An optional third argument set by reference canbe provided to retrieve the default value as defined in the database.
     * @param string $property_name
     * @param bool &$is_nullable
     * @param mixed &$default_value
     * @return string
     * @throws RunTimeException If the provided $property_name is not supported by this object.
     */
    public static function get_property_type(string $property_name, bool &$is_nullable = null, &$default_value = null): string
    {
        $class = get_called_class();
        if (!static::has_property($property_name)) {
            throw new RunTimeException(sprintf(t::_('The object of class "%s" does not have a property/property named "%s".'), $class, $property_name));
        }
        $is_nullable = static::is_property_nullable($property_name);
        $default_value = static::get_property_default_value($property_name);
        //return static::get_columns_data()[$property_name]['php_type'];
        return static::get_properties_data()[$property_name]['php_type'];
    }

    /**
     * Returns the native type of the property/property name as in the database.
     * @param string $property_name
     * @return string
     * @throws RunTimeException If the provided $property_name is not supported by this object.
     */
    public static function get_property_native_type(string $property_name): string
    {
        $class = get_called_class();
        if (!static::has_property($property_name)) {
            throw new RunTimeException(sprintf(t::_('The object of class "%s" does not have a property/property named "%s".'), $class, $property_name));
        }
        if (static::is_property_array($property_name)) {
            //return 'string';//the value column on the arr_table and assoc_arr_table is string
            return 'array';//but returning string will be very misleading so we better return array (even that mysql actually doesnt support array as a native type (PG does!))
        }
        //return static::get_columns_data()[$property_name]['native_type'];
        return static::get_properties_data()[$property_name]['native_type'];
    }

    /**
     * Returns an array with all the available data for the provided property.
     * @example $arr =  array (
     * 'name' => 'fee_is_admin',//the name of the column
     * 'native_type' => 'tinyint',//the type of the column in the database
     * 'php_type' => 'int',//the corresponding php type
     * 'size' => 3,//the size on the column in the DB
     * 'nullable' => true,//can it hold a NULL value
     * 'column_id' => 16,//the position of the column in the table
     * 'primary' => false,//is it a primary key
     * 'default_value' => '0',//the default value of the column in the table
     * 'autoincrement' => false,//is it an autoincrement column
     * );
     * @param string $property_name
     * @return mixed
     * @throws RunTimeException If the provided $property_name is not supported by this object.
     */
    public static function get_property_information(string $property_name): array
    {
        if (!static::has_property($property_name)) {
            throw new RunTimeException(sprintf(t::_('The object of class "%s" does not have a property/property named "%s".'), $class, $property_name));
        }
        //this will also work for array_property_names and assoc_array_property_names as self::$columns_data now contains the column information for the value column from the arr and assoc_arr tables
        //return static::get_columns_data()[$property_name];
        return static::get_properties_data()[$property_name];
    }

    /**
     * Returns an indexed array with the names of the meta properties.
     * @return string[]
     */
    public static function get_meta_property_names(): array
    {
        return ActiveRecordInterface::META_TABLE_COLUMNS;
    }

    /**
     * Returns an indexed array containing the names of all properties and columns.
     * @return string[]
     */
    public static function get_property_names(): array
    {
        //return array_column(static::get_columns_data(), 'name');
        return array_column(static::get_properties_data(), 'name');
    }

    /**
     * Returns the names of the columns in the database
     * @return string[]
     */
    public static function get_column_names(): array
    {
        return array_column(static::get_columns_data(), 'name');
    }

    /**
     * Returns the names of the class properties
     * @return string[]
     */
    public static function get_class_property_names(): array
    {
        return array_column(static::get_class_properties_data(), 'name');
    }

    /**
     * @return array
     */
    public static function get_property_names_with_types(): array
    {
        $ret = [];
        //$columns_data = static::get_columns_data();
        $columns_data = static::get_properties_data();
        foreach ($columns_data as $columns_datum) {
            $ret[$columns_datum['name']] = $columns_datum['type'];
        }
        return $ret;
    }

    /**
     * Checks does this object/class has a property named $property_name. This checks agains the DB structure (all tables and shards)
     * @param string $property_name
     * @return bool
     */
    public static function has_property(string $property_name): bool
    {
        //return array_key_exists($property_name, static::get_columns_data());
        return array_key_exists($property_name, static::get_properties_data());
    }


    /**
     * Returns can the property hold a NULL value (is the corresponding column in the database nullable)
     * @param string $property_name
     * @return bool
     * @throws RunTimeException If the provided $property_name is not supported by this object.
     */
    public static function is_property_nullable(string $property_name): bool
    {
        if (!self::has_property($property_name)) {
            throw new RunTimeException(sprintf(t::_('The object of class "%s" does not have a property/property named "%s".'), $class, $property_name));
        }
        //return static::get_columns_data()[$property_name]['nullable'];
        return static::get_properties_data()[$property_name]['nullable'];
    }

    /**
     * Returns the default value of the property as defined in the database.
     * @param string $property_name
     * @return mixed
     * @throws RunTimeException If the provided $property_name is not supported by this object.
     */
    public static function get_property_default_value(string $property_name) /*: mixed */
    {
        $class = get_called_class();
        if (!static::has_property($property_name)) {
            throw new RunTimeException(sprintf(t::_('The object of class "%s" does not have a property/property named "%s".'), $class, $property_name));
        }
        //return static::get_columns_data()[$property_name]['default_value'];
        return static::get_properties_data()[$property_name]['default_value'];
    }
}
