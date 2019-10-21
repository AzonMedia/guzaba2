<?php
declare(strict_types=1);

namespace Guzaba2\Orm;

use Azonmedia\Lock\Interfaces\LockInterface;
use Azonmedia\Reflection\ReflectionClass;

use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Traits\StaticStore;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Http\Method;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\MetaStore\MetaStore;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Orm\Store\Memory;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Event\Event;



use Guzaba2\Orm\Traits\ActiveRecordOverloading;
use Guzaba2\Orm\Traits\ActiveRecordSave;
use Guzaba2\Orm\Traits\ActiveRecordLoad;
use Guzaba2\Orm\Traits\ActiveRecordStructure;

//use Guzaba2\Orm\Traits\ActiveRecordValidation;
//use Guzaba2\Orm\Traits\ActiveRecordDynamicProperties;
//use Guzaba2\Orm\Traits\ActiveRecordDelete;


class ActiveRecord extends Base implements ActiveRecordInterface
{
    protected const CONFIG_DEFAULTS = [
        'services'      => [
            //'ConnectionFactory',
            'OrmStore',
            'LockManager',
        ]
    ];

    protected const CONFIG_RUNTIME = [];


    //for the porpose of splitting and organising the methods (as this class would become too big) traits are used
    use ActiveRecordOverloading;
    use ActiveRecordStructure;

    const INDEX_NEW = 0;

    /**
     * @var StoreInterface
     */
    protected $Store;

    /**
     * @var bool
     */
    protected $is_new_flag = TRUE;

    /**
     * @var array
     */
    protected $record_data = [];

    /**
     * @var array
     */
    protected $record_modified_data = [];

    /**
     * @var array
     */
    protected $meta_data = [];
    
    /**
     * @var bool
     */
    protected $disable_property_hooks_flag = FALSE;
    
    /**
     * Are the method hooks like _before_save enabled or not.
     * @see activeRecord::disable_method_hooks()
     * @see activeRecord::enable_method_hooks()
     *
     * @var bool
     */
    protected $disable_method_hooks_flag = FALSE;
    
    /**
     * @var bool
     */
    //protected $validation_is_disabled_flag = FALSE;
  


    /**
     * Contains the unified record structure for this class.
     * @see StoreInterface::UNIFIED_COLUMNS_STRUCTURE
     * While in Swoole/coroutine context static variables shouldnt be used here it is acceptable as this structure is not expected to change ever during runtime once it is assigned.
     * @var array
     */
    protected static $columns_data = [];

    /**
     * Contains the unified record structure for this class meta data (ownership data).
     * @see StoreInterface::UNIFIED_COLUMNS_STRUCTURE
     * While in Swoole/coroutine context static variables shouldnt be used here it is acceptable as this structure is not expected to change ever during runtime once it is assigned.
     * @var array
     */
    protected static $meta_columns_data = [];

    /**
     * Contains an indexed array with the name of the primary key columns. usually it is one but can be more.
     * @var array
     */
    protected static $primary_index_columns = [];

    /**
     * Should locking be used when creating (read lock) and saving (write lock) objects.
     * It can be set to FALSE if the REST method used doesnt imply writing.
     * This property is to be used only in NON-coroutine mode.
     * In coroutine mode the Context is to be used.
     * @see self::is_locking_enabled()
     * @var bool
     */
    protected static $locking_enabled_flag = TRUE;

