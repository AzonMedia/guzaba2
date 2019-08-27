<?php

namespace Guzaba2\Orm\Traits;

use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Translator\Translator as t;

trait ActiveRecordOverloading
{

    /**
     * The overloading is used so that all the columns/fields fro mthe database can appear as properties on instance.
     * The logic of the framework includes handling also of multilanguage and array properties.
     * @param string $property The name of the property that is being accessed
     * @return mixed
     * @throws RunTimeException
     * @see http://gitlab.azonmedia.com:4500/root/guzaba-framework/wikis/documentation-0.7/dynamic-properties
     *
     * This method returns by reference so that array properties can have their values modified directly
     *
     * @example
     * print $page->page_id
     * print $page->en->page_title
     * print $blog->blog_tag_id[1];
     *
     * This also supports accessing dynamic properties (properties that are derived from other)
     */
    public function &__get(string $property) /* mixed */
    {
        if (!$this->property_hooks_are_disabled() && method_exists($this, '_before_get_'.$property)) {
            call_user_func_array([$this,'_before_get_'.$property], []);
        }

        if (array_key_exists($property, $this->record_data)) {
            $ret = $this->record_data[$property];
        } else {
            throw new RunTimeException(sprintf(t::_('Trying to get a non existing property/field "%s" of instance of "%s" (ORM class).'), $property, get_class($this)));
        }

        if (!$this->property_hooks_are_disabled() && method_exists($this, '_after_get_'.$property)) {
            $ret = call_user_func_array([$this,'_after_get_'.$property], [$ret]);
        }
        return $ret;
    }

    /**
     * The overloading is used so that all the columns/fields fro mthe database can appear as properties on instance.
     * The logic of the framework includes handling also of multilanguage and array properties.
     * @param string $property The name of the property that is being accessed
     * @return mixed
     * @throws RunTimeException
     * @see http://gitlab.azonmedia.com:4500/root/guzaba-framework/wikis/documentation-0.7/dynamic-properties
     *
     * This method returns by reference so that array properties can have their values modified directly
     *
     * @example
     * print $page->page_id
     * print $page->en->page_title
     * print $blog->blog_tag_id[1];
     *
     * This also supports accessing dynamic properties (properties that are derived from other)
     */


    public function __set(string $property, /* mixed */ $value) : void
    {

        $this->unhook_data_pointer();

        if (!$this->property_hooks_are_disabled() && method_exists($this, '_before_set_'.$property)) {
            //call_user_func_array(array($this,'_before_set_'.$property),array($value));
            $value = call_user_func_array([$this,'_before_set_'.$property], [$value]);
        }


        if (!$this->property_hooks_are_disabled() && method_exists($this, '_after_set_'.$property)) {
            call_user_func_array([$this,'_after_set_'.$property], [$value]);
        }

        if (array_key_exists($property, $this->record_data)) {
            if (is_float($this->record_data[$property]) && is_float($value)) {
                if (abs($this->record_data[$property] - $value) > 0.00001) {
                    $this->record_modified_data[] = $property;
                    $this->is_modified_flag = true;
                }
            } else {
                if ($this->record_data[$property]!=$value) {
                    $this->record_modified_data[] = $property;
                    $this->is_modified_flag = true;
                }
            }
            //$this->record_data[$property] = $value;


            $this->assign_property_value($property, $value);
        } else {
            throw new RunTimeException(sprintf(t::_('Trying to set a non existing property/field "%s" of instance of "%s" (ORM class).'), $property, get_class($this)));
        }

        if (!$this->property_hooks_are_disabled() && method_exists($this, '_after_set_'.$property)) {
            call_user_func_array([$this,'_after_set_'.$property], [$value]);
        }
    }

    private function unhook_data_pointer() : void
    {
        //unhook the record_data pointer from the central storage (this is valid only if it is the MemoryStore)
        $record_data = $this->record_data;
        $this->record_data =& $record_data;
        $meta_data = $this->meta_data;
        $this->meta_data =& $meta_data;
    }

