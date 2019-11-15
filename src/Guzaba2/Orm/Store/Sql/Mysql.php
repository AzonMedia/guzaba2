<?php
declare(strict_types=1);

namespace Guzaba2\Orm\Store\Sql;

use Azonmedia\Utilities\ArrayUtil;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Database\Sql\Statement;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Store\Database;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Orm\Store\Interfaces\StructuredStoreInterface;
use Guzaba2\Orm\Store\NullStore;
use Guzaba2\Kernel\Kernel as Kernel;

use Guzaba2\Database\Sql\Mysql\Mysql as MysqlDB;
use Guzaba2\Resources\ScopeReference;
use Guzaba2\Translator\Translator as t;
use Ramsey\Uuid\Uuid;


class Mysql extends Database implements StructuredStoreInterface
{
    protected const CONFIG_DEFAULTS = [
        'meta_table'    => 'object_meta'
    ];

    protected const CONFIG_RUNTIME = [];

    const SAVE_MODE_ALL = 1;//overwrites all properties

    const SAVE_MODE_MODIFIED = 2;//saves only the modified ones

    const SAVE_MODE = self::SAVE_MODE_ALL;

    protected string $connection_class = '';

    protected string $no_coroutine_connection_class = '';

    protected bool $meta_exists = false;

    /**
     *  Cached columns data
     * @var array
     */
    protected array $unified_columns_data = [];

    /**
     * Cached native columns data
     * @var array
     */
    protected array $storage_columns_data = [];



    public function __construct(StoreInterface $FallbackStore, string $connection_class, string $no_coroutine_connection_class = '')
    {
        parent::__construct();
        if (!$connection_class) {
            throw new InvalidArgumentException(sprintf(t::_('The Store %s needs $connection_class argument provided.'), get_class($this) ));
        }
        $this->FallbackStore = $FallbackStore ?? new NullStore();
        $this->connection_class = $connection_class;
        $this->no_coroutine_connection_class = $no_coroutine_connection_class;
        //$this->create_meta_if_does_not_exist();//no need - other Store will be provided - MysqlCreate
    }

    protected function get_connection(?ScopeReference &$ScopeReference) : ConnectionInterface
    {

        if (Coroutine::inCoroutine()) {
            $connection_class = $this->connection_class;
        } else {
            if (!$this->no_coroutine_connection_class) {
                throw new RunTimeException(sprintf(t::_('The Store %s is used outside coroutine context but there is no $no_coroutine_connection_class configured/provided to the constructor.'), get_class($this) ));
            }
            $connection_class = $this->no_coroutine_connection_class;
        }

        return static::get_service('ConnectionFactory')->get_connection($connection_class, $ScopeReference);
    }

    /**
     * Returns a unified structure
     * @param string $class
     * @return array
     */
    public function get_unified_columns_data(string $class) : array
    {
        if (!isset($this->unified_columns_data[$class])) {
            // TODO check deeper for a structured store
            if ($this->FallbackStore instanceof StructuredStoreInterface) {
                $this->unified_columns_data[$class] = $this->FallbackStore->get_unified_columns_data($class);
            } else {
                $storage_structure_arr = $this->get_storage_columns_data($class);
                $this->unified_columns_data[$class] = $this->unify_columns_data($storage_structure_arr);
            }
        }
        if (empty($this->unified_columns_data[$class])) {
            throw new RunTimeException(sprintf(t::_('No columns information was obtained for class %s.'), $class));
        }

        return $this->unified_columns_data[$class];
    }

    /**
     * Returns the backend storage structure
     * @param string $class
     * @return array
     */
    public function get_storage_columns_data(string $class) : array
    {

        if (!isset($this->storage_columns_data[$class])) {
            // TODO check deeper for a structured store
            if ($this->FallbackStore instanceof StructuredStoreInterface) {
                $this->storage_columns_data[$class] = $this->FallbackStore->get_unified_columns_data($class);
            } else {
                $this->storage_columns_data[$class] = $this->get_storage_columns_data_by_table_name($class::get_main_table());
            }
        }
        if (empty($this->storage_columns_data[$class])) {
            throw new RunTimeException(sprintf(t::_('No columns information was obtained for class %s.'), $class));
        }

        return $this->storage_columns_data[$class];
    }
    