    /**
     * ActiveRecord constructor.
     * @param int $index
     * @param StoreInterface|null $Store
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Guzaba2\Kernel\Exceptions\ConfigurationException
     */
    public function __construct(/* mixed*/ $index = self::INDEX_NEW, ?StoreInterface $Store = NULL)
    {
        parent::__construct();

        if (!isset(static::CONFIG_RUNTIME['main_table'])) {
            throw new RunTimeException(sprintf(t::_('ActiveRecord class %s does not have "main_table" entry in its CONFIG_RUNTIME.'), get_called_class()));
        }

        //$this->locking_enabled_flag = self::is_locking_enabled();
        
        //if ($this->index == self::INDEX_NEW) { //checks is the index already set (as it may be set in _before_construct()) - if not set it
        //    $this->index = $index;
        //}

        if ($Store) {
            $this->Store = $Store;
        } else {
            $this->Store = static::OrmStore();//use the default service
        }
        

        $called_class = get_class($this);
        if (empty(self::$columns_data[$called_class])) {
            $unified_columns_data = $this->Store->get_unified_columns_data(get_class($this));

            foreach ($unified_columns_data as $column_datum) {
                self::$columns_data[$called_class][$column_datum['name']] = $column_datum;
            }
        }

        if (empty(self::$primary_index_columns[$called_class])) {
            foreach (self::$columns_data[$called_class] as $column_name=>$column_data) {
                if (!empty($column_data['primary'])) {
                    self::$primary_index_columns[$called_class][] = $column_name;
                }
            }
        }

        $primary_columns = static::get_primary_index_columns();

        // 1. check is there main index loaded
        // if $this->index is still empty this means that this table has no primary index
        if (!count($primary_columns)) {
            throw new \Guzaba2\Kernel\Exceptions\ConfigurationException(sprintf(t::_('The class %s has no primary index defined.'), $called_class));
        }
        
        if (is_scalar($index)) {
            if (ctype_digit($index)) {
                $index = (int) $index;
            }

            // if the primary index is compound and the provided $index is a scalar throw an error - this could be a mistake by the developer not knowing that the primary index is compound and providing only one component
            // providing only one component for the primary index is still supported but needs to be provided as array
            if (count($primary_columns) === 1) {
                $index = [$primary_columns[0] => $index];
            } else {
                $message = sprintf(t::_('The class "%s" with primary table "%s" has a compound primary index consisting of "%s". Only a single scalar value "%s" was provided to the constructor which could be an error. For classes that use compound primary indexes please always provide arrays. If needed it is allowed the provided array to have less keys than components of the primary key.'), $called_class, static::get_main_table(), implode(', ', $primary_columns), $index);
                throw new InvalidArgumentException($message);
            }
        } elseif (is_array($index)) {
            // no check for count($this->index)==count(self::$primary_index_columns) as an array with some criteria may be supplied instead of index
            // no change
        } else {
            throw new \Guzaba2\Base\Exceptions\RunTimeException(sprintf(t::_('An unsupported type "%s" was supplied for the index of object of class "%s".'), gettype($index), get_class($this)));
        }

        if (isset($index[$primary_columns[0]]) && $index[$primary_columns[0]] === self::INDEX_NEW) {
            $this->record_data = $this->Store::get_record_structure(static::get_columns_data());
        //the new records are not referencing the OrmStore
            //no locking here either
        } else {
            $this->load($index);
        }
    }

    public function __destruct()
    {
        if (!$this->is_new()) {
            $this->Store->free_pointer($this);
        }


        if (self::is_locking_enabled()) {
            $resource = MetaStore::get_key_by_object($this);
            self::LockManager()->release_lock($resource);
        }
    }

    final public function __toString() : string
    {
        return MetaStore::get_key_by_object($this);
    }

    public function _before_change_context() : void
    {
        if ($this->is_modified()) {
            $message = sprintf(t::_('It is not allowed to pass modified but unsaved ActiveRecord objects between coroutines. The object of class %s with index %s is modified but unsaved.'), get_class($this), print_r($this->get_primary_index(), TRUE));
            throw new RunTimeException($message);
        }
    }

    /**
     * Based on the REST method type the locking may be disabled if the request is read only.
     */
    public static function enable_locking() : void
    {
        //cant use a local static var - this is shared between the coroutines
        //self::set_static('locking_enabled_flag', TRUE);
        if (Coroutine::inCoroutine()) {
            $Context = Coroutine::getContext();
            $Context->locking_enabled_flag = TRUE;
        } else {
            self::$locking_enabled_flag = TRUE;
        }
    }