    /**
     * This will return TRUE if the property exists, even if the value is NULL!
     * You can also check does the class has a property with @see self::has_property()
     * @param string $property The name of the property that is being accessed
     * @return bool
     */
    public function __isset(string $property) : bool
    {
        return array_key_exists($property, $this->record_data);
    }

    /**
     * This is to prevent unsetting properties. This is not allowed.
     * If the property is not a database column the call is forwarded to the overloading in the parent class (base - where unsetting properties is not allowed either).
     * @param string $property The name of the property that is being accessed
     * @return void
     * @throws RunTimeException
     */
    public function __unset(string $property) : void
    {
        throw new RunTimeException(sprintf(t::_('It is not allowed to unset overloaded properties on ORM classes.')));
    }

    /**
     * Allows to have properties set using method chaining like $object->prop1($val)->prop2($val2) ...
     * @param string $method Name of the called method
     * @param array $args The arguments provided to the method (in the method chaining a single argument is expected)
     */
    /*
    public function __call(string $method, array $args) {

        if (isset($args[0])) { //use the method as setter only if there is a value provided
            //if (in_array($method,$this->field_names)||in_array($method,$this->field_names_2)) {
            //if (in_array($method,$this->field_names)) {
            if (in_array($method, array_keys($this->record_data) )) {
                $this->__set($method, $args[0]);
                return $this;//allow method chaining
            } else {
                return parent::__call($method, $args);
            }
        }
        return parent::__call($method, $args);

        //because statically calling a method in object context triggers __call() (instead of __callStatic() ) this method cant be used
    }
    */

    /**
     * It is used to provide overloading for the validation methods.
     * @example user::_validate_static_subject_name($some_value);
     * This method uses @uses activeRecordValidation::validate_field_static() but unlike it does not support passing custom validation settings (against which the validation sohuld be performed instead of the configuration validation settings)
     * @param string $method The method name should be like _validate_static_PROPERTYNAME
     * @param array $args There should be only a single argument provided to the overloaded method so the args should be an indexed array with one element
     * @return array | object If this is a service it may return an object (service), otherwise it should return a twodimensional indexed array with validation errors.
     * @throws RunTimeException
     * @author vesko@azonmedia.com
     * @created 02.09.2018
     * @since 0.7.1
     */
    /*
    public static function __callStatic(string $method, array $args)
    {

        $ret = [];
        $class = get_called_class();
        if (is_subclass_of($class, framework\orm\classes\activeRecordVersioned::class)) {
            $instance =& static::get_instance(0, framework\orm\classes\activeRecordVersioned::VERSION_LAST, $INSTANCE);
        } else {
            $instance =& static::get_instance(0, $INSTANCE);
        }

        if (strpos($method, '_validate_static_')===FALSE) {
            //throw new RunTimeException(sprintf(t::_('An unknown static method "%s" was invoked on class "%s". The static overloading __callStatic supports only property validation method that are to be invoked like %s::_validate_static_PROPERTYNAME($value).'), $method, get_class($instance), get_class($instance) ));
            $ret = parent::__callStatic($method, $args);
            return $ret;
        }
        $property = str_replace('_validate_static_', '', $method);
        if (!$instance->has_property($property)) {
            throw new RunTimeException(sprintf(t::_('The class "%s" does not support a property named "%s".'), get_class($instance), $property));
        }
        if (!count($args)) {
            throw new RunTimeException(sprintf(t::_('There was no argument/value provided to %s::%s(). A value against which the validation will be performed is required.'), get_class($instance), $method ));
        }
        if (count($args)>1) {
            throw new RunTimeException(sprintf(t::_('More tha one argument/value was provided to %s::%s(). Only one value against which the validation will be performed must be provided.'), get_class($instance), $method ));
        }
        if (!is_scalar($args[0]) && !is_array($args[0])) {
            throw new RunTimeException(sprintf(t::_('An unsupported value of type "%s" was provided to %s::%s(). The provided value against which the validation will be performed must be of the scalar or array.'), gettype($args[0]), get_class($instance), $method ));
        }
        $ret = self::validate_field_static($property, $args[0]);
        return $ret;
    }
    */

