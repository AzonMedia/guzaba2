<?php

declare(strict_types=1);

namespace Guzaba2\Orm\Traits;

use Azonmedia\Http\Method;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Orm\Store\Interfaces\StoreTransactionInterface;
use Guzaba2\Orm\Store\Memory;
use Guzaba2\Orm\Store\MemoryTransaction;
use Guzaba2\Transaction\Interfaces\TransactionalResourceInterface;
use Guzaba2\Transaction\TransactionManager;
use Guzaba2\Translator\Translator as t;
use Psr\Log\LogLevel;

trait ActiveRecordOverloading
{

    /**
     * The overloading is used so that all the columns/propertys fro mthe database can appear as properties on instance.
     * The logic of the framework includes handling also of multilanguage and array properties.
     * @param string $property The name of the property that is being accessed
     * @return mixed
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
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

    //why the below code is here... not needed
//        if (self::uses_meta() && !$this->is_modified() && !$this->is_new()) {
//            //if this is the first modification (and is not a new object - the new objects are not hooked)
//            //then a new revision "0" needs to be created in the store and the record to be hooked to it
//            //this is needed instead of just keeping the changes local in the object in case the same object is created in another scope in the code
//            //this new object should see the modifications that were already done in the parent scope
//            $pointer =& $this->Store->get_data_pointer_for_new_version(get_class($this), $this->get_primary_index());
//            $this->record_data =& $pointer['data'];
//            $this->meta_data =& $pointer['meta'];
//            $this->record_modified_data =& $pointer['modified'];
//        }

        if (!$this->property_hooks_are_disabled() && method_exists($this, '_before_get_' . $property)) {
            call_user_func_array([$this,'_before_get_' . $property], []);
        }

        if (array_key_exists($property, $this->record_data)) {
            $ret =& $this->record_data[$property];//must have the & here and return by ref to avoid the indirect modification of overloaded property error
        } elseif (array_key_exists($property, $this->meta_data)) {
            $ret = $this->meta_data[$property];//do not put reference here since these are not supposed to be arrays
        } else {
            throw new RunTimeException(sprintf(t::_('Trying to get a non existing property "%s" of instance of "%s" (ORM class).'), $property, get_class($this)));
        }

        if (!$this->property_hooks_are_disabled() && method_exists($this, '_after_get_' . $property)) {
            $ret = call_user_func_array([$this,'_after_get_' . $property], [$ret]);
        }
        return $ret;
    }

    /**
     * The overloading is used so that all the columns/propertys from the database can appear as properties on instance.
     * @param string $property The name of the property that is being accessed
     * @return mixed
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @see http://gitlab.azonmedia.com:4500/root/guzaba-framework/wikis/documentation-0.7/dynamic-properties
     *
     * This method returns by reference so that array properties can have their values modified directly
     *
     * @example
     * print $page->page_id
     *
     * This also supports accessing dynamic properties (properties that are derived from other)
     */
    public function __set(string $property, /* mixed */ $value): void
    {
        //if ($this->is_read_only()) {
        //must allow for properties that are class properties to be set
        //this is needed because these are initialized in _after_read
        if ($this->is_read_only() && !in_array($property, self::get_class_property_names())) {
            //throw new RunTimeException(sprintf(t::_('Trying to modify a read-only instance of class %s with id %s.'), get_class($this), $this->get_id() ), 0, NULL, 'aa5319b8-5664-4fd9-8580-79a4996fba8a' );
        }

        if (array_key_exists($property, $this->meta_data)) {
            throw new RunTimeException(sprintf(t::_('Trying to set a meta property "%1$s" on instance of "%2$s (ActiveRecord class). The meta properties are read only.'), $property, get_class($this) ));
        }

        if (!array_key_exists($property, $this->record_data)) {
            throw new RunTimeException(sprintf(t::_('Trying to set a non existing property "%s" on instance of "%s" (ActiveRecord class).'), $property, get_class($this) ));
        }

//read_only is set in constructor() if method is GET
//        if (Coroutine::inCoroutine()) {
//            $Request = Coroutine::getRequest();
//            if ($Request->getMethodConstant() === Method::HTTP_GET) {
//                throw new RunTimeException(sprintf(t::_('Trying to set a property on object of class %s with id %s in GET request.'), get_class($this), $this->get_id()));
//            }
//        }

        //the below cant be reached as it is not supposed to have SET/WRITE/DELETE on GET
        // if (!empty($this->meta_data['meta_is_deleted'])) {
        //     throw new RunTimeException(sprintf(t::_('Trying to set a property on a deleted object of class %s with ID %s.'), get_class($this), $this->get_id() ));
        // }

        //if there is an active ORM transaction then a copy of the objects data needs to be done


        //instead of unhooking we need to rehook it to a new version called "0" until saved
        //if this is a new object then we do not really need (or can) hook as there is no yet primary index
        //if (!$this->is_modified() && !$this->is_new()) {
        if (self::uses_meta() && !$this->is_modified() && !$this->is_new()) {
            //if this is the first modification (and is not a new object - the new objects are not hooked)
            //then a new revision "0" needs to be created in the store and the record to be hooked to it
            //this is needed instead of just keeping the changes local in the object in case the same object is created in another scope in the code
            //this new object should see the modifications that were already done in the parent scope
            $pointer =& $this->Store->get_data_pointer_for_new_version(get_class($this), $this->get_primary_index());
            $this->record_data =& $pointer['data'];
            $this->meta_data =& $pointer['meta'];
            $this->record_modified_data =& $pointer['modified'];

            //the object needs to be attached to the memory transaction only once
            /** @var Memory $OrmStore */
            $OrmStore = self::get_service('OrmStore');
            //if ($OrmStore instanceof Memory) {
            if ($OrmStore instanceof TransactionalResourceInterface) {
                /** @var TransactionManager $TXM */
                $TXM = self::get_service('TransactionManager');
                /** @var MemoryTransaction $Transaction */
                $Transaction = $TXM->get_current_transaction($OrmStore->get_resource_id());
                if ($Transaction && $Transaction instanceof StoreTransactionInterface) {
                    $Transaction->attach_object($this);
                }
            }
        }

        if (!array_key_exists($property, $this->record_data)) {
            throw new LogicException(sprintf(t::_('After obtaining a pointer for new version (Store::get_data_pointer_for_new_version()) the record_data does not have a key %1$s. This is probably a class property and a store in the store chain does not append the class properties with their default values to the returned data array.'), $property));
        }
        $old_value = $this->record_data[$property];

        if (!$this->property_hooks_are_disabled() && method_exists($this, '_before_set_' . $property)) {
            //call_user_func_array(array($this,'_before_set_'.$property),array($value));
            $value = call_user_func_array([$this,'_before_set_' . $property], [$value]);
        }


        if (is_float($this->record_data[$property]) && is_float($value)) {
            if (abs($this->record_data[$property] - $value) > 0.00001) {
                $is_modified = true;
            }
        } else {
            if ($this->record_data[$property] !== $value) {
                $is_modified = true;
            }
        }

        //the data modification tracking is disabled during the _after_read() section as there the class properties are initialized
        //and this initialization should not be tracked/counted as data modification
        if (!empty($is_modified) && !$this->is_modified_data_tracking_disabled()) {
            if (!array_key_exists($property, $this->record_modified_data)) {
                $this->record_modified_data[$property] = [];
            }
            $this->record_modified_data[$property][] = $old_value;
        }

        $this->assign_property_value($property, $value);

        if (!$this->property_hooks_are_disabled() && method_exists($this, '_after_set_' . $property)) {
            call_user_func_array([$this,'_after_set_' . $property], [$value]);
        }
    }