    public static function disable_locking() : void
    {
        //cant use a local static var - this is shared between the coroutines
        //self::set_static('locking_enabled_flag', FALSE);
        if (Coroutine::inCoroutine()) {
            $Context = Coroutine::getContext();
            $Context->locking_enabled_flag = FALSE;
        } else {
            self::$locking_enabled_flag = FALSE;
        }
    }

    public static function is_locking_enabled() : bool
    {
        //return self::get_static('locking_enabled_flag');
        if (Coroutine::inCoroutine()) {
            $Context = Coroutine::getContext();
            $ret = $Context->locking_enabled_flag ?? self::$locking_enabled_flag;
        } else {
            $ret = self::$locking_enabled_flag;
        }
        return $ret;
    }

    protected function load(/* mixed */ $index) : void
    {

        //_before_load() event
        if (method_exists($this, '_before_load') && !$this->method_hooks_are_disabled()) {
            $args = func_get_args();
            call_user_func_array([$this,'_before_load'], $args);//must return void
        }
        
        new Event($this, '_before_load');

        if ($this->Store->there_is_pointer_for_new_version(get_class($this), $index)) {
            $pointer =& $this->Store->get_data_pointer_for_new_version(get_class($this), $index);
            $this->record_data =& $pointer['data'];
            $this->meta_data =& $pointer['meta'];
            $this->record_modified_data =& $pointer['modified'];
        } else {
            $pointer =& $this->Store->get_data_pointer(get_class($this), $index);

            $this->record_data =& $pointer['data'];
            $this->meta_data =& $pointer['meta'];
            $this->record_modified_data = [];
        }
        if (!count($this->meta_data)) {
            throw new LogicException(sprintf(t::_('No metadata is found/loaded for object of class %s with ID %s.'), get_class($this), print_r($index, TRUE)));
        }


        //do a check is there a modified data

        if (self::is_locking_enabled()) {
            //if ($this->locking_enabled_flag) {
            $resource = MetaStore::get_key_by_object($this);
            $LR = '&';//this means that no scope reference will be used. This is because the lock will be released in another method/scope.
            self::LockManager()->acquire_lock($resource, LockInterface::READ_LOCK, $LR);
        }

        $this->is_new_flag = FALSE;

        new Event($this, '_after_load');

        //_after_load() event
        if (method_exists($this, '_after_load') && !$this->method_hooks_are_disabled()) {
            $args = func_get_args();
            call_user_func_array([$this,'_after_load'], $args);//must return void
        }
    }

    /**
     * Works only for classes that have a single primary index.
     * If the class has a compound index throws a RunTimeException.
     * @return int
     * @throws RunTimeException
     */
    public function get_index() /* scalar */
    {
        $primary_index_columns = static::get_primary_index_columns();
        if (count($primary_index_columns) > 1) {
            throw new RunTimeException(sprintf(t::_('The class %s has a compound primary index and %s can not be used on it.'), get_class($this), __METHOD__));
        }
        
        $ret = $this->record_data[$primary_index_columns[0]];
        
        return $ret;
    }

    /**
     * Returns the primary index as an associative array.
     * For all purposes in the framework this method is to be used.
     * @return array
     */
    public function get_primary_index() : array
    {
        $ret = [];
        $primary_index_columns = static::get_primary_index_columns();
        foreach ($primary_index_columns as $primary_index_column) {
            $ret[$primary_index_column] = $this->record_data[$primary_index_column];
        }
        return $ret;
    }


    /**
     * @param array $data
     * @return int|null
     */
    public static function get_index_from_data(array $data) /* mixed */
    {
        $called_class = get_called_class();
        $ret = NULL;
        $primary_columns = static::get_primary_index_columns();
        foreach ($primary_columns as $primary_column) {
            if (!array_key_exists($primary_column, $data)) {
                break;//not enough data to construct the primary index
            }
            $ret[$primary_column] = $data[$primary_column];
        }
        return $ret;
    }