    /**
     *
     * @implements \ArrayAccess
     */
    public function offsetExists($offset)
    {
        return isset($this->$offset);
    }

    /**
     *
     * @implements \ArrayAccess
     */
    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    /**
     *
     * @implements \ArrayAccess
     */
    public function offsetSet($offset, $value)
    {
        $this->$offset = $value;
    }

    /**
     *
     * @implements \ArrayAccess
     */
    public function offsetUnset($offset)
    {
        unset($this->$offset);
    }

    /**
     *
     * @implements \Countable
     */
    public function count()
    {
        //return count($this->record_data) + count($this->record_data_2);
        return count($this->record_data);
    }

    /**
     * Checks the type of the provided value against the expected type and if there is mismatch it may:
     * - cast to the correct type
     * - write a NOTICE
     * - throw a framework\base\exception\runTimeException
     * depending on the constants:
     * const CAST_PROPERTIES_ON_ASSIGNMENT = TRUE;
     * const LOG_NOTICE_ON_PROPERTY_CAST = TRUE;
     * const THROW_EXCEPTION_ON_PROPERTY_CAST = FALSE;
     * Tobe used/called only by @see self::__set()
     * @param string $property
     * @param mixed $value
     * @return void
     * @throws RunTimeException
     */
    private function assign_property_value($property, $value) : void
    {
        $field_type = $this->get_field_type($property, $is_nullable);
        if ($field_type != gettype($value)) {
            if ($is_nullable && is_null($value)) {
                //if there is type mismatch it will be still OK if the value is NULL and the column is nullable
                $this->record_data[$property] = $value;
            } elseif (empty($value) && in_array(gettype($value), ['integer','int','double','float']) && in_array($field_type, ['integer','int','double','float'])) {
                // we don't need error on zeros - casting (float) 0 still treated as integer
                $this->record_data[$property] = $this->_cast($field_type, $value);
            //also we should always allow INT to be set on FLOAT column but not the reverse - a FLOAT to be set to an INT column
                //$value is the value being set, $field_type is the type of the column
            } elseif (gettype($value)=='double' && $field_type=='integer') {
                //this is allowed
                $this->record_data[$property] = $this->_cast($field_type, $value);
            //} else if (gettype($value)=='integer' && $field_type=='double') {//no need to explicitly have a case for this as it will go into the section below
            } else {
                //casting is needed
                if (self::CAST_PROPERTIES_ON_ASSIGNMENT) {

                    //check if a nonnumeric string is assigned to an integer or double
                    if (gettype($value)=='string' && in_array($field_type, ['integer','int','double','float']) && !is_numeric($value) && $value!='') { //empty string is treated like 0
                        //we should allow $500 and convert it to 500
                        if ($value[0]=='$') {
                            $value = substr($value, 1);
                        }
                        //also 1,200.50 should be converted to 1200.50
                        if (strpos($value, ',')!==FALSE) {
                            $value = str_replace(',', '', $value);
                        }

                        $value = trim($value);
                    }

                    //after the transformations above lets check again...
                    if (gettype($value)=='string' && in_array($field_type, ['integer','int','double','float']) && !is_numeric($value) && $value!='') { //empty string is treated like 0
                        $message = sprintf(t::_('Trying to assign a string nonnumeric value "%s" to property "%s" of an instance of class "%s". The property "%s" is of type "%s".'), $value, $property, get_class($this), $property, $field_type);
                        if (self::ADD_VALIDATION_ERROR_ON_PROPERTY_CAST) {
                            $this->add_validation_error($property, self::V_WRONGTYPE, $message);
                        } else {
                            //this will be thrown even if THROW_EXCEPTION_ON_PROPERTY_CAST=FALSE because it is a major issue
                            throw new RunTimeException($message);
                        }
                        //we cant allow a string that parses to float (like "1.5") to be cast and assigned to an int
                    } elseif (gettype($value)=='string' && in_array($field_type, ['integer','int']) && strpos($value, '.')!==FALSE && $value!='') { //empty string is treated like 0
                        $message = sprintf(t::_('Trying to assign a string value "%s" that contains a float number to property "%s" of an instance of class "%s". The property "%s" is of type "%s".'), $value, $property, get_class($this), $property, $field_type);
                        if (self::ADD_VALIDATION_ERROR_ON_PROPERTY_CAST) {
                            $this->add_validation_error($property, self::V_WRONGTYPE, $message);
                        } else {
                            //this will be thrown even if THROW_EXCEPTION_ON_PROPERTY_CAST=FALSE because it is a major issue
                            throw new RunTimeException($message);
                        }
                    } elseif (!is_array($value) && $field_type=='array') {
                        $message = sprintf(t::_('Trying to assign a non array type "%s" to an array property "%s" of an instance of class "%s".'), gettype($value), $property, get_class($this));
                        if (self::ADD_VALIDATION_ERROR_ON_PROPERTY_CAST) {
                            $this->add_validation_error($property, self::V_WRONGTYPE, $message);
                        } else {
                            throw new RunTimeException($message);
                        }
                    }
                    if (!$value && $is_nullable) {
                        $value = NULL;
                    }
                    $this->record_data[$property] = $this->_cast($field_type, $value);

                    $message = sprintf(t::_('The property "%s" on instance of "%s" is of type "%s" but is being assigned value of type "%s".'), $property, get_class($this), $field_type, gettype($value));
                    if (self::LOG_NOTICE_ON_PROPERTY_CAST) {
                        //self::logger()::notice($message, self::logger()::OPTION_BACKTRACE );//suppress this logger due to too many errors
                    }
                    if (self::THROW_EXCEPTION_ON_PROPERTY_CAST) {
                        throw new RunTimeException($message);
                    }
                    if (self::ADD_VALIDATION_ERROR_ON_PROPERTY_CAST) {
                        $this->add_validation_error($property, self::V_WRONGTYPE, $message);
                    }
                } else {
                    $this->record_data[$property] = $value;
                }
            }
        } else {
            //the type matches - no need to cast
            $this->record_data[$property] = $value;
        }
    }

