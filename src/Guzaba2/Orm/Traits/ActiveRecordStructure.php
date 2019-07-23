<?php


namespace Guzaba2\Orm\Traits;

use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Translator\Translator as t;


trait ActiveRecordStructure
{

    /**
     * Returns the field/property PHP type as string.
     * Optionally by reference as second argument it will be assigned a boolean can it hold a NULL value.
     * An optional third argument set by reference canbe provided to retrieve the default value as defined in the database.
     * @param string $field_name
     * @param bool &$is_nullable
     * @param mixed &$default_value
     * @return string
     * @throws RunTimeException If the provided $field_name is not supported by this object.
     * @author vesko@azonmedia.com
     * @created 20.10.2017
     * @since 0.7.1
     */
    public function get_field_type(string $field_name, bool &$is_nullable = NULL, &$default_value = NULL) : string
    {
        if (!$this->has_field($field_name)) {
            throw new RunTimeException(sprintf(t::_('The object of class "%s" does not have a field/property named "%s".'), get_class($this), $field_name ));
        }
        //$is_nullable = self::$columns_data[$field_name]['nullable'];
        $is_nullable = $this->is_field_nullable($field_name);
        //$default_value = self::$columns_data[$field_name]['default_value'];
        $default_value = $this->get_field_default_value($field_name);
//        if ($this->is_field_array($field_name)) {
//            //return 'string';//the value column on the arr_table and assoc_arr_table is string
//            return 'array';//but returning string will be very misleading so we better return array
//        }
        return self::$columns_data[$field_name]['php_type'];
    }

    /**
     * Returns the native type of the property/field name as in the database.
     * @param string $field_name
     * @return string
     * @throws RunTimeException If the provided $field_name is not supported by this object.
     * @author vesko@azonmedia.com
     * @created 20.10.2017
     * @since 0.7.1
     */
    public function get_field_native_type(string $field_name) : string
    {
        if (!$this->has_field($field_name)) {
            throw new RunTimeException(sprintf(t::_('The object of class "%s" does not have a field/property named "%s".'), get_class($this), $field_name ));
        }
        if ($this->is_field_array($field_name)) {
            //return 'string';//the value column on the arr_table and assoc_arr_table is string
            return 'array';//but returning string will be very misleading so we better return array (even that mysql actually doesnt support array as a native type (PG does!))
        }
        return self::$columns_data[$field_name]['native_type'];
    }

    /**
     * Returns an array with all the available data for the provided field.
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
     * @param string $field_name
     * @return mixed
     * @throws RunTimeException If the provided $field_name is not supported by this object.
     * @author vesko@azonmedia.com
     * @created 20.10.2017
     * @since 0.7.1
     */
    public function get_field_information(string $field_name) : array
    {
        if (!$this->has_field($field_name)) {
            throw new RunTimeException(sprintf(t::_('The object of class "%s" does not have a field/property named "%s".'), get_class($this), $field_name ));
        }
        //this will also work for array_field_names and assoc_array_field_names as self::$columns_data now contains the column information for the value column from the arr and assoc_arr tables
        return self::$columns_data[$field_name];
    }

    /**
     * This is an alias of has_field().
     * @param string $field_name
     * @return bool
     * @author vesko@azonmedia.com
     * @created 22.01.2018
     * @since 0.7.1
     */
    public function has_property(string $field_name) : bool
    {
        return $this->has_field($field_name);
    }

    /**
     * Checks does this object/class has a field/property named $field_name. This checks agains the DB structure (all tables and shards)
     * @param string $field_name
     * @return bool
     * @author vesko@azonmedia.com
     * @created 22.01.2018
     * @since 0.7.1
     */
    public function has_field(string $field_name) : bool
    {
        //return isset(self::$columns_data[$field_name]) || $this->is_field_array($field_name) || $field_name==$this->get_field_prefix().'_url_rewrite';
        return array_key_exists($field_name, self::$columns_data);
    }

    /**
     * Returns can the field hold a NULL value (is the corresponding column in the database nullable)
     * @param string $field_name
     * @return bool
     * @throws RunTimeException If the provided $field_name is not supported by this object.
     * @author vesko@azonmedia.com
     * @created 20.10.2017
     * @since 0.7.1
     */
    public function is_field_nullable(string $field_name) : bool
    {
        if (!$this->has_field($field_name)) {
            throw new RunTimeException(sprintf(t::_('The object of class "%s" does not have a field/property named "%s".'), get_class($this), $field_name ));
        }
//        if ($this->is_field_array($field_name)) {
//            return FALSE;//the array fields cant contain NULLs
//        }
        return self::$columns_data[$field_name]['nullable'];
    }

    /**
     * Returns the default value of the field as defined in the database.
     * @param string $field_name
     * @return mixed
     * @throws RunTimeException If the provided $field_name is not supported by this object.
     * @author vesko@azonmedia.com
     * @created 20.10.2017
     * @since 0.7.1
     */
    public function get_field_default_value(string $field_name) /*: mixed */
    {
        if (!$this->has_field($field_name)) {
            throw new RunTimeException(sprintf(t::_('The object of class "%s" does not have a field/property named "%s".'), get_class($this), $field_name ));
        }
//        if ($this->is_field_array($field_name)) {
//            return '';//the default value is empty string (the value column are of type string)
//        }

        return self::$columns_data[$field_name]['default_value'];
    }


}