    /**
     * Returns a unified structure
     * @param string $class
     * @return array
     */
    protected final function get_unified_columns_data_by_table_name(string $table_name) : array
    {
        $storage_structure_arr = $this->get_storage_columns_data_by_table_name($table_name);

        return $this->unify_columns_data($storage_structure_arr);
    }
    
    /**
    * Returns the backend storage structure.
    * @param string $table_name
    * @return array
    */
    protected final function get_storage_columns_data_by_table_name(string $table_name) : array
    {

        $Connection = $this->get_connection($CR);
        $q = "
SELECT
    information_schema.columns.*
FROM
    information_schema.columns
WHERE
    table_schema = :table_schema
    AND table_name = :table_name
ORDER BY
    ordinal_position ASC
    ";
        $s = $Connection->prepare($q);
        $s->table_schema = $Connection::get_database();
        $s->table_name = $Connection::get_tprefix().$table_name;
        $ret = $s->execute()->fetchAll();

        return $ret;
    }

    
    /**
     *
     * @param array $storage_structure_arr
     * @return array
     */
    public function unify_columns_data(array $storage_structure_arr) : array
    {
        $ret = [];
        for ($aa=0; $aa<count($storage_structure_arr); $aa++) {
            $column_structure_arr = $storage_structure_arr[$aa];
            $ret[$aa] = [
                'name'                  => strtolower($column_structure_arr['COLUMN_NAME']),
                'native_type'           => $column_structure_arr['DATA_TYPE'],
                'php_type'              => MysqlDB::TYPES_MAP[$column_structure_arr['DATA_TYPE']],
                'size'                  => MysqlDB::get_column_size($column_structure_arr),
                'nullable'              => $column_structure_arr['IS_NULLABLE'] === 'YES',
                'column_id'             => (int) $column_structure_arr['ORDINAL_POSITION'],
                'primary'               => $column_structure_arr['COLUMN_KEY'] === 'PRI',
                'default_value'         => $column_structure_arr['COLUMN_DEFAULT'] === 'NULL' ? NULL : $column_structure_arr['COLUMN_DEFAULT'],
                'autoincrement'         => $column_structure_arr['EXTRA'] === 'auto_increment',
            ];
            settype($ret[$aa]['default_value'], $ret[$aa]['php_type']);
            
            ArrayUtil::validate_array($ret[$aa], StoreInterface::UNIFIED_COLUMNS_STRUCTURE, $errors);
            if ($errors) {
                throw new RunTimeException(sprintf(t::_('The provide $storage_structure_arr to method %s does not conform to %s::UNIFIED_COLUMNS_STRUCTURE.'), __METHOD__, StoreInterface::class));
            }
        }
        
        return $ret;
    }

    public static function get_meta_table() : string
    {
        return self::CONFIG_RUNTIME['meta_table'];
    }

    public function get_meta(string $class_name, /* scalar */ $object_id) : array
    {
        $Connection = $this->get_connection($CR);
        $q = "
SELECT
    *
FROM
    {$Connection::get_tprefix()}{$this::get_meta_table()}
WHERE
    class_name = :class_name
    AND object_id = :object_id
        ";
        $data = $Connection->prepare($q)->execute(['class_name' => $class_name, 'object_id' => $object_id])->fetchRow();
        unset($data['object_uuid_binary']);//this is only needed internally for MySQL - this MUST stay removed!
        return $data;
    }

    /**
     * Returns class and id of object by uuid
     * @param  string $uuid
     * @return array - class and id
     */
    public function get_meta_by_uuid(string $uuid) : array
    {
        $Connection = $this->get_connection($CR);

        $q = "
SELECT 
    *
FROM
    {$Connection::get_tprefix()}{$this::get_meta_table()}
WHERE
    object_uuid_binary = UUID_TO_BIN(:object_uuid)";

        $data = $Connection->prepare($q)->execute([ 'object_uuid' => $uuid])->fetchRow();

        if (!count($data)) {
            throw new RunTimeException(sprintf(t::_('No meta data is found for object with UUID %s.'), $uuid));
        }
        $ret['object_id'] = $data['object_id'];
        $ret['class'] = $data['class_name'];

        return $ret;
    }

