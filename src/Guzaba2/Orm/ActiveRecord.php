<?php

namespace Guzaba2\Orm;

use Azonmedia\Lock\Interfaces\LockInterface;
use Azonmedia\Reflection\ReflectionClass;

use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Object\GenericObject;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\MetaStore\MetaStore;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Orm\Store\Memory;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Translator\Translator as t;



use Guzaba2\Orm\Traits\ActiveRecordOverloading;
use Guzaba2\Orm\Traits\ActiveRecordSave;
use Guzaba2\Orm\Traits\ActiveRecordLoad;
use Guzaba2\Orm\Traits\ActiveRecordStructure;

//use Guzaba2\Orm\Traits\ActiveRecordValidation;
//use Guzaba2\Orm\Traits\ActiveRecordDynamicProperties;
//use Guzaba2\Orm\Traits\ActiveRecordDelete;


class ActiveRecord extends GenericObject implements ActiveRecordInterface
{
    const PROPERTIES_TO_LINK = ['is_new_flag', 'was_new_flag', 'data'];


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
    //use ActiveRecordSave;
    //use ActiveRecordSave;
    //use ActiveRecordLoad;
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
     * @var bool
     */
    protected $is_modified_flag = FALSE;

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
    //protected $maintain_ownership_record = true;
    
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

    
    
    public static function _initialize_class() : void
    {
    }
    
    //public function __construct(StoreInterface $Store)