    public static function get_properties_with_enabled_logging() : array
    {
        $ret = [];
        $called_class = get_called_class();

        static $cache = [];
        if (isset($cache[$called_class])) {
            return $cache[$called_class];
        }

        $classes = array_merge([$called_class], class_parents($called_class));
        if (defined($called_class.'::LOGGING_ENABLED_FOR_SETTING_PROPERTIES') && is_array(static::LOGGING_ENABLED_FOR_SETTING_PROPERTIES)) {
            //go through all the parents
            foreach ($classes as $class) {
                if (defined($class.'::LOGGING_ENABLED_FOR_SETTING_PROPERTIES') && is_array($class::LOGGING_ENABLED_FOR_SETTING_PROPERTIES)) {
                    $ret = array_merge($ret, $class::LOGGING_ENABLED_FOR_SETTING_PROPERTIES);
                }
            }
        }

        $ret = array_values(array_unique($ret));

        $cache[$called_class] = $ret;
        return $ret;
    }

    public function disable_property_hooks(): void
    {
        $this->disable_property_hooks_flag = TRUE;
    }

    public function enable_property_hooks(): void
    {
        $this->disable_property_hooks_flag = FALSE;
    }

    public function property_hooks_are_disabled() : bool
    {
        return $this->disable_property_hooks_flag;
    }

    public function disable_method_hooks() : void
    {
        $this->disable_method_hooks_flag = true;
    }

    public function enable_method_hooks() : void
    {
        $this->disable_method_hooks_flag = false;
    }

    public function method_hooks_are_disabled() : bool
    {
        return $this->disable_method_hooks_flag;
    }

    /**
     * Resets the properties of the object as provided in the array.
     * To be used only by the object\transaction
     * @param array $properties
     * @return void
     */
    public function _set_all_properties(array $properties): void
    {
        //we do not want to trigger the _before_set_propertyname hooks if there are such
        //the rollback must be transparent
        $this->disable_property_hooks();
        parent::_set_all_properties($properties);
        $this->enable_property_hooks();
    }
}
