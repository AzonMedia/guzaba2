<?php
declare(strict_types=1);

namespace Guzaba2\Orm\Store\Sql;

use Azonmedia\Utilities\ArrayUtil;
use Azonmedia\Utilities\GeneralUtil;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Database\Sql\Mysql\Connection;
use Guzaba2\Database\Sql\Statement;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Store\Database;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Orm\Store\Interfaces\StructuredStoreInterface;
use Guzaba2\Cache\Interfaces\CacheStatsInterface;
use Guzaba2\Orm\Store\NullStore;
use Guzaba2\Kernel\Kernel as Kernel;

use Guzaba2\Database\Sql\Mysql\Mysql as MysqlDB;
use Guzaba2\Resources\ScopeReference;
use Guzaba2\Translator\Translator as t;
use Ramsey\Uuid\Uuid;



class Mysql extends Database implements StructuredStoreInterface, CacheStatsInterface
{
    protected const CONFIG_DEFAULTS = [
        'meta_table'    => 'object_meta',
        'class_table'   => 'classes',
    ];

    protected const CONFIG_RUNTIME = [];

    const SAVE_MODE_ALL = 1;//overwrites all properties

    const SAVE_MODE_MODIFIED = 2;//saves only the modified ones

    const SAVE_MODE = self::SAVE_MODE_ALL;

    /**
     * @var string
     */
    protected string $connection_class = '';

    /**
     * @var string
     */
    protected string $no_coroutine_connection_class = '';

    /**
     * @var bool
     */
    protected bool $meta_exists = false;

    /**
     * Associative array with class_name=>main_table
     * It is used to check do two classes write to the same main table
     * This must NOT be allowed
     * @var array
     */
    protected array $known_classes = [];

    protected $cache_enabled;
    protected $hits;
    protected $misses;

    private array $classes_data = [];


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
        $this->hits = 0;
        $this->misses = 0;

        $this->update_classes_data();
    }

    private function load_classes_data() : void
    {
        $Connection = $this->get_connection($CR);
        $q = "
SELECT
    *
FROM
    {$Connection::get_tprefix()}{$this::get_class_table()}
ORDER BY
    class_name ASC
        ";
        $data = $Connection->prepare($q)->execute()->fetchAll();
        foreach ($data as $record) {
            $this->classes_data[$record['class_name']] = $record;
        }
    }

    private function update_classes_data() : void
    {
        $this->load_classes_data();
        $active_record_classes = ActiveRecord::get_active_record_classes(array_keys(Kernel::get_registered_autoloader_paths()));
        foreach ($active_record_classes as $class_name) {
            if (!$this->has_class_data($class_name)) {
                $this->insert_new_class($class_name);//could be converted to coroutine call but new classes are added rarely and no point optimizing this...
            }
        }
        $this->load_classes_data();//reload the data
    }

    private function insert_new_class(string $class_name) : void
    {
        $Connection = $this->get_connection($CR);
        $q = "
INSERT
INTO
    {$Connection::get_tprefix()}{$this::get_class_table()}
    (class_uuid_binary, class_name, class_table)
VALUES
    (:class_uuid_binary, :class_name, :class_table)        
        ";

        $uuid = Uuid::uuid4();
        $uuid_binary = $uuid->getBytes();

        $b = [
            'class_uuid_binary' => $uuid_binary,
            'class_name'        => $class_name,
            'class_table'       => $class_name::get_main_table(),
        ];
        $Connection->prepare($q)->execute($b);

        Kernel::log(sprintf(t::_('%1s: Detected and added a new class %2s with UUID %3s.'), __CLASS__, $class_name, $uuid->getHex()));
    }

    public function has_class_data(string $class_name) : bool
    {
        if (!$class_name) {
            throw new InvalidArgumentException(sprintf(t::_('No $class_name argument provided.')));
        }
        if (!class_exists($class_name)) {
            throw new InvalidArgumentException(sprintf(t::_('There is no class %1s.'), $class_name));
        }
        return isset($this->classes_data[$class_name]);
    }

    public function get_class_data(string $class_name) : array
    {
        if (!$this->has_class_data($class_name)) {
            throw new RunTimeException(sprintf(t::_('The Mysql store has no data for class %1s.'), $class_name));
        }
        return $this->classes_data[$class_name];
    }

    public function get_class_name(int $class_id) : ?string
    {
        $ret = NULL;
        foreach ($this->classes_data as $classes_datum) {
            if ($classes_datum['class_id'] === $class_id) {
                $ret = $classes_datum['class_name'];
                break;
            }
        }
        return $ret;
    }

    public function get_class_id(string $class_name) : ?int
    {
        return $this->has_class_data($class_name) ? $this->get_class_data($class_name)['class_id'] : NULL ;
    }

    public function get_class_uuid(string $class_name) : ?string
    {
        return $this->has_class_data($class_name) ? $this->get_class_data($class_name)['class_uuid'] : NULL ;
    }

    public function get_connection(?ScopeReference &$ScopeReference) : ConnectionInterface
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
            throw new RunTimeException(sprintf(t::_('No columns information was obtained for class %s with main table %s. Please check is the main table for the class and table prefix set correctly in the connection config.'), $class, $class::get_main_table() ));
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
    *,
    '' AS COLUMN_KEY_NAME,
    '' AS COLUMN_KEY_REFERENCE