    /**
     * This will return TRUE if the property exists, even if the value is NULL!
     * You can also check does the class has a property with @see self::has_property()
     * @param string $property The name of the property that is being accessed
     * @return bool
     */
    public function __isset(string $property): bool
    {
        return array_key_exists($property, $this->record_data);
    }

    /**
     * This is to prevent unsetting properties. This is not allowed.
     * If the property is not a database column the call is forwarded to the overloading in the parent class (base - where unsetting properties is not allowed either).
     * @param string $property The name of the property that is being accessed
     * @return void
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public function __unset(string $property): void
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
            //if (in_array($method,$this->property_names)||in_array($method,$this->property_names_2)) {
            //if (in_array($method,$this->property_names)) {
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
     * This method uses @uses activeRecordValidation::validate_property_static() but unlike it does not support passing custom validation settings (against which the validation sohuld be performed instead of the configuration validation settings)
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
        $ret = self::validate_property_static($property, $args[0]);
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
     * Tobe used/called only by @param string $property
     * @param mixed $value
     * @return void
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     * @see self::__set()
     */
    private function assign_property_value($property, $value): void
    {
        $property_type = static::get_property_type($property, $is_nullable);
        if ($property_type !== gettype($value)) {
            if ($is_nullable && is_null($value)) {
                //if there is type mismatch it will be still OK if the value is NULL and the column is nullable
                $this->record_data[$property] = $value;
            } elseif (empty($value) && in_array(gettype($value), ['integer','int','double','float']) && in_array($property_type, ['integer','int','double','float'])) {
                // we don't need error on zeros - casting (float) 0 still treated as integer
                //$this->record_data[$property] = $this->_cast($property_type, $value);
                settype($value, $property_type);
                $this->record_data[$property] = $value;
            //also we should always allow INT to be set on FLOAT column but not the reverse - a FLOAT to be set to an INT column
                //$value is the value being set, $property_type is the type of the column
            } elseif (gettype($value) === 'double' && $property_type === 'integer') {
                //this is allowed
                //$this->record_data[$property] = $this->_cast($property_type, $value);
                settype($value, $property_type);
                $this->record_data[$property] = $value;
            //} else if (gettype($value) === 'integer' && $property_type === 'double') {//no need to explicitly have a case for this as it will go into the section below
            } else {
                //casting is needed
                if (self::CONFIG_RUNTIME['cast_properties_on_assignment']) {
                    //check if a nonnumeric string is assigned to an integer or double
                    if (gettype($value) === 'string' && in_array($property_type, ['integer','int','double','float']) && !is_numeric($value) && $value !== '') { //empty string is treated like 0
                        //we should allow $500 and convert it to 500
                        if ($value[0] === '$') {
                            $value = substr($value, 1);
                        }
                        //also 1,200.50 should be converted to 1200.50
                        if (strpos($value, ',') !== false) {
                            $value = str_replace(',', '', $value);
                        }

                        $value = trim($value);
                    } elseif (gettype($value) === 'boolean' && in_array($property_type, ['integer','int'])) {
                        //perhaps a BOOL column in the BD which accepts only 1 & 0
                        $value = (int) $value;
                    }


                    //after the transformations above lets check again...
                    if (gettype($value) === 'string' && in_array($property_type, ['integer','int','double','float']) && !is_numeric($value) && $value !== '') { //empty string is treated like 0
                        $message = sprintf(t::_('Trying to assign a string nonnumeric value "%s" to property "%s" of an instance of class "%s". The property "%s" is of type "%s".'), $value, $property, get_class($this), $property, $property_type);
                        if (self::CONFIG_RUNTIME['add_validation_error_on_property_cast']) {
                            $this->add_validation_error($property, self::V_WRONGTYPE, $message);
                        } else {
                            //this will be thrown even if THROW_EXCEPTION_ON_PROPERTY_CAST=FALSE because it is a major issue
                            throw new RunTimeException($message);
                        }
                        //we cant allow a string that parses to float (like "1.5") to be cast and assigned to an int
                    } elseif (gettype($value) === 'string' && in_array($property_type, ['integer','int']) && strpos($value, '.') !== false && $value !== '') { //empty string is treated like 0
                        $message = sprintf(t::_('Trying to assign a string value "%s" that contains a float number to property "%s" of an instance of class "%s". The property "%s" is of type "%s".'), $value, $property, get_class($this), $property, $property_type);
                        if (self::CONFIG_RUNTIME['add_validation_error_on_property_cast']) {
                            $this->add_validation_error($property, self::V_WRONGTYPE, $message);
                        } else {
                            //this will be thrown even if THROW_EXCEPTION_ON_PROPERTY_CAST=FALSE because it is a major issue
                            throw new RunTimeException($message);
                        }
                    } elseif (!is_array($value) && $property_type === 'array') {
                        $message = sprintf(t::_('Trying to assign a non array type "%s" to an array property "%s" of an instance of class "%s".'), gettype($value), $property, get_class($this));
                        if (self::CONFIG_RUNTIME['add_validation_error_on_property_cast']) {
                            $this->add_validation_error($property, self::V_WRONGTYPE, $message);
                        } else {
                            throw new RunTimeException($message);
                        }
                    }
                    if (!$value && $is_nullable) {
                        $value = null;
                    }
                    settype($value, $property_type);
                    $this->record_data[$property] = $value;

                    $message = sprintf(t::_('The property "%s" on instance of "%s" is of type "%s" but is being assigned value of type "%s".'), $property, get_class($this), $property_type, gettype($value));
                    if (self::CONFIG_RUNTIME['log_notice_on_property_cast']) {
                        //self::logger()::notice($message, self::logger()::OPTION_BACKTRACE );//suppress this logger due to too many errors
                        Kernel::log($message, LogLevel::NOTICE);
                    }
                    if (self::CONFIG_RUNTIME['throw_exception_on_property_cast']) {
                        throw new RunTimeException($message);
                    }
                    if (self::CONFIG_RUNTIME['add_validation_error_on_property_cast']) {
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


    /**
     * Updates the data type based on the structure.
     * Certain stores (like Redis) may loose the type and keep everything as string.
     * @param array $data
     * @return array
     */
    public static function fix_record_data_types(array $data): array
    {
        // altough we have lazy loading we need to store in record_data whatever we obtained - this will set the index (so get_index() works)
        $called_class = get_called_class();
        $ret = [];
        foreach ($data as $key => $value) {
            //$type = static::get_column_type($key, $nullable);
            if (static::has_property($key)) {
                $type = static::get_property_type($key, $nullable, $default_value);
                if ($type === null) {
                    throw new RunTimeException(sprintf(t::_('In the provided data to %s method there is a key named %s and the class %s does not have such a column.'), __METHOD__, $key));
                }
                settype($value, ($nullable && null === $value) ? 'null' : $type); //$this->_cast( ($nullable && null === $value) ? 'null' : $type , $value );
                $ret[$key] = $value;
            }
        }
        return $ret;
    }


    /**
     * It is invoked on @see write()
     * write() provides the record_data array as first argument
     * Checks for properties that are empty ==='' and if the property is int or float converts it as follows:
     * - if the column is nullable sets to NULL
     * - if the column has a default values it sets it to the default value
     * - otherwise sets to 0
     * It also checks if a field is ===NULL and the column is not nullable then converts as follows:
     * - strings to ''
     * - ints and floats to 0
     *
     * @param array $data_arr
     * @return array The provided array after the processing
     */
    public static function fix_data_arr_empty_values_type(array $data_arr): array
    {
        //$columns_data = self::$columns_data;
        //$columns_data = static::get_columns_data();
        //$columns_data = static::get_columns_and_properties_data();
        $columns_data = static::get_properties_data();



        foreach ($data_arr as $field_name => $field_value) {
            if ($field_value === '') {
                // there is no value - lets see what it has to be
                // if it is an empty string '' and it is of type int it must be converted to NULL if allowed or 0 otherwise
                // look for the field
                foreach ($columns_data as $column_name => $columns_datum) {
                    if ($columns_datum['name'] === $field_name) {
                        if ($columns_datum['php_type'] === 'string') {
                            // this is OK - a string can be empty
                        } elseif ($columns_datum['php_type'] === 'int' || $columns_datum['php_type'] === 'float' || $columns_datum['php_type'] === 'double') {
                            // check the default value - the default value may be set to NULL in the table cache but if the column is not NULL-able this means that there is no default value
                            // in this case we need to set it to 0
                            // even if the column is NULLable but threre is default value we must use the default value

                            if ($columns_datum['default_value'] !== null) {
                                //we have a default value and we must use it
                                $data_arr[$field_name] = $columns_datum['default_value'];
                            } elseif ($columns_datum['nullable']) {
                                $data_arr[$field_name] = null;
                            } else {
                                $data_arr[$field_name] = 0;
                            }
                        } else {
                            // ignore
                        }
                        break;// we found our column
                    }
                }
            } elseif ($field_value === null) {
                // we need to check does the column support this type
                // if it doesnt we need to cast it to 0 or ''
                // look for the field
                if (!$columns_data[$field_name]['nullable']) { // the column does not support NULL but the value is null
                    // we will need to cast it
                    if ($columns_data[$field_name]['php_type'] === 'string') {
                        $data_arr[$field_name] = '';
                    } elseif ($columns_data[$field_name]['php_type'] === 'int') {
                        $data_arr[$field_name] = 0;
                    } elseif ($columns_data[$field_name]['php_type'] === 'float' || $columns_data[$field_name]['php_type'] === 'double') {
                        $data_arr[$field_name] = 0.0;
                    } else {
                        // ignore for now - let it throw an error
                    }
                }
            }
        }

        return $data_arr;
    }

    /*
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
    */

    public function disable_property_hooks(): void
    {
        $this->disable_property_hooks_flag = true;
    }

    public function enable_property_hooks(): void
    {
        $this->disable_property_hooks_flag = false;
    }

    public function property_hooks_are_disabled(): bool
    {
        return $this->disable_property_hooks_flag;
    }

    public function disable_method_hooks(): void
    {
        $this->disable_method_hooks_flag = true;
    }

    public function enable_method_hooks(): void
    {
        $this->disable_method_hooks_flag = false;
    }

    public function method_hooks_are_disabled(): bool
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