    protected function update_meta(ActiveRecordInterface $ActiveRecord) : void
    {
        // it can happen to call update_ownership on a record that is new but this can happen if there is save() recursion
        if ($ActiveRecord->is_new() /* &&  !$object->is_in_method_twice('save') */) {
            throw new RunTimeException(sprintf(t::_('Trying to update the meta data of a new object of class "%s". Instead the new obejcts have their metadata created with Mysql::create_meta() method.'), get_class($ActiveRecord)));
        }
        $Connection = $this->get_connection($CR);
        $meta_table = self::get_meta_table();

        $object_last_update_microtime = microtime(TRUE) * 1_000_000;


        $q = "
UPDATE
    {$Connection::get_tprefix()}{$meta_table} 
SET
    object_last_update_microtime = :object_last_update_microtime
WHERE
    class_name = :class_name
    AND object_id = :object_id
        ";

        $params = [
            //'class_name'                    => str_replace('\\','\\\\',get_class($ActiveRecord)),
            'class_name'                    => get_class($ActiveRecord),
            'object_id'                     => $ActiveRecord->get_id(),
            'object_last_update_microtime'  => $object_last_update_microtime,
        ];

        $Statement = $Connection->prepare($q);
        $Statement->execute($params);
    }

    protected function create_meta(ActiveRecordInterface $ActiveRecord) : string
    {
        $Connection = $this->get_connection($CR);
        $meta_table = self::get_meta_table();

        $object_create_microtime = microtime(TRUE) * 1_000_000;

        $uuid = Uuid::uuid4();
        $uuid_binary = $uuid->getBytes(); 
            
        $q = "
INSERT
INTO
    {$Connection::get_tprefix()}{$meta_table}
    (object_uuid_binary, class_name, object_id, object_create_microtime, object_last_update_microtime)
VALUES
    (:object_uuid_binary, :class_name, :object_id, :object_create_microtime, :object_last_update_microtime)
        ";

        $params = [
            'class_name'                    => get_class($ActiveRecord),
            'object_id'                     => $ActiveRecord->get_id(),
            'object_create_microtime'       => $object_create_microtime,
            'object_last_update_microtime'  => $object_create_microtime,
            'object_uuid_binary'            => $uuid_binary,
            //'object_uuid'                   => $uuid,
        ];

        $Statement = $Connection->prepare($q);
        $Statement->execute($params);

        return (string) $uuid;
    }