    /**
     * ActiveRecord constructor.
     * @param $index
     * @param StoreInterface|null $Store
     * @throws \ReflectionException
     * @throws RunTimeException
     */
    public function __construct(/* mixed*/ $index = self::INDEX_NEW, ?StoreInterface $Store = NULL)
    {
        parent::__construct();

        if (!isset(static::CONFIG_RUNTIME['main_table'])) {
            throw new RunTimeException(sprintf(t::_('ActiveRecord class %s does not have "main_table" entry in its CONFIG_RUNTIME.'), get_called_class()));
        }
        
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
//        } else {
//            foreach (self::$columns_data as $column_datum) {
//                if ($column_datum['autoincrement'] === TRUE) {
//                    //may be autoincrement_index should be static prop
//                    self::$autoincrement_index = TRUE;
//                }
//            }
//        }

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
                $message = sprintf(t::_(' The class "%s" with primary table "%s" has a compound primary index consisting of "%s". Only a single scalar value "%s" was provided to the constructor which could be an error. For classes that use compound primary indexes please always provide arrays. If needed it is allowed the provided array to have less keys than components of the primary key.'), $called_class, static::get_main_table(), implode(', ', $primary_columns), $index);
                throw new InvalidArgumentException($message);
            }
        } elseif (is_array($this->index)) {
            // no check for count($this->index)==count(self::$primary_index_columns) as an array with some criteria may be supplied instead of index
            // no change
        } else {
            throw new \Guzaba2\Base\Exceptions\runTimeException(sprintf(t::_('An unsupported type "%s" was supplied for the index of object of class "%s".'), gettype($index), get_class($this)));
        }

        if ($index[$primary_columns[0]] === self::INDEX_NEW) {
            $this->record_data = $this->Store::get_record_structure(static::get_columns_data());
            //the new records are unhooked
            //no locking here
        } else {
            $this->load($index);
        }
    }

    public function __destruct()
    {
        $resource = MetaStore::get_key_by_object($this);
        self::LockManager()->release_lock($resource);
    }

    protected function load( /* mixed */ $index) : void
    {

        //_before_load() event
        if (method_exists($this, '_before_load') && !$this->method_hooks_are_disabled()) {
            $args = func_get_args();
            call_user_func_array(array($this,'_before_load'),$args);//must return void
        }

        $pointer =& $this->Store->get_data_pointer(get_class($this), $index);

        $this->record_data =& $pointer['data'];
        $this->meta_data =& $pointer['meta'];

        $resource = MetaStore::get_key_by_object($this);
        $LR = '&';//this means that no scope reference will be used. This is because the lock will be released in another method/scope.
        self::LockManager()->acquire_lock($resource, LockInterface::READ_LOCK, $LR);

        $this->is_new_flag = FALSE;

        //_after_load() event
        if (method_exists($this, '_after_load') && !$this->method_hooks_are_disabled()) {
            $args = func_get_args();
            call_user_func_array(array($this,'_after_load'),$args);//must return void
        }
    }

    /**
     * Works only for classes that have a single primary index.
     * If the class has a compound index throws a RunTimeException.
     * @return int
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
    
    //public static function get_meta_table() : string
    //{
    //    return static::CONFIG_RUNTIME['meta_table'];
    //}

    public function save() : ActiveRecord
    {

        //BEGIN ORMTransaction (==ORMDBTransaction)

        //_before_save() event
        if (method_exists($this, '_before_save') && !$this->method_hooks_are_disabled()) {
            $args = func_get_args();
            call_user_func_array([$this,'_before_save'], $args);//must return void
        }

        $resource = MetaStore::get_key_by_object($this);
        self::LockManager()->acquire_lock($resource, LockInterface::WRITE_LOCK, $LR);

        self::OrmStore()->update_record($this);

        self::LockManager()->release_lock('', $LR);

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
        return $this->is_modified_flag;
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
                    throw new \Guzaba2\Base\Exceptions\LogicException(sprintf(t::_('There is date for column %s from object %s but there is no such column in table %s.'), $key, $this->get_internal_name(), $this->main_table));
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
     * Returns the field names from the main table and its shards.
     * @return array
     */
    public function get_field_names() : array
    {
        $ret = [];
        foreach (static::get_columns_data() as $field_data) {
            $ret[] = $field_data['name'];
        }
        return $ret;
    }
    
    /**
     * Returns the field names of the modified properties
     * @return array
     */
    public function get_modified_field_names()
    {
        return $this->record_modified_data;
    }
    
    /**
     * Loads the ownership of the object.
     * It is public so the ownership can be reloaded from the monitor if needed.
     * Returns TRUE if the data was loaded and FALSE if it was already loaded (it wont try to load it again).
     * Once an object has its ownership data loaded it cant be loaded again.
     * @param $row - use externally provided ownership - this is used when an object is created by a record
     * @return bool
     * @throws \Guzaba2\Base\Exceptions\LogicException
     */
    final public function load_ownership(array $row=[]) : bool
    {
        $table = self::get_meta_table();

        if (!$row) {
            $row = $this->get_object_ownership();
            if (count($row)) {
                $this->meta_data = $row;
            } else {
                throw new \Guzaba2\Base\Exceptions\LogicException(sprintf(t::_('The object of class "%s" with index "%s" was loaded but it is missing its ownership record. This type of object is supposed to have an ownership record. This error may be due to missing record in object_owners table or due to missing role pointed by object_create_role_id and object_last_change_role_id fields.<br />If an object uses permisisons or ownership you should not be manually entering records in the database.'), get_class($this), print_r($this->get_index(self::RETURN_INDEX_ARRAY), true)));//NOVERIFY
            }
        } else {
            $this->meta_data = $row;
        }

        return TRUE;
    }
    
    public function get_object_ownership() : array
    {
        $Connection = self::ConnectionFactory()->get_connection(\Azonmedia\Glog\Application\MysqlConnection::class, $CR);
        $meta_table = self::get_meta_table();
        // there is always an owner (inner join) but the last change may not be set
        $q = "
SELECT
    ownership.*
FROM
    {$Connection::get_tprefix()}{$meta_table} AS ownership
WHERE
    ownership.class_name = :class_name
    AND ownership.object_id = :object_id
        ";
        
        $params = ['class_name'=>get_class($this),'object_id'=>$this->get_index()];
        
        $Statement = $Connection->prepare($q);
        return $Statement->execute($params)->fetchRow();
    }
    
    /**
     *
     * @return bool
     */
    public function uses_ownership() : bool
    {
        return $this->maintain_ownership_record;
    }
    
    /**
     * Sets the session subject as the owner of the object. If needed after saving the object it can be changed using change_ownership()
     * @param object $object
     */
    protected function set_ownership() : void
    {
        if (!$this->is_new()) {
            throw new RunTimeException(sprintf(t::_('Trying to set the initial ownership on object of class "%s" with index "%s". The set_ownership() method can not be used on objects that are not new.'), get_class($object), print_r($object->get_index($object::RETURN_INDEX_ARRAY), true)));//NOVERIFY
        }
    
        // versioned object must be handled a little differently... for versioned objects the initial object_create_time must remain the same for all versions (after it was set initially for the first)
        $object_create_microtime = $this->get_object_create_microtime();
        // if it is a nonversioned object it wont exist and a new time must be set
        if (!$object_create_microtime) {
            $object_create_microtime = self::get_current_microtime();
        }
        $object_last_update_microtime = self::get_current_microtime();

        $Connection = self::ConnectionFactory()->get_connection(\Azonmedia\Glog\Application\MysqlConnection::class, $CR);
        $meta_table = self::get_meta_table();
        
        $q = "
INSERT
INTO {$Connection::get_tprefix()}{$meta_table}
(
    class_name, object_id,  object_create_microtime, object_last_update_microtime
   
)
VALUES
(
    :class_name, :object_id, :object_create_microtime, :object_last_update_microtime
    
)
        ";

        $params = ['class_name'=>get_class($this),
                        'object_id'=>$this->get_index(),
                        'object_create_microtime'=> $object_create_microtime,
                        'object_last_update_microtime' => $object_last_update_microtime
                  ];
        
        $Statement = $Connection->prepare($q);
        $Statement->execute($params);
    }
    
    /**
     * Returns the create time of the object (no matter is it versioned or not)
     * Actually it is used only for versioned objects so the create time of the first version can be retreived (so can be set for any newversion)
     * Does not check permissions
     */
    public function get_object_create_microtime() : int
    {
        $Connection = self::ConnectionFactory()->get_connection(\Azonmedia\Glog\Application\MysqlConnection::class, $CR);
        $meta_table = self::get_meta_table();
        
        $q = "
SELECT
    p1.object_create_microtime
FROM
    {$Connection::get_tprefix()}{$meta_table} AS p1
WHERE
    p1.class_name = :class_name
    AND p1.object_id = :object_id
GROUP BY
    p1.object_id
        ";

        $params = ['class_name'=>get_class($this),'object_id'=>$this->get_index()];
        
        $Statement = $Connection->prepare($q);
       
        return (int) $Statement->execute($params)->fetchRow('object_create_microtime');
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
        $this->index[$main_index[0]] = $index;
        // this updated the property of the object that is the primary key
        $this->record_data[$main_index[0]] = $index;
    }
}