FROM
    information_schema.COLUMNS
WHERE
    TABLE_SCHEMA = :table_schema
    AND TABLE_NAME = :table_name
ORDER BY
    ORDINAL_POSITION ASC
    ";
        $s = $Connection->prepare($q);
        $s->table_schema = $Connection::get_database();
        $s->table_name = $Connection::get_tprefix().$table_name;
        $ret = $s->execute()->fetchAll();

        if (!$ret) {
            throw new RunTimeException(sprintf(t::_('The table %s does not exist. Please check the class main_table and the connection tprefix (table prefix).'), $Connection::get_tprefix().$table_name ));
        }

        $q = "
SELECT
    *
FROM
    information_schema.KEY_COLUMN_USAGE
WHERE
    TABLE_SCHEMA = :table_schema
    AND TABLE_NAME = :table_name
    
        ";
        $s = $Connection->prepare($q);
        $s->table_schema = $Connection::get_database();
        $s->table_name = $Connection::get_tprefix().$table_name;
        //print_r($s->get_params());
        $keys_ret = $s->execute()->fetchAll();

        foreach ($keys_ret as $key_row) {
            foreach ($ret as &$row) {
                if ($row['COLUMN_NAME'] === $key_row['COLUMN_NAME']) {
                    $row['COLUMN_KEY_NAME'] = $key_row['CONSTRAINT_NAME'];
                    if ($key_row['REFERENCED_TABLE_SCHEMA']) {
                        $row['COLUMN_KEY_REFERENCE'] = $key_row['REFERENCED_TABLE_SCHEMA'].'.'.$key_row['REFERENCED_TABLE_NAME'].'.'.$key_row['REFERENCED_COLUMN_NAME'];//TODO - improve this
                    }
                }
            }
        }

        //mysql store specific implementation - it uses class_id instead of class_name
        $has_class_id_column = FALSE;
        foreach ($ret as &$row) {
            if ($row['COLUMN_NAME'] === 'class_id') {
                $has_class_id_column = TRUE;
                break;
            }
        }
        $has_class_name_column = FALSE;
        foreach ($ret as &$row) {
            if ($row['COLUMN_NAME'] === 'class_name') {
                $has_class_name_column = TRUE;
                break;
            }
        }
        if ($has_class_id_column && !$has_class_name_column) {
            $q = "
SELECT
    *,
    '' AS COLUMN_KEY_NAME,
    '' AS COLUMN_KEY_REFERENCE
FROM
    information_schema.COLUMNS
WHERE
    TABLE_SCHEMA = :table_schema
    AND TABLE_NAME = :table_name
ORDER BY
    ORDINAL_POSITION ASC
    ";
            $s = $Connection->prepare($q);
            $s->table_schema = $Connection::get_database();
            $s->table_name = $Connection::get_tprefix().'classes';
            $classes_columns = $s->execute()->fetchAll();
            $class_column = [];
            foreach ($classes_columns as $classes_column) {
                if ($classes_column['COLUMN_NAME'] === 'class_name') {
                    $class_column = $classes_column;
                    break;
                }
            }
            if ($class_column) {
                //$ret[] = $class_column;
            }

        }

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
                'key_name'              => $column_structure_arr['COLUMN_KEY_NAME'],
                'key_reference'         => $column_structure_arr['COLUMN_KEY_REFERENCE'],
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

    public static function get_class_table() : string
    {
        return self::CONFIG_RUNTIME['class_table'];
    }

    public function get_meta(string $class_name, /* scalar */ $object_id) : array
    {
        $Connection = $this->get_connection($CR);
//        $q = "
//SELECT
//    *
//FROM
//    {$Connection::get_tprefix()}{$this::get_meta_table()}
//WHERE
//    meta_class_name = :class_name
//    AND meta_object_id = :object_id
//        ";
//        $data = $Connection->prepare($q)->execute(['class_name' => $class_name, 'object_id' => $object_id])->fetchRow();

        $class_id = $this->get_class_id($class_name);

        $q = "
SELECT
    *
FROM
    {$Connection::get_tprefix()}{$this::get_meta_table()}
WHERE
    meta_class_id = :meta_class_id
    AND meta_object_id = :meta_object_id
        ";
        $data = $Connection->prepare($q)->execute(['meta_class_id' => $class_id, 'meta_object_id' => $object_id])->fetchRow();
        $data['meta_class_id'] = $class_name;
        $data['meta_class_uuid'] = $this->get_class_uuid($class_name);

        unset($data['meta_object_uuid_binary']);//this is only needed internally for MySQL - this MUST stay removed!
        return $data;
    }

    /**
     * Returns class and id of object by uuid
     * @param  string $uuid
     * @return array - class and id
     */
    public function get_meta_by_uuid(string $uuid) : array
    {
        if (!GeneralUtil::is_uuid($uuid)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided $uuid argument %1s is not a valid UUID.'), $uuid));
        }
        $Connection = $this->get_connection($CR);

        $q = "
SELECT 
    *,
    classes.class_name AS meta_class_name 
FROM
    {$Connection::get_tprefix()}{$this::get_meta_table()} AS meta
    INNER JOIN {$Connection::get_tprefix()}{$this::get_class_table()} AS classes ON classes.class_id = meta.meta_class_id
WHERE
    meta_object_uuid_binary = UUID_TO_BIN(:meta_object_uuid)";

        $data = $Connection->prepare($q)->execute([ 'meta_object_uuid' => $uuid])->fetchRow();
        unset($data['meta_object_uuid_binary']);
        unset($data['class_uuid_binary']);
        if (!count($data)) {
            //throw new RunTimeException(sprintf(t::_('No meta data is found for object with UUID %s.'), $uuid));
            $data = $this->FallbackStore->get_meta_by_uuid( $uuid);
        }
        //$ret['object_id'] = $data['object_id'];
        //$ret['class'] = $data['class_name'];

        //return $ret;
        return $data;
    }

    protected function update_meta(ActiveRecordInterface $ActiveRecord) : void
    {
        // it can happen to call update_ownership on a record that is new but this can happen if there is save() recursion
        if ($ActiveRecord->is_new() /* &&  !$object->is_in_method_twice('save') */) {
            throw new RunTimeException(sprintf(t::_('Trying to update the meta data of a new object of class "%s". Instead the new obejcts have their metadata created with Mysql::create_meta() method.'), get_class($ActiveRecord)));
        }
        $Connection = $this->get_connection($CR);
        $meta_table = self::get_meta_table();

        $object_last_update_microtime = (int) microtime(TRUE) * 1_000_000;


//        $q = "
//UPDATE
//    {$Connection::get_tprefix()}{$meta_table}
//SET
//    meta_object_last_update_microtime = :meta_object_last_update_microtime
//WHERE
//    meta_class_name = :meta_class_name
//    AND meta_object_id = :meta_object_id
//        ";
//
//        $params = [
//            'meta_class_name'                    => get_class($ActiveRecord),
//            'meta_object_id'                     => $ActiveRecord->get_id(),
//            'meta_object_last_update_microtime'  => $object_last_update_microtime,
//        ];

        $CurrentUser = self::get_service('CurrentUser');
        $role_id = $CurrentUser->get()->get_role()->get_id();
        $q = "
UPDATE
    {$Connection::get_tprefix()}{$meta_table} 
SET
    meta_object_last_update_microtime = :meta_object_last_update_microtime,
    meta_object_last_update_role_id = :meta_object_last_update_role_id
WHERE
    meta_class_id = :meta_class_id
    AND meta_object_id = :meta_object_id
        ";

        $params = [
            'meta_class_id'                      => $this->get_class_id(get_class($ActiveRecord)),
            'meta_object_id'                     => $ActiveRecord->get_id(),
            'meta_object_last_update_microtime'  => $object_last_update_microtime,
            'meta_object_last_update_role_id'    => $role_id,
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


//        $q = "
//INSERT
//INTO
//    {$Connection::get_tprefix()}{$meta_table}
//    (meta_object_uuid_binary, meta_class_name, meta_object_id, meta_object_create_microtime, meta_object_last_update_microtime)
//VALUES
//    (:meta_object_uuid_binary, :meta_class_name, :meta_object_id, :meta_object_create_microtime, :meta_object_last_update_microtime)
//        ";
//
//        $params = [
//            'meta_class_name'                    => get_class($ActiveRecord),
//            'meta_object_id'                     => $ActiveRecord->get_id(),
//            'meta_object_create_microtime'       => $object_create_microtime,
//            'meta_object_last_update_microtime'  => $object_create_microtime,
//            'meta_object_uuid_binary'            => $uuid_binary,
//            //'object_uuid'                   => $uuid,
//        ];

        $CurrentUser = self::get_service('CurrentUser');
        $role_id = $CurrentUser->get()->get_role()->get_id();
        $q = "
INSERT
INTO
    {$Connection::get_tprefix()}{$meta_table}
    (meta_object_uuid_binary, meta_class_id, meta_object_id, meta_object_create_microtime, meta_object_last_update_microtime, meta_object_create_role_id, meta_object_last_update_role_id)
VALUES
    (:meta_object_uuid_binary, :meta_class_id, :meta_object_id, :meta_object_create_microtime, :meta_object_last_update_microtime, :meta_object_create_role_id, :meta_object_last_update_role_id)
        ";

        $params = [
            'meta_class_id'                      => $this->get_class_id(get_class($ActiveRecord)),
            'meta_object_id'                     => $ActiveRecord->get_id(),
            'meta_object_create_microtime'       => $object_create_microtime,
            'meta_object_last_update_microtime'  => $object_create_microtime,
            'meta_object_uuid_binary'            => $uuid_binary,
            'meta_object_create_role_id'         => $role_id,
            'meta_object_last_update_role_id'    => $role_id,
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

                    //implementation detail of Mysql store
                    if (
                        in_array('class_name', $field_names_arr, TRUE)
                        && $ActiveRecord::has_property('class_id')
                        && $record_data_to_save['class_name']
                        && !$record_data_to_save['class_id']
                    ) {
                        $record_data_to_save['class_id'] = $this->get_class_id($record_data_to_save['class_name']);
                    }

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


//            try {
                $Statement = $Connection->prepare($q);
                $Statement->execute($data_arr);
//            } catch (\Guzaba2\Database\Exceptions\DuplicateKeyException $Exception) {
//                throw new \Guzaba2\Database\Exceptions\DuplicateKeyException(NULL, $Exception->getMessage(), 0, $Exception);
//            } catch (\Guzaba2\Database\Exceptions\ForeignKeyConstraintException $Exception) {
//                throw new \Guzaba2\Database\Exceptions\ForeignKeyConstraintException(NULL, $Exception->getMessage(), 0, $Exception);
//            }

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

            // $record_data = $ActiveRecord->get_record_data();
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
            if ($ActiveRecord::uses_meta()) {
                //$uuid = $this->create_meta($ActiveRecord);
                $this->create_meta($ActiveRecord);
            }
        } else {
            if ($ActiveRecord::uses_meta()) {
                $this->update_meta($ActiveRecord);
                //$uuid = $ActiveRecord->get_uuid();
            }

        }
        //$ret = array_merge($record_data, $this->get_meta());


        // TODO set uuid to $ActiveRecord

        //COMMIT DB TRANSACTION

        //$this->is_new = FALSE;
        //this flag will be updated in activerecord::save()
        //return $uuid;
        $ret = ['data' => $record_data, 'meta' => $ActiveRecord::uses_meta() ? $this->get_meta(get_class($ActiveRecord), $ActiveRecord->get_id() ) : [] ];

        return $ret;
    }

    public function &get_data_pointer(string $class, array $index) : array
    {
        if (array_key_exists('<', $index)) {
            if (!$index['<']) {
                //throw
            }
            //check column exists
        }
        if (array_key_exists('>', $index)) {
            if (!$index['>']) {
                //throw
            }
            //check column exists
        }
        if (array_key_exists('<', $index) && array_key_exists('>', $index)) {
            if ($index['<'] === $index['>']) {
                throw new InvalidArgumentException(sprintf(t::_('Both sorting keys "<" and ">" are provided and both use the same column %1s.'), $index['<'] ));
            }
        }
        $sort_by = NULL;
        $sort_desc = FALSE;
        if (array_key_exists('<', $index)) {
            $sort_by = $index['<'];
            $sort_desc = FALSE;
            unset($index['<']);
        }
        if (array_key_exists('>', $index)) {
            $sort_by = $index['>'];
            $sort_desc = TRUE;
            unset($index['>']);
        }
        //string $class, array $index, int $offset = 0, int $limit = 0, bool $use_like = FALSE, ?string $sort_by = NULL, bool $sort_desc = FALSE, ?int &$total_found_rows = NULL
        $data = $this->get_data_by($class, $index, 0, 0, FALSE, $sort_by, $sort_desc);

        if (count($data)) {
            $primary_index = $class::get_index_from_data($data[0]);
            if (is_null($primary_index)) {
                throw new RunTimeException(sprintf(t::_('The primary index for class %s is not found in the retreived data.'), $class));
            }
            if (count($primary_index) > 1) {
                throw new RunTimeException(sprintf(t::_('The class %s has compound index and can not have meta data.'), $class));
            }
            $ret['meta'] = $this->get_meta($class, current($primary_index));
            //$data contains also meta data
            $record_data = [];
            $sturcutre_data = $this->get_storage_columns_data($class);
            //print_r($sturcutre_data);
            $column_names = array_map(fn($column_data) => $column_data['COLUMN_NAME'], $sturcutre_data);
//            $column_names = [];
//            foreach ($structure_data as $structure_datum) {
//                $column_names
//            }
            foreach ($data[0] as $column_name=>$column_datum) {
                if (in_array($column_name, $column_names)) {
                    $record_data[$column_name] = $column_datum;
                }
            }
            //$ret['data'] = $data[0];
            $ret['data'] = $record_data;
            $this->hits++;
        } else {
            $this->misses--;
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

//    protected function create_meta_if_does_not_exist()
//    {
//
//        if ($this->meta_exists) {
//            return true;
//        }
//
//        // TODO can use create table if not exists
//        $Connection = $this->get_connection($CR);
//        $q = "
//SELECT
//    *
//FROM
//    information_schema.tables
//WHERE
//    table_schema = :table_schema
//    AND table_name = :table_name
//LIMIT 1
//        ";
//        $s = $Connection->prepare($q);
//        $s->table_schema = $Connection::get_database();
//        $s->table_name = $Connection::get_tprefix() . static::CONFIG_RUNTIME['meta_table'];
//
//        $ret = $s->execute()->fetchAll();
//
//        if (!empty($ret)) {
//            $this->meta_exists = true;
//            return true;
//        }
//
//        $q = "
//        CREATE TABLE `{$s->table_name}` (
//  `meta_object_uuid_binary` binary(16) NOT NULL,
//  `meta_object_uuid` char(36) GENERATED ALWAYS AS (bin_to_uuid(`meta_object_uuid_binary`)) VIRTUAL NOT NULL,
//  -- `meta_class_name` varchar(255) NOT NULL,
//  `meta_class_id` bigint(20) UNSIGNED NOT NULL,
//  `meta_object_id` bigint(20) UNSIGNED NOT NULL,
//  `meta_object_create_microtime` bigint(16) UNSIGNED NOT NULL,
//  `meta_object_last_update_microtime` bigint(16) UNSIGNED NOT NULL,
//  `meta_object_create_transaction_id` bigint(20) UNSIGNED NOT NULL DEFAULT '0',
//  `meta_object_last_update_transction_id` bigint(20) UNSIGNED NOT NULL DEFAULT '0',
//  PRIMARY KEY (`object_uuid_binary`),
//  CONSTRAINT `class_name` UNIQUE (`class_name`,`object_id`)
//) ENGINE=InnoDB DEFAULT CHARSET=utf8;
//
//        ";
//        $s = $Connection->prepare($q);
//        $s->execute();
//
//        $this->meta_exists = true;
//    }

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
WHERE `meta_object_uuid` = '{$uuid}'
";
        $s = $Connection->prepare($q);
        $s->execute();
    }

    /**
     * Returns all results matching criteria
     * @param string $class class name
     * @param array  $index [$column => $value]
     * @param int $offset
     * @param int $limit
     * @param bool $use_like
     * @param string $sort_by
     * @param $sort_desc
     * @return array dataset
     */
    public function get_data_by(string $class, array $index, int $offset = 0, int $limit = 0, bool $use_like = FALSE, ?string $sort_by = NULL, bool $sort_desc = FALSE, ?int &$total_found_rows = NULL) : array
    {

        //initialization
        $record_data = self::get_record_structure($this->get_unified_columns_data($class));

        //lookup in DB

        /**
         * @var Connection
         */
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
        $sort_str = '';

        if ($offset || $limit) {
            $l_str = "LIMIT {$offset}, {$limit}";
        } else {
            $l_str = "";
        }

        $sort_direction = [
            TRUE => 'DESC',
            FALSE => 'ASC',
        ];
        if ($sort_by !== NULL) {
            $sort_str = " ORDER BY " . $sort_by . " " . $sort_direction[$sort_desc];
        }

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
        if (array_key_exists('meta_object_uuid', $index)) {


            if (GeneralUtil::is_uuid( (string) $index['meta_object_uuid'])) {
                $meta_data = $this->get_meta_by_uuid($index['meta_object_uuid']);
                $object_id = $meta_data['meta_object_id'];
                $w[] = $main_index[0] . ' = :object_id';
                $b['object_id'] = $object_id;
            } else {
                //do a like search
                $w[] = 'meta_object_uuid LIKE :meta_object_uuid';
                if ($use_like) {
                    $b['meta_object_uuid'] = '%'.$index['meta_object_uuid'].'%';
                } else {
                    //$b['meta_object_uuid'] = $index['meta_object_uuid'];//this is pointless - it is an error
                    //either provide an exact UUID or $use_like = TRUE
                    throw new InvalidArgumentException(sprintf(t::_('An invalid/partial UUID "%s" provided and the $use_like argument is set to FALSE.'), $index['meta_object_uuid'] ));
                }

            }

        } else {

            //an implementation detail of MySQL store is to replace class_name lookups to by class_id for performance reasons
            if (array_key_exists('class_name', $index) && $class::has_property('class_id')) {
                $index['class_id'] = $this->get_class_id($index['class_name']);
                unset($index['class_name']);
            }

            foreach ($index as $field_name=>$field_value) {
                if (!is_string($field_name)) {
                    //perhaps get_instance was provided like this [1,2] instead of ['col1'=>1, 'col2'=>2]... The first notation may get supported in future by inspecting the columns and assume the order in which the primary index is provided to be correct and match it
                    throw new RunTimeException(sprintf(t::_('It seems wrong values were provided to object instance. The provided array must contain keys with the column names and values instead of just values. Please use new %s([\'col1\'=>1, \'col2\'=>2]) instead of new %s([1,2]).'), $class, $class, $class));
                }

                if ($field_name !== 'meta_object_uuid') {
                    if (!array_key_exists($field_name, $record_data)) {
                        throw new RunTimeException(sprintf(t::_('A field named "%s" that does not exist is supplied to the constructor of an object of class "%s".'), $field_name, $class));
                    }
                }

                $j[$table_name] = $Connection::get_tprefix().$table_name;
                //$w[] = "{$table_name}.{$field_name} {$this->db->equals($field_value)} :{$field_name}";
                //$b[$field_name] = $field_value;
                if (is_null($field_value)) {
                    $w[] = "{$class::get_main_table()}.{$field_name} {$Connection::equals($field_value)} NULL";
                } else {
                    $w[] = "{$class::get_main_table()}.{$field_name} {$Connection::equals($field_value, $use_like)} :{$field_name}";
                    if ($use_like) {
                        $b[$field_name] = "%".$field_value."%";
                    } else {
                        $b[$field_name] = $field_value;
                    }
                }
            } //end foreach

            if (empty($w)) {
                $w[] = "1";
            }
        }
        //here we join the tables and load only the data from the joined tables
        //this means that some tables / properties will not be loaded - these will be loaded on request
        //$j_str = implode(" INNER JOIN ", $j);//cant do this way as now we use keys
        //the key is the alias of the table, the value is the real full name of the table (including the prefix)
        $j_alias_arr = [];
        $select_arr = [];

        foreach ($j as $table_alias=>$full_table_name) {
            //and the class_id & object_id are moved to the WHERE CLAUSE
            if ($table_alias == $table_name) {
                //do not add ON clause - this is the table containing the primary index and the first shard
                $on_str = "";
//            } elseif ($table_alias == 'ownership_table') {
//                $on_arr = [];
//
//                $on_arr[] = "ownership_table.class_id = :class_id";
//                $b['class_id'] = static::_class_id;
//
//                $w[] = "ownership_table.object_id = {$table_name}.{$main_index[0]}";//the ownership table does not support compound primary index
//
//                $on_str = "ON ".implode(" AND ", $on_arr);
            } else {
                $on_arr = [];
                foreach ($main_index as $column_name) {
                    $on_arr[] = "{$table_alias}.{$column_name} = {$table_name}.{$column_name}";
                }
                $on_str = "ON ".implode(" AND ", $on_arr);
            }
            $j_alias_arr[] = "`{$full_table_name}` AS `{$table_alias}` {$on_str}";
            $select_arr[] = $table_alias . ".*";
            //$this->data_is_loaded_from_tables[] = $table_alias;
        }

        $j_str = implode(PHP_EOL."\t"."LEFT JOIN ", $j_alias_arr);//use LEFT JOIN as old record will have no data in the new shards
        unset($j, $j_alias_arr);
        $w_str = implode(" AND ", $w);
        unset($w);
        $select_str = implode(PHP_EOL."\t".", ", $select_arr);
        unset($select_arr);


        // GET meda data
        //, meta.meta_class_name
        $select_str .= "
            , meta.meta_object_uuid
            , meta.meta_class_id
            , meta.meta_object_id
            , meta.meta_object_create_microtime
            , meta.meta_object_last_update_microtime
            , meta.meta_object_create_transaction_id
            , meta.meta_object_last_update_transaction_id
        ";

        // JOIN meta data
        $meta_table = $Connection::get_tprefix().$this::get_meta_table();
        $class_table = $Connection::get_tprefix().$this::get_class_table();
        // -- meta.meta_class_name = :meta_class_name
        $meta_str = " 
LEFT JOIN 
    `{$meta_table}` as `meta` 
    ON 
        meta.meta_object_id = {$table_name}.{$main_index[0]} 
    AND
        meta.meta_class_id = :meta_class_id
        
";
        //$b['meta_class_name'] = $class;
        $b['meta_class_id'] = $this->get_class_id($class);

        $q_data = "
SELECT
    {$select_str}
FROM
    {$j_str}
    {$meta_str}
WHERE
    {$w_str}
    {$sort_str}
    {$l_str}
";

        $q_count = "
SELECT
    COUNT(*) as total_found_rows
FROM
    {$j_str}
    {$meta_str}
WHERE
    {$w_str}
";


        if ($limit) {

            if ($Connection instanceof \Guzaba2\Database\Sql\Mysql\ConnectionCoroutine) {
                $connection_class = get_class($Connection);
                unset($CR);//release the connection
                $queries = [
                    ['query' => $q_data, 'params' => $b],
                    ['query' => $q_count, 'params' => $b]
                ];
                list($data, $count) = $connection_class::execute_parallel_queries($queries);
            } else {
                $Statement = $Connection->prepare($q_data);
                $Statement->execute($b);
                $data = $Statement->fetchAll();

                $Statement = $Connection->prepare($q_count);
                $Statement->execute($b);
                $count = $Statement->fetchAll();
            }

            $total_found_rows = $count[0]['total_found_rows'];

        } else {
            $Statement = $Connection->prepare($q_data);
            $Statement->execute($b);
            $data = $Statement->fetchAll();
            $total_found_rows = count($data);
        }





        if (empty($data)) {
            // $this->throw_not_found_exception($class, self::form_lookup_index($index));
        }
        $class_uuid = $this->get_class_uuid($class);
        $add_class_name = $class::has_property('class_name') && $class::has_property('class_id');
        foreach ($data as &$record) {
            $record['meta_class_name'] = $class;
            $record['meta_class_uuid'] = $class_uuid;
            if ($add_class_name && array_key_exists('class_id', $record) && !array_key_exists('class_name', $record)) {
                $record['class_name'] = $this->get_class_name($record['class_id']);
            }
        }

        return $data;

    }


    public function get_hits() : int
    {
        return $this->hits;
    }

    public function get_misses() : int
    {
        return $this->misses;
    }

    public function get_hits_percentage() : float {
        $ret = 0.0;
        $hits = $this->get_hits();
        $misses = $this->get_misses();
        $total = $hits + $misses;

        if (0 != $total) {
            $ret = (float) ($hits / $total * 100.0);
        }

        return $ret;
    }

    public function reset_hits() : void
    {
        $this->hits = 0;
    }

    public function reset_misses() : void
    {
        $this->misses = 0;
    }

    public function reset_stats() : void
    {
        $this->reset_hits();
        $this->reset_misses();
    }

    public function reset_all()
    {
        /*
        TODO
        $this->clear_cache();
        $this->reset_stats();
        */
    }
}