    /**
     * @param ActiveRecordInterface $ActiveRecord
     * @return string UUID
     * @throws RunTimeException
     * @throws \Guzaba2\Database\Exceptions\DuplicateKeyException
     * @throws \Guzaba2\Database\Exceptions\ForeignKeyConstraintException
     */
    public function update_record(ActiveRecordInterface $ActiveRecord) : array
    {

        //BEGIN DB TRANSACTION


//        if (method_exists($ActiveRecord, '_before_save') /*&& !$this->check_for_method_recursion('save') && !$this->method_hooks_are_disabled() */) {
//            $args = func_get_args();
//            $ret = call_user_func_array([$ActiveRecord,'_before_save'], $args);
//        }

        // basic checks
        //if (!$ActiveRecord->is_new() && !$ActiveRecord->index[$main_index[0]]) {
        //    throw new \Guzaba2\Base\Exceptions\RunTimeException(sprintf(t::_('Trying to save an object/record from %s class that is not new and has no primary index.'), get_class($this)));
        //}

        // saving data
        // funny thing here - if there is another save() in the _after_save and this occurs on a new object it will try to create the record twice and trhow duplicateKeyException
        // the issue here is that the is_new_flag is set to FALSE only after _after_save() and this is the expected behaviour
        // so we must check are we in save() method and we must do this check BEFORE we have pushed to the calls_stack because otherwise we will always be in save()
        $columns_data = $ActiveRecord::get_columns_data();
        $record_data = $ActiveRecord->get_record_data();
        $main_index = $ActiveRecord->get_primary_index_columns();
        $index = $ActiveRecord->get_id();
        if ($ActiveRecord->is_new() /*&& !$already_in_save */) {
            $record_data_to_save = [];
            foreach ($columns_data as $field_data) {
                $record_data_to_save[$field_data['name']] = $record_data[$field_data['name']];
            }

            //TO DO - find more intelligent solution
            // if (!$index[$main_index[0]]) {
            //temporary fix
            if (true) { 
            //temporary fix end
                if (!$ActiveRecord::uses_autoincrement()) {
                    //TODO IVO
                    $index[$main_index[0]] = $ActiveRecord->db->get_new_id($partition_name, $main_index[0]);
                    $field_names_arr = array_unique(array_merge($partition_fields, $main_index));
                    $field_names_str = implode(', ', $field_names_arr);
                    $placeholder_str = implode(', ', array_map($prepare_binding_holders_function, $field_names_arr));
                    $data_arr = array_merge($record_data_to_save, $ActiveRecord->index);
                } else {
                    $field_names_arr = $ActiveRecord::get_property_names();//this includes the full index

//                    if (array_key_exists($main_index[0], $record_data_to_save)) {
//                        unset($record_data_to_save[$main_index[0]]);
//                    }

                    $field_names_str = implode(', ', $field_names_arr);
                    $placeholder_str = implode(', ', array_map(function ($value) {
                        return ':'.$value;
                    }, $field_names_arr));

                    $data_arr = $record_data_to_save;
                }
            } else {
                // the first column of the main index is set (as well probably the ither is there are more) and then it doesnt matter is it autoincrement or not
                $field_names_arr = array_unique(array_merge($ActiveRecord::get_property_names(), $main_index));
                $field_names_str = implode(', ', $field_names_arr);
                $placeholder_str = implode(', ', array_map(function ($value) {
                    return ':'.$value;
                }, $field_names_arr));
                $data_arr = array_merge($record_data_to_save, $ActiveRecord->index);
            }
            $Connection = $this->get_connection($CR);

            $data_arr = $ActiveRecord::fix_data_arr_empty_values_type($data_arr, $Connection::get_tprefix().$ActiveRecord::get_main_table());


            $q = "
INSERT
INTO
    {$Connection::get_tprefix()}{$ActiveRecord::get_main_table()}
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

            //if ($ActiveRecord::uses_autoincrement() && !$ActiveRecord->index[$main_index[0]]) {
            if ($ActiveRecord::uses_autoincrement() && !$ActiveRecord->get_id()) {
                // the index is autoincrement and it is not yet set
                $last_insert_id = $Connection->get_last_insert_id();
//                $ActiveRecord->index[$main_index[0]] = $last_insert_id;
//                // this updated the property of the object that is the primary key
//                $ActiveRecord->record_data[$main_index[0]] = $last_insert_id;
                // we need this part of code. It will set $ActiveRecord->record_data[$main_index[0]]
                $ActiveRecord->update_primary_index($last_insert_id);

                //we need to update the record data as it is being returned by the update method (for the previous stored to use)
                $main_index = $ActiveRecord::get_primary_index_columns();
                $record_data[$main_index[0]] = $last_insert_id;
            }
        } else {
            $record_data_to_save = [];
            $field_names = $modified_field_names = $ActiveRecord::get_property_names();

//            if (self::SAVE_MODE == self::SAVE_MODE_MODIFIED) {
//                $modified_field_names = $ActiveRecord->get_modified_field_names();
//            }

            $record_data = $ActiveRecord->get_record_data();
            foreach ($modified_field_names as $field_name) {
                // $record_data_to_save[$field_name] = $ActiveRecord->record_data[$field_name];
                // we need to save only the fields that are part of the current shard
                if (in_array($field_name, $field_names)) {
                    $record_data_to_save[$field_name] = $record_data[$field_name];
                }
            }

            if (count($record_data_to_save)) { //certain partitions may have nothing to save
                // set_str is used only if it is UPDATE
                // for REPLACE we need
                $columns_str = implode(', ', array_keys($record_data_to_save));

                // $where_str = implode(PHP_EOL.'AND ',array_map(function($value){return "{$value} = :{$value}";},$main_index));
                // the params must not repeat
                // $where_str = implode(PHP_EOL.'AND ',array_map(function($value){return "{$value} = :where_{$value}";},$main_index));
                // the above is for UPDATE
                // when using REPLACE we need
                $values_str = implode(', ', array_map(function ($value) {
                    return ":insert_{$value}";
                }, array_keys($record_data_to_save)));

                if (self::SAVE_MODE == self::SAVE_MODE_MODIFIED) {
                    $data_arr = $record_data_to_save;
                } else {
                    //$data_arr = array_merge($record_data_to_save, $ActiveRecord->index);//not needed
                    $data_arr = $record_data_to_save;
                }
                // in the update str we need to exclude the index
                $upd_arr = $record_data_to_save;

                foreach ($main_index as $index_column_name) {
                    unset($upd_arr[$index_column_name]);
                }
                $update_str = implode(', ', array_map(function ($value) {
                    return "{$value} = :update_{$value}";
                }, array_keys($upd_arr)));

                $Connection = $this->get_connection($CR);

                $data_arr = $ActiveRecord->fix_data_arr_empty_values_type($data_arr, $Connection::get_tprefix().$ActiveRecord::get_main_table());

                foreach ($data_arr as $key=>$value) {
                    $data_arr['insert_'.$key] = $value;
                    if (!in_array($key, array_values($main_index))) {
                        $data_arr['update_'.$key] = $value;
                    }
                    unset($data_arr[$key]);
                }

                // using REPLACE does not work because of the foreign keys
                // so UPDATE
                $q = "
INSERT INTO
{$Connection::get_tprefix()}{$ActiveRecord::get_main_table()}
({$columns_str})
VALUES
({$values_str})
ON DUPLICATE KEY UPDATE
{$update_str}
                ";

                try {
                    $Statement = $Connection->prepare($q);

                    $ret = $Statement->execute($data_arr);

                    //print 'BB'.$Connection->get_affected_rows().'BB';
                } catch (\Guzaba2\Database\Exceptions\DuplicateKeyException $exception) {
                    throw new \Guzaba2\Database\Exceptions\DuplicateKeyException($exception->getMessage(), 0, $exception);
                } catch (\Guzaba2\Database\Exceptions\ForeignKeyConstraintException $exception) {
                    throw new \Guzaba2\Database\Exceptions\ForeignKeyConstraintException($exception->getMessage(), 0, $exception);
                }
            }
        }


        if ($ActiveRecord->is_new()) {
            $uuid = $this->create_meta($ActiveRecord);

        } else {
            $this->update_meta($ActiveRecord);
            $uuid = $ActiveRecord->get_uuid();
        }
        //$ret = array_merge($record_data, $this->get_meta());


        // TODO set uuid to $ActiveRecord

        //COMMIT DB TRANSACTION

        //$this->is_new = FALSE;
        //this flag will be updated in activerecord::save()
        //return $uuid;
        $ret = ['data' => $record_data, 'meta' => $this->get_meta(get_class($ActiveRecord), $ActiveRecord->get_id() )];

        return $ret;
    }

