<?php

namespace Guzaba2\Orm;

use Azonmedia\Reflection\ReflectionClass;

use Guzaba2\Kernel\Kernel;
use Guzaba2\Object\GenericObject;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
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


class ActiveRecord extends GenericObject
implements ActiveRecordInterface
{
    const PROPERTIES_TO_LINK = ['is_new_flag', 'was_new_flag', 'data'];


    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'ConnectionFactory',
            'OrmStore',
        ]
    ];

    protected const CONFIG_RUNTIME = [];

    //for the porpose of splitting and organising the methods (as this class would become too big) traits are used
    use ActiveRecordOverloading;
    use ActiveRecordSave;
    //use ActiveRecordLoad;
    use ActiveRecordStructure;

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
    protected $was_new_flag = FALSE;

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
    protected $maintain_ownership_record = true;
    
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
    protected $validation_is_disabled_flag = FALSE;

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
     * Indexed array containing the
     * @var array
     */
    protected static $primary_index_columns = [];    
    
    /**
     * @var bool
     */    
    protected $autoincrement_index = FALSE;
    
    /**
     * Contains an indexed array with the name of the primary key columns. usually it is one but can be more.
     * @var array
     */
    protected $main_index = array();

    /**
     * Contains the index provided to the get_instance()
     * @var mixed
     */
    protected $index = self::INDEX_NEW;
    
    /**
     * Each table that we have loaded the data from will be added to this array.
     * If a property is not found then we look at all tables and the check are there any tables that are not in the $data_is_loaded_from_tables and then we load all these.
     * If the table is not loaded the it will be loaded and added to this array. The array contains the names of the tables as in the database but excluding the prefix.
     * @var array
     */
    public $data_is_loaded_from_tables = [];
    
    const SAVE_MODE_ALL = 1;//overwrites all properties
    
    const SAVE_MODE_MODIFIED = 2;//saves only the modified ones
    
    const SAVE_MODE = self::SAVE_MODE_ALL;
  
    const INDEX_NEW = 0;
    
    const RETURN_INDEX_SCALAR = 1;
    
    const RETURN_INDEX_ARRAY = 2;

    
    
    public static function _initialize_class() : void
    {
        
    }
    
    //public function __construct(StoreInterface $Store)
    /**
     * ActiveRecord constructor.
     * @param $index
     * @param StoreInterface|null $Store
     * @throws \ReflectionException
     */
    public function __construct( /* mixed*/ $index = self::INDEX_NEW, ?StoreInterface $Store = NULL)
    {
        parent::__construct();

        if (!isset(static::CONFIG_RUNTIME['main_table'])) {
            throw new RunTimeException(sprintf(t::_('ActiveRecord class %s does not have "main_table" entry in its CONFIG_RUNTIME.'), get_called_class() ));
        }
        
        if ($this->index == self::INDEX_NEW) { //checks is the index already set (as it may be set in _before_construct()) - if not set it
            $this->index = $index;
        }

        if ($Store) {
            $this->Store = $Store;
        } else {
            $this->Store = static::OrmStore();//use the default service
        }
        
        /*
        if($this->maintain_ownership_record){
            if(empty(self::$meta_columns_data)){
                //TODO               
            }            
        }
        */
        
        if (empty(self::$columns_data)) {
            $unified_columns_data = $this->Store->get_unified_columns_data(get_class($this));
 
            foreach ($unified_columns_data as $column_datum) {
                self::$columns_data[$column_datum['name']] = $column_datum;
                
                if($column_datum['autoincrement'] === TRUE){
                    //may be autoincrement_index should be static prop
                    $this->autoincrement_index = TRUE;
                }
            }
        } else {
            foreach(self::$columns_data as $column_datum){
                if($column_datum['autoincrement'] === TRUE){
                    //may be autoincrement_index should be static prop
                    $this->autoincrement_index = TRUE;
                }
            }
        }

        if (empty(self::$primary_index_columns)) {
            foreach (self::$columns_data as $column_name=>$column_data) {
                if (!empty($column_data['primary'])) {
                    self::$primary_index_columns[] = $column_name;
                }
            }
        }
        
        $this->main_index = self::$primary_index_columns;

        // 1. check is there main index loaded
        // if $this->index is still empty this means that this table has no primary index
        if (!count($this->main_index)) {
            throw new \Guzaba2\Kernel\Exceptions\ConfigurationException(sprintf(t::_('The table "%s" has no primary index defined.'),$this->db->tprefix.$this->get_main_table()));
        }

        // if the primary index is compound and the provided $index is a scalar throw an error - this could be a mistake by the developer not knowing that the primary index is compound and providing only one component
        // providing only one component for the primary index is still supported but needs to be provided as array
        if (count($this->main_index) > 1) {
            if (is_scalar($this->index) && $this->index != self::INDEX_NEW) {
                $message = sprintf(t::_(' The class "%s" with primary table "%s" has a compound primary index consisting of "%s". Only a single scalar value "%s" was provided to the constructor which could be an error. For classes that use compound primary indexes please always provide arrays. If needed it is allowed the provided array to have less keys than components of the primary key.'), get_class($this), $this->get_main_table(), implode(', ', $this->main_index), $this->index);
                throw new \Guzaba2\Base\Exceptions\InvalidArgumentException($message);
            }
        }
        
        if (is_scalar($this->index)) {
            if (ctype_digit($this->index)) {
                $this->index = (int) $this->index;
            }
            $this->index = array($this->main_index[0] => $this->index);
        } elseif (is_array($this->index)) {
            // no check for count($this->index)==count($this->main_index) as an array with some criteria may be supplied instead of index
            // no change
        } else {
            throw new \Guzaba2\Base\Exceptions\runTimeException(sprintf(t::_('An unsupported type "%s" was supplied for the index of object of class "%s".'),gettype($index),get_class($this)));
        }

        if ($index) {
            $pointer =& $this->Store->get_data_pointer(get_class($this), $this->index);

            // reset the index
            $this->index = array(); 
            $this->record_data =& $pointer['data'];
            $this->meta_data =& $pointer['meta'];
            $this->initialize_record_data($pointer['data']);
            $this->is_new_flag = FALSE;
            
            if ($this->maintain_ownership_record) {
                $this->load_ownership();
            }
        } else {
            $this->record_data = $this->Store::get_record_structure(self::$columns_data);    
        }


        //all properties defined in this class must be references to the store in MemoryCache
        //if new properties are defined these will be contained in this instance, instead of being referenced in the Store
        //the Store contains only the ORM properties
//        $RClass = new ReflectionClass($this);
//        $properties = $RClass->getOwnDynamicProperties();
//        foreach ($properties as $RProperty) {
//            if (array_key_exists($RProperty->name, $pointer)) {
//                $this->{$RProperty->name} =& $pointer[$RProperty->name];
//            }
//        }

        //do not link these - these will stay separate for each instance
//        foreach (self::PROPERTIES_TO_LINK as $property_name) {
//            if (array_key_exists($property_name, $pointer)) {
//                $this->{$property_name} =& $pointer[$property_name];
//            }
//        }


    }

    /**
     * Returns the primary index for the object.
     * Returns an array if the primary index is from multiple columns.
     */
    public function get_index() /* mixed */
    {
        $primary_index_columns = self::$primary_index_columns;
        if (count($primary_index_columns) === 1) {
            $ret = $this->record_data[$primary_index_columns[0]];
        } else {
            foreach($primary_index_columns as $primary_index_column) {
                $ret[] = $this->record_data[$primary_index_column];
            }
        }
        return $ret;
    }

    public static function get_primary_index_columns() : array
    {
        return self::$primary_index_columns;
    }

    public static function get_main_table() : string
    {
        return static::CONFIG_RUNTIME['main_table'];
    }
    
    public static function get_meta_table() : string
    {
        return static::CONFIG_RUNTIME['meta_table'];
    }    
    
    public function save() : ActiveRecord
    {        
        if (method_exists($this,'_before_save') /*&& !$this->check_for_method_recursion('save') && !$this->method_hooks_are_disabled() */ ) {
            $args = func_get_args();
            $ret = call_user_func_array(array($this,'_before_save'),$args);
        }

        // basic checks
        if (!$this->is_new() && !$this->index[$this->main_index[0]]) {
            throw new \Guzaba2\Base\Exceptions\runTimeException(sprintf(t::_('Trying to save an object/record from %s class that is not new and has no primary index.'),get_class($this)));
        }

        // saving data
        // funny thing here - if there is another save() in the _after_save and this occurs on a new object it will try to create the record twice and trhow duplicateKeyException
        // the issue here is that the is_new_flag is set to FALSE only after _after_save() and this is the expected behaviour
        // so we must check are we in save() method and we must do this check BEFORE we have pushed to the calls_stack because otherwise we will always be in save()
        if ($this->is_new() /*&& !$already_in_save */) {
            // on the very first partition (which is the actual main_table) we need to make sure we have the primary index set
            $record_data_to_save = [];//needs to contain only fields from this partition
            foreach (self::$columns_data as $field_data) {
                $record_data_to_save[$field_data['name']] = $this->record_data[$field_data['name']];
            }
                       
            if (!$this->index[$this->main_index[0]]) {
                
                if (!$this->autoincrement_index) {
                    //TODO IVO
                    $this->index[$this->main_index[0]] = $this->db->get_new_id($partition_name,$this->main_index[0]);
                    $field_names_arr = array_unique(array_merge($partition_fields,$this->main_index));
                    $field_names_str = implode(', ',$field_names_arr);
                    $placeholder_str = implode(', ',array_map($prepare_binding_holders_function, $field_names_arr));
                    $data_arr = array_merge($record_data_to_save,$this->index);
                } else {
                    $field_names_arr = $this->get_field_names();//this includes the full index
                    // the first column has to be excluded
                    $pos = array_search($this->main_index[0],$field_names_arr);
                    if ($pos===false) {
                        throw new \Guzaba2\Base\Exceptions\runTimeException(sprintf(t::_('The first column of the primary index is not found within the field names returned by get_field_names() for object of class %s.'),get_class($this)));
                    }
                    unset($field_names_arr[$pos]);
                    // it needs to be removed from the data array too
                    // this won't work - peter - added if array_key_exists
                    // $pos = array_search($this->main_index[0],$record_data_to_save);
                    // unset($record_data_to_save[$pos]);
                    if (array_key_exists($this->main_index[0], $record_data_to_save)) {
                        unset($record_data_to_save[$this->main_index[0]]);
                    }

                    $field_names_str = implode(', ',$field_names_arr);
                    $placeholder_str = implode(', ',array_map(function($value) { return ':'.$value; } ,$field_names_arr));
                    $index = $this->index;
                    array_shift($index);
                    $data_arr = array_merge($record_data_to_save,$index);
                }
            } else {
                // the first column of the main index is set (as well probably the ither is there are more) and then it doesnt matter is it autoincrement or not
                $field_names_arr = array_unique(array_merge($this->get_field_names(),$this->main_index));
                $field_names_str = implode(', ',$field_names_arr);
                $placeholder_str = implode(', ',array_map(function($value) { return ':'.$value; } ,$field_names_arr));
                $data_arr = array_merge($record_data_to_save,$this->index);
            }
            
            $Connection = self::ConnectionFactory()->get_connection(\Azonmedia\Glog\Application\MysqlConnection::class, $CR);

            $data_arr = $this->fix_data_arr_empty_values_type($data_arr, $Connection::get_tprefix().$this::get_main_table());
                        
$q = "
INSERT
INTO
    {$Connection::get_tprefix()}{$this::get_main_table()}
(
    {$field_names_str}
)
VALUES
(
    {$placeholder_str}
)
                ";


                try { 
                    $Statement = $Connection->prepare($q);
                    $Statement->execute($data_arr);   
                } catch (\Guzaba2\Database\Exceptions\DuplicateKeyException $exception) {
                    throw new \Guzaba2\Database\Exceptions\DuplicateKeyException($exception->getMessage(), 0, $exception);
                } catch (\Guzaba2\Database\Exceptions\ForeignKeyConstraintException $exception) {
                    throw new \Guzaba2\Database\Exceptions\ForeignKeyConstraintException($exception->getMessage(), 0, $exception);
                }
                
                if ($this->autoincrement_index && !$this->index[$this->main_index[0]]) {
                    // the index is autoincrement and it is not yet set
                    $last_insert_id = $Connection->get_last_insert_id();
                    $this->index[$this->main_index[0]] = $last_insert_id;
                    // this updated the property of the object that is the primary key
                    $this->record_data[$this->main_index[0]] = $last_insert_id;
                }               
        } else {
            $record_data_to_save = [];
            $field_names = $modified_field_names = $this->get_field_names();
            
            if (self::SAVE_MODE == self::SAVE_MODE_MODIFIED) {
                $modified_field_names = $this->get_modified_field_names();
            }

            foreach ($modified_field_names as $field_name) {
                // $record_data_to_save[$field_name] = $this->record_data[$field_name];
                // we need to save only the fields that are part of the current shard
                if (in_array($field_name, $field_names)) {
                    $record_data_to_save[$field_name] = $this->record_data[$field_name];
                }
            }
                       
            if (count($record_data_to_save)) { //certain partitions may have nothing to save
                // set_str is used only if it is UPDATE
                // for REPLACE we need
                $columns_str = implode(', ', array_keys($record_data_to_save) );

                // $where_str = implode(PHP_EOL.'AND ',array_map(function($value){return "{$value} = :{$value}";},$this->main_index));
                // the params must not repeat
                // $where_str = implode(PHP_EOL.'AND ',array_map(function($value){return "{$value} = :where_{$value}";},$this->main_index));
                // the above is for UPDATE
                // when using REPLACE we need
                $values_str = implode(', ',array_map(function($value){return ":insert_{$value}";}, array_keys($record_data_to_save )) );

                if (self::SAVE_MODE == self::SAVE_MODE_MODIFIED) {
                    $data_arr = $record_data_to_save;
                } else {
                    $data_arr = array_merge($record_data_to_save,$this->index);
                }
                // in the update str we need to exclude the index
                $upd_arr = $record_data_to_save;

                foreach ($this->main_index as $index_column_name) {
                    unset($upd_arr[$index_column_name]);
                }
                $update_str = implode(', ',array_map(function($value){return "{$value} = :update_{$value}";}, array_keys($upd_arr) ));
                
                $Connection = self::ConnectionFactory()->get_connection(\Azonmedia\Glog\Application\MysqlConnection::class, $CR);

                $data_arr = $this->fix_data_arr_empty_values_type($data_arr, $Connection::get_tprefix().$this::get_main_table());

                foreach ($data_arr as $key=>$value) {
                    $data_arr['insert_'.$key] = $value;
                    if (!in_array($key, array_values($this->main_index))) {
                        $data_arr['update_'.$key] = $value;
                    }
                    unset($data_arr[$key]);
                }

                // suing REPLACE does not work because of the foreign keys
                // so UPDATE
                $q = "
INSERT INTO
{$Connection::get_tprefix()}{$this::get_main_table()}
({$columns_str})
VALUES
({$values_str})
ON DUPLICATE KEY UPDATE
{$update_str}
                ";



                try {
                    $Statement = $Connection->prepare($q);
                    $Statement->execute($data_arr);   
                } catch (\Guzaba2\Database\Exceptions\DuplicateKeyException $exception) {
                    throw new \Guzaba2\Database\Exceptions\DuplicateKeyException($exception->getMessage(), 0, $exception);
                } catch (\Guzaba2\Database\Exceptions\ForeignKeyConstraintException $exception) {
                    throw new \Guzaba2\Database\Exceptions\ForeignKeyConstraintException($exception->getMessage(), 0, $exception);
                }
            }     
        }
        
        if ($this->is_new() /*&& !$already_in_save */) {
            if ($this->maintain_ownership_record) {
                $this->initialize_object();
            }    
        } else {
            // update the object_last_change_time and object_last_change_role_id
            // but this should be done only for non versioned objects as the versioned in fact always are initialized
            // the versioned objects are always new so they will not get here
            if ($this->maintain_ownership_record) {  
                $this->update_ownership();
            }
        }
        
        // after the object is saved
        // the ownership reload must happen before permissions reload
        if ($this->maintain_ownership_record) {
            $this->load_ownership();
        }
                        
        if (method_exists($this,'_after_save') /*&& !$this->check_for_method_recursion('save') && !$this->method_hooks_are_disabled() */ ) { //we check for recursion against the parent method save()
            $args = func_get_args();
            $ret = call_user_func_array(array($this,'_after_save'),$args);  
        }
       
        return $this;
    }

    /**
     * Returns true is the record is just being created now and it is not yet saved
     * @return bool
     */
    public function is_new() {
        return $this->is_new_flag;
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
            if (array_key_exists($key,$this->record_data)) {
                $type = $this->get_column_type($key, $nullable);
                if (!isset($type)) {
                    throw new \Guzaba2\Base\Exceptions\LogicException(sprintf(t::_('There is date for column %s from object %s but there is no such column in table %s.'),$key,$this->get_internal_name(),$this->main_table));
                }

                if (in_array($key,$this->main_index)) {
                    settype($value,($nullable && null === $value) ? 'null' : $type); //$this->_cast( ($nullable && null === $value) ? 'null' : $type , $value );
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
        foreach (self::$columns_data as $column_key_name => $column_data) {
            if($column_data['name'] == $column){
                $type = $column_data['php_type'];
                $nullable = (bool) $column_data['nullable'];
                $column_found = TRUE;
                break;
            }            
        }
        
        if(!$column_found){
            $message = sprintf(t::_('The column "%s" is not found in table "%s". Please clear the cache and try again. If the error persists it would mean wrong column name.'), $column, self::get_main_table());
            throw new RunTimeException($message);
        }

        $column_type_cache[$class][$column] = [$type, $nullable];

        return $type;
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
     * @param string $table_name
     * @return array The provided array after the processing
     */
    protected function fix_data_arr_empty_values_type(array $data_arr, string $table_name) : array
    {
        $columns_data = self::$columns_data;
        
        foreach ($data_arr as $field_name=>$field_value) {
            if ($field_value==='') {
                // there is no value - lets see what it has to be
                // if it is an empty string '' and it is of type int it must be converted to NULL if allowed or 0 otherwise
                // look for the field
                for ($aa = 0; $aa < count ($columns_data); $aa++) {
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
                for ($aa = 0; $aa < count ($columns_data); $aa++) {
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
        $ret = array();
        foreach (self::$columns_data as $field_data) {
            $ret[] = $field_data['name'];
        }        
        return $ret;
    }
    
    /**
     * Returns the field names of the modified properties
     * @return array
     */
    public function get_modified_field_names() {
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
    public final function load_ownership(array $row=array() ) : bool
    {
        $table = self::get_meta_table();

        if (!$row) {
            $row = $this->get_object_ownership();
            if (count($row)) {
                $this->meta_data = $row;
            } else {
                throw new \Guzaba2\Base\Exceptions\LogicException(sprintf(t::_('The object of class "%s" with index "%s" was loaded but it is missing its ownership record. This type of object is supposed to have an ownership record. This error may be due to missing record in object_owners table or due to missing role pointed by object_create_role_id and object_last_change_role_id fields.<br />If an object uses permisisons or ownership you should not be manually entering records in the database.'),get_class($this),print_r($this->get_index(self::RETURN_INDEX_ARRAY),true)));//NOVERIFY
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
        
        $params = array('class_name'=>get_class($this),'object_id'=>$this->get_index());
        
        $Statement = $Connection->prepare($q);
        return $Statement->execute($params)->fetchRow();
    }
        
    /**
     * Creates permissions & ownership records for a new object
     * @param object $object
     * @param role $role
     */
    public function initialize_object() : void
    {
        if ($this->uses_ownership()) {
            $this->set_ownership();
        }    
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
            throw new RunTimeException(sprintf(t::_('Trying to set the initial ownership on object of class "%s" with index "%s". The set_ownership() method can not be used on objects that are not new.'),get_class($object),print_r($object->get_index($object::RETURN_INDEX_ARRAY),true)));//NOVERIFY
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

        $params = array('class_name'=>get_class($this),
                        'object_id'=>$this->get_index(),
                        'object_create_microtime'=> $object_create_microtime,
                        'object_last_update_microtime' => $object_last_update_microtime
                  );
        
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

        $params = array('class_name'=>get_class($this),'object_id'=>$this->get_index());
        
        $Statement = $Connection->prepare($q);
       
        return (int) $Statement->execute($params)->fetchRow('object_create_microtime'); 
    }
        
    /**
     * Returns current microtime.
     * 
     * @return int microtime
     */
    public static function get_current_microtime() : int
    {
        $micro = microtime();
        list($usec, $sec) = explode(' ', $micro);
        
        // Note: Using a string here to prevent loss of precision
        // in case of "overflow" (PHP converts it to a double)
        return (int) sprintf('%d%03d', $sec, $usec * 1000);    
    }
      
    public function update_ownership() : void
    {
        // it can happen to call update_ownership on a record that is new but this can happen if there is save() recursion
        if ($this->is_new() /* &&  !$object->is_in_method_twice('save') */ ) {
            throw new RunTimeException(sprintf(t::_('Trying to update the ownership record of a new object of class "%s" with index "%s". Instead the new obejcts should be initialized using the manager::initialize_object() method.'),get_class($object),$object->get_index()));
        }
        $Connection = self::ConnectionFactory()->get_connection(\Azonmedia\Glog\Application\MysqlConnection::class, $CR);
        $meta_table = self::get_meta_table();
        
        $object_last_update_microtime = self::get_current_microtime();

        $q = "
UPDATE
    {$Connection::get_tprefix()}{$meta_table} 
SET
    object_last_update_microtime = :object_last_update_microtime
WHERE
    class_name = :class_name
    AND object_id = :object_id
        ";
        
        $params = array('object_last_update_microtime' => $object_last_update_microtime,
                        'class_name'=>get_class($this),
                        'object_id'=>$this->get_index()
                        
        );
        
        $Statement = $Connection->prepare($q);
        $Statement->execute($params); 
    }
}