    public static function get_primary_index_columns() : array
    {
        $called_class = get_called_class();
        return self::$primary_index_columns[$called_class];
    }

    public static function get_columns_data() : array
    {
        $called_class = get_called_class();
        return self::$columns_data[$called_class];
    }

    public static function uses_autoincrement() : bool
    {
        $ret = FALSE;
        foreach (static::get_columns_data() as $column_datum) {
            if ($column_datum['autoincrement'] === TRUE) {
                $ret = TRUE;
                break;
            }
        }
        return $ret;
    }

    public static function get_main_table() : string
    {
        return static::CONFIG_RUNTIME['main_table'];
    }

    public static function get_default_route() : ?string
    {
        return static::CONFIG_RUNTIME['default_route'] ?? NULL;
    }
    
    //public static function get_meta_table() : string
    //{
    //    return static::CONFIG_RUNTIME['meta_table'];
    //}

    public function save() : ActiveRecord
    {
        if (!count($this->get_modified_properties_names())) {
            return $this;
        }

        //BEGIN ORMTransaction (==ORMDBTransaction)

        //_before_save() event
        if (method_exists($this, '_before_save') && !$this->method_hooks_are_disabled()) {
            $args = func_get_args();
            call_user_func_array([$this,'_before_save'], $args);//must return void
        }

        new Event($this, '_before_save');

        if (self::is_locking_enabled()) {
            $resource = MetaStore::get_key_by_object($this);
            self::LockManager()->acquire_lock($resource, LockInterface::WRITE_LOCK, $LR);
        }

        self::OrmStore()->update_record($this);

        if (self::is_locking_enabled()) {
            self::LockManager()->release_lock('', $LR);
        }


        //TODO - it is not correct to release the lock and acquire it again - someone may obtain it in the mean time
        //instead the lock levle should be updated (lock reacquired)
        if ($this->is_new() && self::is_locking_enabled()) {
            $resource = MetaStore::get_key_by_object($this);
            $LR = '&';//this means that no scope reference will be used. This is because the lock will be released in another method/scope.
            self::LockManager()->acquire_lock($resource, LockInterface::READ_LOCK, $LR);
        }

        new Event($this, '_after_save');

        //_after_save() event
        if (method_exists($this, '_after_save') && !$this->method_hooks_are_disabled()) {
            $args = func_get_args();
            call_user_func_array([$this,'_after_save'], $args);//must return void
        }
        

        //COMMIT

        //reattach the pointer
        $pointer =& $this->Store->get_data_pointer(get_class($this), $this->get_primary_index());

        $this->record_data =& $pointer['data'];
        $this->meta_data =& $pointer['meta'];
        $this->record_modified_data = [];

        $this->is_new_flag = FALSE;

        return $this;
    }

    /**
     * Returns true is the record is just being created now and it is not yet saved
     * @return bool
     */
    public function is_new() : bool
    {
        return $this->is_new_flag;
    }