    public function &get_data_pointer(string $class, array $index) : array
    {

        $data = $this->get_data_by($class, $index);

        if (count($data)) {
            //TODO meta data object onwenrs table, i will set it manuly until save() is finished
            //$record_data['meta']['updated_microtime'] = time();
            //based on the returned data we need to determine the primary index (which needs to be a single column for the objects which support meta data)
            //$record_data['meta'] = $this->get_meta($class, );
            //TODO IVO [0]
            //$record_data['data'] = $data[0];
            $primary_index = $class::get_index_from_data($data[0]);
            if (is_null($primary_index)) {
                throw new RunTimeException(sprintf(t::_('The primary index for class %s is not found in the retreived data.'), $class));
            }
            if (count($primary_index) > 1) {
                throw new RunTimeException(sprintf(t::_('The class %s has compound index and can not have meta data.'), $class));
            }
            $ret['meta'] = $this->get_meta($class, current($primary_index));
            $ret['data'] = $data[0];
        } else {
            //TODO IVO may be should be moved in ActiveRecord
            //throw new framework\orm\exceptions\missingRecordException(sprintf(t::_('The required object of class "%s" with index "%s" does not exist.'), $class, var_export($lookup_index, true)));
            $this->throw_not_found_exception($class, self::form_lookup_index($index));
        }


        return $ret;
    }

    public function &get_data_pointer_for_new_version(string $class, array $primary_index) : array
    {
        $data = $this->get_data_pointer($class, $primary_index);
        return $data;
    }

    public function there_is_pointer_for_new_version(string $class, array $primary_index) : bool
    {
        //this store doesnt use pointers
        return FALSE;
    }

    public function free_pointer(ActiveRecordInterface $ActiveRecord) : void
    {
        //does nothing
    }

    public function debug_get_data() : array
    {
        return [];
    }

    protected function create_meta_if_does_not_exist()
    {
   return;
        if ($this->meta_exists) {
            return true;
        }

        // TODO can use create table if not exists
        $Connection = $this->get_connection($CR);
        $q = "
SELECT
    *
FROM
    information_schema.tables
WHERE
    table_schema = :table_schema
    AND table_name = :table_name
LIMIT 1
        ";
        $s = $Connection->prepare($q);
        $s->table_schema = $Connection::get_database();
        $s->table_name = $Connection::get_tprefix() . static::CONFIG_RUNTIME['meta_table'];

        $ret = $s->execute()->fetchAll();

        if (!empty($ret)) {
            $this->meta_exists = true;
            return true;
        }

        $q = "
        CREATE TABLE `{$s->table_name}` (
  `object_uuid_binary` binary(16) NOT NULL,
  `object_uuid` char(36) GENERATED ALWAYS AS (bin_to_uuid(`object_uuid_binary`)) VIRTUAL NOT NULL,
  `class_name` varchar(255) NOT NULL,
  `object_id` bigint(20) UNSIGNED NOT NULL,
  `object_create_microtime` bigint(16) UNSIGNED NOT NULL,
  `object_last_update_microtime` bigint(16) UNSIGNED NOT NULL,
  `object_create_transaction_id` bigint(20) UNSIGNED NOT NULL DEFAULT '0',
  `object_last_update_transction_id` bigint(20) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`object_uuid_binary`),
  CONSTRAINT `class_name` UNIQUE (`class_name`,`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        ";
        $s = $Connection->prepare($q);
        $s->execute();

        $this->meta_exists = true;
    }

    /**
     * Removes an active record data from the Store
     * @param ActiveRecordInterface $ActiveRecord
     */
    public function remove_record(ActiveRecordInterface $ActiveRecord): void
    {
        $Connection = $this->get_connection($CR);
        $primary_index = $ActiveRecord->get_primary_index();
        $w_arr = [];
        foreach ($primary_index as $key => $value) {
            $w_arr[] = "$key = '$value'";
        }
        $w_str = implode(' AND ', $w_arr);

        // Remove record data
        $q = "
DELETE FROM {$Connection::get_tprefix()}{$ActiveRecord::get_main_table()} 
WHERE {$w_str}
";
        $s = $Connection->prepare($q);
        $s->execute();

        // Remove meta data
        $meta_table = self::get_meta_table();
        $uuid = $ActiveRecord->get_uuid();
        $q = "
DELETE FROM {$Connection::get_tprefix()}{$meta_table} 
WHERE `object_uuid` = '{$uuid}'
";
        $s = $Connection->prepare($q);
        $s->execute();
    }

    /**
     * Returns all results matching criteria
     * @param  string $class class name
     * @param  array  $index [$column => $value]
     * @return array  dataset
     */
    public function &get_data_by(string $class, array $index) : array
    {

        //initialization
        $record_data = self::get_record_structure($this->get_unified_columns_data($class));

        //lookup in DB

        $Connection = $this->get_connection($CR);

        //pull data from DB
        //set the data to $record_data['data']
        //set the meta data to $record_data['meta'];

        $j = [];//an array containing all the tables that need to be INNER JOINED
        //needs to be associative as we may join unwillingdfully multiple times the same table
        //the key is the name as which the join will be done and the value is the actual table name
        //so the join will look like JOIN key AS value
        $w = [];//array containing the where clauses
        $b = [];//associative array with the variables that must have values bound

        //we need to always join all the main tables
        //otherwise the loaded object will be mising properties
        //but these can be loaded on request
        //so if we
        
        $table_name = $class::get_main_table();
        //the main table must be always loaded
        $j[$class::get_main_table()] = $Connection::get_tprefix().$class::get_main_table();//if it gets assigned multiple times it will overwrite it
        //as it may happen the WHERE index provided to get_instance to be from other shards
        
        //if($this->is_ownership_table($table_name)){
            
        //}
        
        
        $main_index = $class::get_primary_index_columns();
        //$index = [$main_index[0] => $index];


        /**
         * If UUID is provided the meta data is searched to find the primary key in order
         * to perform the SELECT operation
         */
        if (array_key_exists('object_uuid', $index)) {

            $meta_data = $this->get_meta_by_uuid($index['object_uuid']);
            $object_id = $meta_data['object_id'];

            $w[] = $main_index[0] . ' = :object_id';
            $b['object_id'] = $object_id;

        } else {


            foreach ($index as $field_name=>$field_value) {
                if (!is_string($field_name)) {
                    //perhaps get_instance was provided like this [1,2] instead of ['col1'=>1, 'col2'=>2]... The first notation may get supported in future by inspecting the columns and assume the order in which the primary index is provided to be correct and match it
                    throw new RunTimeException(sprintf(t::_('It seems wrong values were provided to object instance. The provided array must contain keys with the column names and values instead of just values. Please use new %s([\'col1\'=>1, \'col2\'=>2]) instead of new %s([1,2]).'), $class, $class, $class));
                }

                if ($field_name !== 'object_uuid') {
                    if (!array_key_exists($field_name, $record_data)) {
                        throw new RunTimeException(sprintf(t::_('A field named "%s" that does not exist is supplied to the constructor of an object of class "%s".'), $field_name, $class));
                    }
                }

                //TODO IVO add owners_table, meta table

                $j[$table_name] = $Connection::get_tprefix().$table_name;
                //$w[] = "{$table_name}.{$field_name} {$this->db->equals($field_value)} :{$field_name}";
                //$b[$field_name] = $field_value;
                if (is_null($field_value)) {
                    $w[] = "{$class::get_main_table()}.{$field_name} {$Connection::equals($field_value)} NULL";
                } else {
                    $w[] = "{$class::get_main_table()}.{$field_name} {$Connection::equals($field_value)} :{$field_name}";
                    $b[$field_name] = $field_value;
                }
            } //end foreach

        }
        //here we join the tables and load only the data from the joined tables
        //this means that some tables / properties will not be loaded - these will be loaded on request
        //$j_str = implode(" INNER JOIN ", $j);//cant do this way as now we use keys
        //the key is the alias of the table, the value is the real full name of the table (including the prefix)
        $j_alias_arr = [];
        foreach ($j as $table_alias=>$full_table_name) {

            //and the class_id & object_id are moved to the WHERE CLAUSE
            if ($table_alias == $table_name) {
                //do not add ON clause - this is the table containing the primary index and the first shard
                $on_str = "";
            } elseif ($table_alias == 'ownership_table') {
                $on_arr = [];

                $on_arr[] = "ownership_table.class_id = :class_id";
                $b['class_id'] = static::_class_id;

                $w[] = "ownership_table.object_id = {$table_name}.{$main_index[0]}";//the ownership table does not support compound primary index

                $on_str = "ON ".implode(" AND ", $on_arr);
            } else {
                $on_arr = [];
                foreach ($main_index as $column_name) {
                    $on_arr[] = "{$table_alias}.{$column_name} = {$table_name}.{$column_name}";
                }
                $on_str = "ON ".implode(" AND ", $on_arr);
            }
            $j_alias_arr[] = "`{$full_table_name}` AS `{$table_alias}` {$on_str}";
            //$this->data_is_loaded_from_tables[] = $table_alias;
        }

        $j_str = implode(PHP_EOL."\t"."LEFT JOIN ", $j_alias_arr);//use LEFT JOIN as old record will have no data in the new shards
        unset($j, $j_alias_arr);
        $w_str = implode(" AND ", $w);
        unset($w);
        $q = "
SELECT 
*
FROM
{$j_str}
WHERE
{$w_str}
";

        $Statement = $Connection->prepare($q);
        $Statement->execute($b);
        $data = $Statement->fetchAll();

        if (empty($data)) {
            $this->throw_not_found_exception($class, self::form_lookup_index($index));
        }
        return $data;

    }
}