    public function is_modified() : bool
    {
        return count($this->record_modified_data) ? TRUE : FALSE;
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
     * Initializes the record_data properties with the default values (taking account nullable too).
     * To be called only from self::load()
     * @param $initial_data array
     * @author vesko@azonmedia.com
     * @created 14.09.2017
     * @since 0.7.1
     * @return void
     */
    private function initialize_record_data(array $initial_data) : void
    {
        // altough we have lazy loading we need to store in record_data whatever we obtained - this will set the index (so get_index() works)
        foreach ($initial_data as $key=>$value) {
            if (array_key_exists($key, $this->record_data)) {
                $type = $this->get_column_type($key, $nullable);
                if (!isset($type)) {
                    throw new \Guzaba2\Base\Exceptions\LogicException(sprintf(t::_('There is date for column %s from object %s but there is no such column in table %s.'), $key, get_class($this), $this->main_table));
                }

                if (in_array($key, self::$primary_index_columns)) {
                    settype($value, ($nullable && null === $value) ? 'null' : $type); //$this->_cast( ($nullable && null === $value) ? 'null' : $type , $value );
                    $this->index[$key] = $value;
                }
            } // else - ignore
        }
    }
        
    /**
     * Returns the column type as string.
     * The optional second argument $nullable will be populated with TRUE or FALSE if provided.
     * WIll return NULL if the column does not exist.
     * @see self::is_column_nullable()
     * @param string $column
     * @param bool $nullable
     * @return string|null
     * @since 0.7.1
     * @created 17.10.2017
     * @author vesko@azonmedia.com
     */
    public function get_column_type(string $column, ?bool &$nullable = null) : ?string
    {
        $type = NULL;

        static $column_type_cache = [];
        $class = get_class($this);

        if (!array_key_exists($class, $column_type_cache)) {
            $column_type_cache[$class] = [];
        }

        if (array_key_exists($column, $column_type_cache[$class])) {
            $nullable = $column_type_cache[$class][$column][1];
            return $column_type_cache[$class][$column][0];
        }
        
        $column_found = FALSE;
        foreach (static::get_columns_data() as $column_key_name => $column_data) {
            if ($column_data['name'] == $column) {
                $type = $column_data['php_type'];
                $nullable = (bool) $column_data['nullable'];
                $column_found = TRUE;
                break;
            }
        }
        
        if (!$column_found) {
            $message = sprintf(t::_('The column "%s" is not found in table "%s". Please clear the cache and try again. If the error persists it would mean wrong column name.'), $column, self::get_main_table());
            throw new RunTimeException($message);
        }

        $column_type_cache[$class][$column] = [$type, $nullable];

        return $type;
    }

    public function get_record_data() : array
    {
        return $this->record_data;
    }

    public function get_meta_data() : array
    {
        return $this->meta_data;
    }
        
    /**
     * It is invoked on @see save()
     * save() provides the record_data array as first argument
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
    public static function fix_data_arr_empty_values_type(array $data_arr) : array
    {
        //$columns_data = self::$columns_data;
        $columns_data = static::get_columns_data();
        
        foreach ($data_arr as $field_name=>$field_value) {
            if ($field_value==='') {
                // there is no value - lets see what it has to be
                // if it is an empty string '' and it is of type int it must be converted to NULL if allowed or 0 otherwise
                // look for the field
                for ($aa = 0; $aa < count($columns_data); $aa++) {
                    if ($columns_data[$aa]['name'] == $field_name) {
                        if ($columns_data[$aa]['php_type'] == 'string') {
                            // this is OK - a string can be empty
                        } elseif ($columns_data[$aa]['php_type'] == 'int' || $columns_data[$aa]['php_type'] == 'float') {
                            // check the default value - the default value may be set to NULL in the table cache but if the column is not NULL-able this means that there is no default value
                            // in this case we need to set it to 0
                            // even if the column is NULLable but threre is default value we must use the default value

                            if ($columns_data[$aa]['default_value']!==null) {
                                //we have a default value and we must use it
                                $data_arr[$field_name] = $columns_data[$aa]['default_value'];
                            } elseif ($columns_data[$aa]['nullable']) {
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
            } elseif ($field_value===NULL) {
                // we need to check does the column support this type
                // if it doesnt we need to cast it to 0 or ''
                // look for the field
                for ($aa = 0; $aa < count($columns_data); $aa++) {
                    if ($columns_data[$aa]['name'] == $field_name) {
                        if (!$columns_data[$aa]['nullable']) { // the column does not support NULL but the value is null
                            // we will need to cast it
                            if ($columns_data[$aa]['php_type'] == 'string') {
                                $data_arr[$field_name] = '';
                            } elseif ($columns_data[$aa]['php_type'] == 'int' || $columns_data[$aa]['php_type'] == 'float') {
                                $data_arr[$field_name] = 0;
                            } else {
                                // ignore for now - let it throw an error
                            }
                        }
                        break;// we found our column
                    }
                }
            }
        }
        
        return $data_arr;
    }
    
    /**
     * Returns the property names from the main table and its shards.
     * @return array
     */
    public function get_property_names() : array
    {
        $ret = [];
        foreach (static::get_columns_data() as $field_data) {
            $ret[] = $field_data['name'];
        }
        return $ret;
    }
    
    /**
     * Returns the property names of the modified properties
     * @return array Indexed array with property names
     */
    public function get_modified_properties_names() : array
    {
        //return $this->record_modified_data;
        return array_keys($this->record_modified_data);
    }

    public function is_property_modified(string $property) : bool
    {
        if (!array_key_exists($property, $this->record_data)) {
            throw new RunTimeException(sprintf(t::_('Trying to check a non existing property "%s" of instance of "%s" (ORM class).'), $property, get_class($this)));
        }
        return array_key_exists($property, $this->record_modified_data);
    }

    /**
     * Returns all old values
     * @param string $property
     * @return array
     */
    public function get_property_old_values(string $property) : array
    {
        if (!array_key_exists($property, $this->record_data)) {
            throw new RunTimeException(sprintf(t::_('Trying to get old values for a non existing property "%s" of instance of "%s" (ORM class).'), $property, get_class($this)));
        }
        return $this->record_modified_data[$property] ?? [];
    }

    /**
     * Returns
     * @param string $property
     * @throws RunTimeException
     */
    public function get_property_old_value(string $property) /* mixed */
    {
        $modified_data = $this->get_property_old_values($property);
        if (!count($modified_data)) {
            throw new RunTimeException(sprintf(t::_('The property "%s" on instnace of class "%s" (ORM class) is not modified and has no old value.'), $property, get_class($this)));
        }
        return $modified_data[ count($modified_data) - 1];
    }

    /**
     * Updates the primary index after the object is saved.
     * To be used on the records using autoincrement.
     * To be called classes implementing the StoreInterface.
     */
    public function update_primary_index(int $index) : void
    {
        // the index is autoincrement and it is not yet set
        $main_index = static::get_primary_index_columns();
//        $this->index[$main_index[0]] = $index;
        // this updated the property of the object that is the primary key
        $this->record_data[$main_index[0]] = $index;
    }

    public function debug_get_data()
    {
        return $this->Store->debug_get_data();
    }

    /**
     * Returns a routing table in a format as expected by Azonmedia\Routing\RoutingMapArray.
     * Obtains the default routes for the objects under the provided $ns_prefixes.
     * The default route for an ActiveRecord object needs to be set in the RUNTIME_CONFIG['default_route'] and must be without the UUID section.
     * @example CONFIG_DEFAULTS = ['default_route' => '/log-entry'];
     * @param array $ns_prefixes
     * @return array Twodimensional array $routing_map['route']['method'] => controller
     */
    public static function get_default_routes(array $ns_prefixes) : array
    {
        $routes = [];

        $loaded_classes = Kernel::get_loaded_classes();

        foreach ($ns_prefixes as $ns_prefix) {
            //get all activeRecord classes in the given ns prefix
            foreach ($loaded_classes as $loaded_class) {
                if (is_a($loaded_class, ActiveRecord::class, TRUE) && $loaded_class !== ActiveRecord::class && strpos($loaded_class, $ns_prefix) === 0) {
                    $default_route = $loaded_class::get_default_route();
                    if ($default_route !== NULL) {
                        $default_route_with_id = $default_route.'/{uuid}';
                        $routes[$default_route_with_id][Method::HTTP_GET] = [ActiveRecordDefaultController::class, 'get'];
                        $routes[$default_route_with_id][Method::HTTP_PUT | Method::HTTP_PATCH] = [ActiveRecordDefaultController::class, 'update'];
                        $routes[$default_route_with_id][Method::HTTP_DELETE] = [ActiveRecordDefaultController::class, 'delete'];
                        $routes[$default_route][Method::HTTP_POST] = [ActiveRecordDefaultController::class, 'create'];
                    }
                }
            }
        }

        return $routes;
    }
}
