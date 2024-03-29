<?php

declare(strict_types=1);

namespace Guzaba2\Orm\Store\Sql;

use Azonmedia\Utilities\ArrayUtil;
use Azonmedia\Utilities\GeneralUtil;
use Guzaba2\Authorization\CurrentUser;
use Guzaba2\Authorization\Interfaces\AuthorizationProviderInterface;
use Guzaba2\Authorization\Interfaces\UserInterface;
use Guzaba2\Authorization\Role;
use Guzaba2\Base\Exceptions\BadMethodCallException;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Database\Exceptions\DuplicateKeyException;
use Guzaba2\Database\Exceptions\ForeignKeyConstraintException;
use Guzaba2\Database\Interfaces\ConnectionInterface;
use Guzaba2\Database\Sql\Mysql\Connection;
use Guzaba2\Database\Sql\Statement;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Store\Database;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Orm\Store\Interfaces\StructuredStoreInterface;
use Guzaba2\Cache\Interfaces\CacheStatsInterface;
use Guzaba2\Orm\Store\Interfaces\TransactionalStoreInterface;
use Guzaba2\Orm\Store\NullStore;
use Guzaba2\Kernel\Kernel as Kernel;
use Guzaba2\Database\Sql\Mysql\Mysql as MysqlDB;
use Guzaba2\Resources\ScopeReference;
use Guzaba2\Transaction\Transaction;
use Guzaba2\Transaction\TransactionManager;
use Guzaba2\Translator\Translator as t;
use Ramsey\Uuid\Uuid;

/**
 * Class Mysql
 * @package Guzaba2\Orm\Store\Sql
 *
 * No point implementing TransactionalResourceInterface as it will still not be possible to implement new_transaction()
 * As this required also passing to the caller scope a scope reference for the connection, not just the transaction.
 */
class Mysql extends Database implements StructuredStoreInterface, CacheStatsInterface, TransactionalStoreInterface
{
    protected const CONFIG_DEFAULTS = [
        'meta_table'        => 'object_meta',
        'class_table'       => 'classes',
        'services'          => [
            'AuthorizationProvider',
        ],
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


    public function __construct(StoreInterface $FallbackStore, string $connection_class, string $no_coroutine_connection_class)
    {
        parent::__construct();
        if (!$connection_class) {
            throw new InvalidArgumentException(sprintf(t::_('The Store %s needs $connection_class argument provided.'), get_class($this)));
        }
        $this->FallbackStore = $FallbackStore ?? new NullStore();
        if (!class_exists($connection_class)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided coroutine connection class %1$s does not exist.'), $connection_class));
        }
        if (!class_exists($no_coroutine_connection_class)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided no coroutine connection class %1$s does not exist.'), $no_coroutine_connection_class));
        }
        $this->connection_class = $connection_class;
        $this->no_coroutine_connection_class = $no_coroutine_connection_class;
        //$this->create_meta_if_does_not_exist();//no need - other Store will be provided - MysqlCreate
        $this->hits = 0;
        $this->misses = 0;

        $this->update_classes_data();
    }

    /**
     * @throws RunTimeException
     */
    private function load_classes_data(): void
    {
        $Connection = $this->get_connection($CR);
        $q = "
SELECT
    *
FROM
    {$Connection::get_tprefix()}{$this::get_class_table()}
ORDER BY
    class_id ASC
        ";
        $data = $Connection->prepare($q)->execute()->fetchAll();
        foreach ($data as $record) {
            unset($record['class_uuid_binary']);
            $this->classes_data[$record['class_name']] = $record;
        }
    }

    /**
     * @throws InvalidArgumentException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    private function update_classes_data(): void
    {
        $this->load_classes_data();
        $active_record_classes = ActiveRecord::get_active_record_classes();
        foreach ($active_record_classes as $class_name) {
            if (!$this->has_class_data($class_name)) {
                $this->insert_new_class($class_name);//could be converted to coroutine call but new classes are added rarely and no point optimizing this...
            }
        }
        $active_record_interfaces = ActiveRecord::get_active_record_interfaces();
        foreach ($active_record_interfaces as $interface_name) {
            if (!$this->has_class_data($interface_name)) {
                $this->insert_new_interface($interface_name);//could be converted to coroutine call but new classes are added rarely and no point optimizing this...
            }
        }
        $this->load_classes_data();//reload the data
    }

    /**
     * @param string $interface_name
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    private function insert_new_interface(string $interface_name): void
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

        /** @var $implementing_class ActiveRecordInterface */
        $implementing_class = ActiveRecord::get_active_record_interface_implementation($interface_name);
        if (!$implementing_class) {
            throw new LogicException(sprintf(t::_('No implementation for interface %1$s was found.'), $interface_name));
        }

        $b = [
            'class_uuid_binary' => $uuid_binary,
            'class_name'        => $interface_name,
            'class_table'       => $implementing_class::get_main_table(),
        ];
        $Connection->prepare($q)->execute($b);

        Kernel::log(sprintf(t::_('%1$s: Detected and added a new class %2$s with UUID %3$s.'), __CLASS__, $interface_name, $uuid->toString()));
    }

    /**
     * @param string $class_name
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    private function insert_new_class(string $class_name): void
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

        Kernel::log(sprintf(t::_('%1$s: Detected and added a new class %2$s with UUID %3$s.'), __CLASS__, $class_name, $uuid->toString()));
    }

    /**
     * @param string $class_name
     * @return bool
     * @throws InvalidArgumentException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function has_class_data(string $class_name): bool
    {
        if (!$class_name) {
            throw new InvalidArgumentException(sprintf(t::_('No $class_name argument provided.')));
        }
        if (!class_exists($class_name) && !interface_exists($class_name)) {
            throw new InvalidArgumentException(sprintf(t::_('There is no class or interface %1$s.'), $class_name));
        }
        return isset($this->classes_data[$class_name]);
    }

    /**
     * @param string $class_name
     * @return array
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function get_class_data(string $class_name): array
    {
        if (!$this->has_class_data($class_name)) {
            throw new RunTimeException(sprintf(t::_('The Mysql store has no data for class %1$s.'), $class_name));
        }
        return $this->classes_data[$class_name];
    }

    /**
     * @param int $class_id
     * @return string|null
     */
    public function get_class_name(int $class_id): ?string
    {
        $ret = null;
        if (!$class_id) {
            throw new InvalidArgumentException(sprintf(t::_('No class_id provided.')));
        }
        foreach ($this->classes_data as $classes_datum) {
            if ($classes_datum['class_id'] === $class_id) {
                $ret = $classes_datum['class_name'];
                break;
            }
        }
        return $ret;
    }

    /**
     * @param string $class_name
     * @return int|null
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function get_class_id(string $class_name): ?int
    {
        $active_record_interface = ActiveRecord::get_class_active_record_interface($class_name);
        if ($active_record_interface) {
            $class_name = $active_record_interface;
        }
        return $this->has_class_data($class_name) ? $this->get_class_data($class_name)['class_id'] : null ;
    }

    /**
     * @param string $class_name
     * @return string|null
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function get_class_uuid(string $class_name): ?string
    {
        return $this->has_class_data($class_name) ? $this->get_class_data($class_name)['class_uuid'] : null ;
    }

    /**
     * @param ScopeReference|null $ScopeReference
     * @return ConnectionInterface
     * @throws RunTimeException
     */
    public function get_connection(?ScopeReference &$ScopeReference): ConnectionInterface
    {

//        if (Coroutine::inCoroutine()) {
//            $connection_class = $this->connection_class;
//        } else {
//            if (!$this->no_coroutine_connection_class) {
//                throw new RunTimeException(sprintf(t::_('The Store %s is used outside coroutine context but there is no $no_coroutine_connection_class configured/provided to the constructor.'), get_class($this) ));
//            }
//            $connection_class = $this->no_coroutine_connection_class;
//        }
        $connection_class = $this->get_connection_class();

        return static::get_service('ConnectionFactory')->get_connection($connection_class, $ScopeReference);
    }

    /**
     * @return Transaction|null
     * @throws RunTimeException
     */
    public function get_current_transaction(): ?Transaction
    {
        // TODO: Implement get_current_transaction() method.
        $Connection = $this->get_connection($CR);
        /** @var TransactionManager $TXM */
        $TXM = self::get_service('TransactionManager');
        return $TXM->get_current_transaction($Connection->get_resource_id());
    }

    /**
     * Returns a connection class based on whether the code is in coroutine context or not.
     * @return string
     */
    public function get_connection_class(): string
    {
        if (Coroutine::inCoroutine()) {
            $connection_class = $this->connection_class;
        } else {
            $connection_class = $this->no_coroutine_connection_class;
        }
        return $connection_class;
    }

    /**
     * Returns a unified structure
     * @param string $class
     * @return array
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws BadMethodCallException
     * @throws \ReflectionException
     */
    public function get_unified_columns_data(string $class): array
    {

        if (interface_exists($class)) {
            //get one implementing class
            $class = ActiveRecord::get_active_record_interface_implementation($class);
        }

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
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws BadMethodCallException
     * @throws \ReflectionException
     */
    public function get_storage_columns_data(string $class): array
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
            throw new RunTimeException(sprintf(t::_('No columns information was obtained for class %s with main table %s. Please check is the main table for the class and table prefix set correctly in the connection config.'), $class, $class::get_main_table()));
        }

        return $this->storage_columns_data[$class];
    }

    /**
     * Returns a unified structure
     * @param string $class
     * @return array
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    final protected function get_unified_columns_data_by_table_name(string $table_name): array
    {
        $storage_structure_arr = $this->get_storage_columns_data_by_table_name($table_name);

        return $this->unify_columns_data($storage_structure_arr);
    }

    /**
     * Returns the backend storage structure.
     * @param string $table_name
     * @return array
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    final protected function get_storage_columns_data_by_table_name(string $table_name): array
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
        $s->table_name = $Connection::get_tprefix() . $table_name;
        $ret = $s->execute()->fetchAll();

        if (!$ret) {
            throw new RunTimeException(sprintf(t::_('The table %1$s.%2$s does not exist. Please check the class main_table and the connection tprefix (table prefix).'), $Connection::get_database(), $Connection::get_tprefix() . $table_name));
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
        $s->table_name = $Connection::get_tprefix() . $table_name;
        $keys_ret = $s->execute()->fetchAll();

        foreach ($keys_ret as $key_row) {
            foreach ($ret as &$row) {
                if ($row['COLUMN_NAME'] === $key_row['COLUMN_NAME']) {
                    $row['COLUMN_KEY_NAME'] = $key_row['CONSTRAINT_NAME'];
                    if ($key_row['REFERENCED_TABLE_SCHEMA']) {
                        $row['COLUMN_KEY_REFERENCE'] = $key_row['REFERENCED_TABLE_SCHEMA'] . '.' . $key_row['REFERENCED_TABLE_NAME'] . '.' . $key_row['REFERENCED_COLUMN_NAME'];//TODO - improve this
                    }
                }
            }
        }

        //mysql store specific implementation - it uses class_id instead of class_name
        $has_class_id_column = false;
        foreach ($ret as &$row) {
            if ($row['COLUMN_NAME'] === 'class_id') {
                $has_class_id_column = true;
                break;
            }
        }
        $has_class_name_column = false;
        foreach ($ret as &$row) {
            if ($row['COLUMN_NAME'] === 'class_name') {
                $has_class_name_column = true;
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
            $s->table_name = $Connection::get_tprefix() . 'classes';
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
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function unify_columns_data(array $storage_structure_arr): array
    {
        $ret = [];
        for ($aa = 0; $aa < count($storage_structure_arr); $aa++) {
            $column_structure_arr = $storage_structure_arr[$aa];
            $ret[$aa] = [
                'name'                  => strtolower($column_structure_arr['COLUMN_NAME']),
                'native_type'           => $column_structure_arr['DATA_TYPE'],
                'php_type'              => MysqlDB::TYPES_MAP[$column_structure_arr['DATA_TYPE']],
                'size'                  => MysqlDB::get_column_size($column_structure_arr),
                'nullable'              => $column_structure_arr['IS_NULLABLE'] === 'YES',
                'column_id'             => (int) $column_structure_arr['ORDINAL_POSITION'],
                'primary'               => $column_structure_arr['COLUMN_KEY'] === 'PRI',
                'default_value'         => $column_structure_arr['COLUMN_DEFAULT'] === 'NULL' ? null : $column_structure_arr['COLUMN_DEFAULT'],
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

    public static function get_meta_table(): string
    {
        return self::CONFIG_RUNTIME['meta_table'];
    }

    public static function get_class_table(): string
    {
        return self::CONFIG_RUNTIME['class_table'];
    }

    public function get_meta(string $class_name, /* scalar */ $object_id): array
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
     * @param string $uuid
     * @return array - class and id
     * @throws InvalidArgumentException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws RunTimeException
     */
    public function get_meta_by_uuid(string $uuid): array
    {
        if (!GeneralUtil::is_uuid($uuid)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided $uuid argument %1$s is not a valid UUID.'), $uuid));
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
            $data = $this->FallbackStore->get_meta_by_uuid($uuid);
        }
        return $data;
    }

    public function get_meta_by_id(string $class_name, int $object_id): array
    {
        if (!$class_name) {
            throw new InvalidArgumentException(sprintf(t::_('No class_name is provided.')));
        }
        if (!is_a($class_name, ActiveRecordInterface::class, true)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided class_name %s is not a %s.'), $class_name, ActiveRecordInterface::class));
        }
        if (!$object_id) {
            throw new InvalidArgumentException(sprintf(t::_('No object_id provided.')));
        }
        if ($object_id < 0) {
            throw new InvalidArgumentException(sprintf(t::_('The provided object_id is negative.')));
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
    meta_class_id = :class_id
    AND meta_object_id = :object_id
        ";
        $class_id = $this->get_class_id($class_name);

        $data = $Connection->prepare($q)->execute([ 'class_id' => $class_id, 'object_id' => $object_id])->fetchRow();
        unset($data['meta_object_uuid_binary']);
        unset($data['class_uuid_binary']);
        if (!count($data)) {
            //throw new RunTimeException(sprintf(t::_('No meta data is found for object with UUID %s.'), $uuid));
            $data = $this->FallbackStore->get_meta_by_id($class_name, $object_id);
        }
        return $data;
    }

    /**
     * @param ActiveRecordInterface $ActiveRecord
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    protected function update_meta(ActiveRecordInterface $ActiveRecord): void
    {
        // it can happen to call update_ownership on a record that is new but this can happen if there is save() recursion
        if ($ActiveRecord->is_new() /* &&  !$object->is_in_method_twice('save') */) {
            throw new RunTimeException(sprintf(t::_('Trying to update the meta data of a new object of class "%s". Instead the new obejcts have their metadata created with Mysql::create_meta() method.'), get_class($ActiveRecord)));
        }
        $Connection = $this->get_connection($CR);
        $meta_table = self::get_meta_table();

        $object_last_update_microtime = (int) microtime(true) * 1_000_000;


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

    /**
     * @param ActiveRecordInterface $ActiveRecord
     * @return string
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    protected function create_meta(ActiveRecordInterface $ActiveRecord): string
    {

        //$start_time = microtime(true);

        $Connection = $this->get_connection($CR);
        $meta_table = self::get_meta_table();

        $object_create_microtime = microtime(true) * 1_000_000;

        //print 'Check 1: '.(microtime(true) - $start_time).PHP_EOL;

        $uuid = Uuid::uuid4();
        $uuid_binary = $uuid->getBytes();

        //print 'Check 2: '.(microtime(true) - $start_time).PHP_EOL;

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



        /** @var CurrentUser $CurrentUser */
        $CurrentUser = self::get_service('CurrentUser');

        //print 'Check 3: '.(microtime(true) - $start_time).PHP_EOL;

        /** @var UserInterface $User */
        $User = $CurrentUser->get();

        //print 'Check 4: '.(microtime(true) - $start_time).PHP_EOL;

        $role_id = $User->get_role()->get_id();

        //print 'Check 5: '.(microtime(true) - $start_time).PHP_EOL;

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

        //print 'Check 6: '.(microtime(true) - $start_time).PHP_EOL;

        $Statement = $Connection->prepare($q);

        //print 'Check 7: '.(microtime(true) - $start_time).PHP_EOL;

        $Statement->execute($params);

        //print 'Check 8: '.(microtime(true) - $start_time).PHP_EOL;


        return (string) $uuid;
    }

    /**
     * @param ActiveRecordInterface $ActiveRecord
     * @return array
     * @throws DuplicateKeyException
     * @throws ForeignKeyConstraintException
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function update_record(ActiveRecordInterface $ActiveRecord): array
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

        //$start_time = microtime(true);

        $columns_data = $ActiveRecord::get_columns_data();
        $record_data = $ActiveRecord->get_record_data();
        $main_index = $ActiveRecord->get_primary_index_columns();
        $index = $ActiveRecord->get_id();

        //print 'Check 1: '.(microtime(true) - $start_time).PHP_EOL;

        if ($ActiveRecord->is_new()) {

            $record_data_to_save = [];
            foreach ($columns_data as $field_data) {
                $record_data_to_save[$field_data['name']] = $record_data[$field_data['name']];
            }

            //print 'Check 2: '.(microtime(true) - $start_time).PHP_EOL;


            //TO DO - find more intelligent solution
            // if (!$index[$main_index[0]]) {
            //temporary fix
            //if (true) {
            //temporary fix end
//            if (!$ActiveRecord::uses_autoincrement()) {
//                //TODO IVO
//                $index[$main_index[0]] = $ActiveRecord->db->get_new_id($partition_name, $main_index[0]);
//                $field_names_arr = array_unique(array_merge($partition_fields, $main_index));
//                $field_names_str = implode(', ', $field_names_arr);
//                $placeholder_str = implode(', ', array_map($prepare_binding_holders_function, $field_names_arr));
//                $data_arr = array_merge($record_data_to_save, $ActiveRecord->index);
//            } else {
            //assumed there is autoincrement

                $field_names_arr = $ActiveRecord::get_column_names();//this includes the full index

                //implementation detail of Mysql store
                if (
                    in_array('class_name', $field_names_arr, true)
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
                    return ':' . $value;
                }, $field_names_arr));

                $data_arr = $record_data_to_save;
            //}

            //print 'Check 3: '.(microtime(true) - $start_time).PHP_EOL;

//            } else {
//                // the first column of the main index is set (as well probably the ither is there are more) and then it doesnt matter is it autoincrement or not
//                $field_names_arr = array_unique(array_merge($ActiveRecord::get_property_names(), $main_index));
//                $field_names_str = implode(', ', $field_names_arr);
//                $placeholder_str = implode(', ', array_map(function ($value) {
//                    return ':'.$value;
//                }, $field_names_arr));
//                $data_arr = array_merge($record_data_to_save, $ActiveRecord->index);
//            }
            $Connection = $this->get_connection($CR);

            //print 'Check 4: '.(microtime(true) - $start_time).PHP_EOL;

            $data_arr = $ActiveRecord::fix_data_arr_empty_values_type($data_arr, $Connection::get_tprefix() . $ActiveRecord::get_main_table());

            //print 'Check 5: '.(microtime(true) - $start_time).PHP_EOL;


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
            $Statement = $Connection->prepare($q);

            //print 'Check 6: '.(microtime(true) - $start_time).PHP_EOL;

            $Statement->execute($data_arr);


            //print 'Check 7: '.(microtime(true) - $start_time).PHP_EOL;


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

            //print 'Check 8: '.(microtime(true) - $start_time).PHP_EOL;

        } else {

            $record_data_to_save = [];
            $field_names = $modified_field_names = $ActiveRecord::get_column_names();

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

                $data_arr = $ActiveRecord->fix_data_arr_empty_values_type($data_arr, $Connection::get_tprefix() . $ActiveRecord::get_main_table());

                foreach ($data_arr as $key => $value) {
                    $data_arr['insert_' . $key] = $value;
                    if (!in_array($key, array_values($main_index))) {
                        $data_arr['update_' . $key] = $value;
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

                $Statement = $Connection->prepare($q);

                $ret = $Statement->execute($data_arr);
            }
        }

        //print 'Check 9: '.(microtime(true) - $start_time).PHP_EOL;

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

        //print 'Check 10: '.(microtime(true) - $start_time).PHP_EOL;

        //$ret = array_merge($record_data, $this->get_meta());


        // TODO set uuid to $ActiveRecord

        //COMMIT DB TRANSACTION

        //$this->is_new = FALSE;
        //this flag will be updated in activerecord::save()
        //return $uuid;
        $ret = ['data' => $record_data, 'meta' => $ActiveRecord::uses_meta() ? $this->get_meta(get_class($ActiveRecord), $ActiveRecord->get_id()) : [] ];

        //print 'Check 11: '.(microtime(true) - $start_time).PHP_EOL;


        return $ret;
    }

    /**
     * @param string $class
     * @param array $index
     * @return array
     * @throws BadMethodCallException
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws RecordNotFoundException
     * @throws \ReflectionException
     */
    public function &get_data_pointer(string $class, array $index, bool $permission_checks_disabled = false): array
    {

        if (!is_a($class, ActiveRecordInterface::class, true)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided class "%1$s" does not implement %2$s.'), $class, ActiveRecordInterface::class));
        }
        if (!class_exists($class)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided class "%1$s" does not exist (or is not a class).'), $class));
        }

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
                throw new InvalidArgumentException(sprintf(t::_('Both sorting keys "<" and ">" are provided and both use the same column %1$s.'), $index['<']));
            }
        }
        $sort_by = null;
        $sort_desc = false;
        if (array_key_exists('<', $index)) {
            $sort_by = $index['<'];
            $sort_desc = false;
            unset($index['<']);
        }
        if (array_key_exists('>', $index)) {
            $sort_by = $index['>'];
            $sort_desc = true;
            unset($index['>']);
        }

//        $active_record_interface = $class::get_class_active_record_interface();
//        //if there is an active record interface for the provided class the lookup should be done by this interface
//        if ($active_record_interface) {
//            $class = $active_record_interface;
//        }

        //string $class, array $index, int $offset = 0, int $limit = 0, bool $use_like = FALSE, ?string $sort_by = NULL, bool $sort_desc = FALSE, ?int &$total_found_rows = NULL
        $data = $this->get_data_by($class, $index, 0, 0, false, $sort_by, $sort_desc, $total_rows, $permission_checks_disabled);




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
            $sturcture_data = $this->get_storage_columns_data($class);

            $column_names = array_map(fn($column_data) => $column_data['COLUMN_NAME'], $sturcture_data);
//            $column_names = [];
//            foreach ($structure_data as $structure_datum) {
//                $column_names
//            }
            foreach ($data[0] as $column_name => $column_datum) {
                if (in_array($column_name, $column_names)) {
                    $record_data[$column_name] = $column_datum;
                }
            }

            //append the own class properties
            $class_properties_data = $class::get_class_properties_data();
            foreach ($class_properties_data as $class_properties_datum) {
                if (!array_key_exists($class_properties_datum['name'], $record_data)) {
                    $record_data[$class_properties_datum['name']] = $class_properties_datum['default_value'];
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

    /**
     * @param string $class
     * @param array $primary_index
     * @return array
     * @throws BadMethodCallException
     * @throws InvalidArgumentException
     * @throws RecordNotFoundException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function &get_data_pointer_for_new_version(string $class, array $primary_index): array
    {
        $data = $this->get_data_pointer($class, $primary_index);
        return $data;
    }

    /**
     * @param string $class
     * @param array $primary_index
     * @return bool
     */
    public function there_is_pointer_for_new_version(string $class, array $primary_index): bool
    {
        //this store doesnt use pointers
        return false;
    }

    /**
     * @param ActiveRecordInterface $ActiveRecord
     */
    public function free_pointer(ActiveRecordInterface $ActiveRecord): void
    {
        //does nothing
    }

    public function debug_get_data(): array
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
     * @throws RunTimeException
     */
    public function remove_record(ActiveRecordInterface $ActiveRecord): void
    {
        $Connection = $this->get_connection($CR);
        $primary_index = $ActiveRecord->get_primary_index();
        $w = $b = [];
        foreach ($primary_index as $key => $value) {
            //$w_arr[] = "$key = '$value'";
            $w[] = "`$key` = :$key";
            $b[$key] = $value;
        }
        $w_str = implode(' AND ', $w);

        // Remove record data
        $q = "
DELETE
    FROM {$Connection::get_tprefix()}{$ActiveRecord::get_main_table()} 
WHERE
    {$w_str}
        ";

        $s = $Connection->prepare($q);
        $s->execute($b);

        // Remove meta data
        $meta_table = self::get_meta_table();
        $uuid = $ActiveRecord->get_uuid();
        $q = "
DELETE
    FROM {$Connection::get_tprefix()}{$meta_table} 
WHERE
    `meta_object_uuid` = :meta_object_uuid
";
        $b = ['meta_object_uuid' => $uuid];
        $s = $Connection->prepare($q);
        $s->execute($b);
    }

    /**
     * Returns all results matching criteria
     * @param string $class class name
     * @param array $index [$column => $value]
     * @param int $offset
     * @param int $limit
     * @param bool $use_like
     * @param string $sort_by
     * @param bool $sort_desc
     * @param int|null $total_found_rows
     * @return array dataset
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws BadMethodCallException
     * @throws \ReflectionException
     */
    public function get_data_by(
        string $class,
        array $index,
        int $offset = 0,
        int $limit = 0,
        bool $use_like = false,
        ?string $sort_by = null,
        bool $sort_desc = false,
        ?int &$total_found_rows = null,
        bool $permission_checks_disabled = false
    ): array
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
        //needs to be associative as we may join unwillingly multiple times the same table
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
            true => 'DESC',
            false => 'ASC',
        ];
        if ($sort_by !== null) {
            $sort_str = " ORDER BY " . $sort_by . " " . $sort_direction[$sort_desc];
        }

        //we need to always join all the main tables
        //otherwise the loaded object will be mising properties
        //but these can be loaded on request
        //the vertical sharding is currently not supported

        $table_name = $class::get_main_table();
        //the main table must be always loaded
        //$j[$class::get_main_table()] = $Connection::get_tprefix().$class::get_main_table();//if it gets assigned multiple times it will overwrite it
        //as it may happen the WHERE index provided to get_instance to be from other shards
        //if($this->is_ownership_table($table_name)){
        //}

        
        $main_index = $class::get_primary_index_columns();
        //$index = [$main_index[0] => $index];


        /**
         * If UUID is provided the meta data is searched to find the primary key in order
         * to perform the SELECT operation and ignores the rest of the $index keys
         */
        if (array_key_exists('meta_object_uuid', $index)) {
            if (GeneralUtil::is_uuid((string) $index['meta_object_uuid'])) {
                $meta_data = $this->get_meta_by_uuid($index['meta_object_uuid']);
                $object_id = $meta_data['meta_object_id'];
                //$w[] = $main_index[0] . ' = :object_id';
                $w[] = "main_table.{$main_index[0]} = :object_id";
                $b['object_id'] = $object_id;
            } else {
                //do a like search
                $w[] = 'meta_object_uuid LIKE :meta_object_uuid';
                if ($use_like) {
                    $b['meta_object_uuid'] = '%' . $index['meta_object_uuid'] . '%';
                } else {
                    //$b['meta_object_uuid'] = $index['meta_object_uuid'];//this is pointless - it is an error
                    //either provide an exact UUID or $use_like = TRUE
                    throw new InvalidArgumentException(sprintf(t::_('An invalid/partial UUID "%s" provided and the $use_like argument is set to FALSE.'), $index['meta_object_uuid']));
                }
            }
        } else {
            //an implementation detail of MySQL store is to replace class_name lookups to by class_id for performance reasons
            if (array_key_exists('class_name', $index) && $class::has_property('class_id')) {
                $index['class_id'] = $this->get_class_id($index['class_name']);
                unset($index['class_name']);
            }
            foreach ($index as $field_name => $field_value) {
                if (!is_string($field_name)) {
                    //perhaps get_instance was provided like this [1,2] instead of ['col1'=>1, 'col2'=>2]... The first notation may get supported in future by inspecting the columns and assume the order in which the primary index is provided to be correct and match it
                    throw new RunTimeException(sprintf(t::_('It seems wrong values were provided to object instance. The provided array must contain keys with the column names and values instead of just values. Please use new %s([\'col1\'=>1, \'col2\'=>2]) instead of new %s([1,2]).'), $class, $class, $class));
                }

                if ($field_name !== 'meta_object_uuid') {
                    if (!array_key_exists($field_name, $record_data)) {
                        throw new RunTimeException(sprintf(t::_('A field named "%s" that does not exist is supplied to the constructor of an object of class "%s".'), $field_name, $class));
                    }
                }
                if (is_null($field_value)) {
                    $w[] = "main_table.{$field_name} {$Connection::equals($field_value)} NULL";
                } else {
                    $w[] = "main_table.{$field_name} {$Connection::equals($field_value, $use_like)} :{$field_name}";
                    if ($use_like) {
                        $b[$field_name] = "%" . $field_value . "%";
                    } else {
                        $b[$field_name] = $field_value;
                    }
                }
            } //end foreach

            if (empty($w)) {
                $w[] = "1";
            }
        }
        $full_main_table_name = $Connection::get_tprefix() . $table_name;
        $roles_table = $Connection::get_tprefix() . Role::get_main_table();
        $from_str = "`$full_main_table_name` AS main_table";


        $w_str = implode(" AND ", $w);

        $select_str = "
            main_table.*
        ";


        // JOIN meta data
        $meta_table = $Connection::get_tprefix() . $this::get_meta_table();
        $class_table = $Connection::get_tprefix() . $this::get_class_table();
        // -- meta.meta_class_name = :meta_class_name
        if ($class::uses_meta()) {
            $select_str .= "
            , meta.meta_object_uuid
            , meta.meta_class_id
            , meta.meta_object_id
            , meta.meta_object_create_microtime
            , meta.meta_object_last_update_microtime
            , meta.meta_object_create_transaction_id
            , meta.meta_object_last_update_transaction_id
            ";
            $select_str .= "
            , create_role.role_name AS meta_create_role_name
            , last_update_role.role_name AS meta_last_update_role_name
            ";

            $from_str .= " 
INNER JOIN 
    `{$meta_table}` as `meta` 
    ON 
        meta.meta_object_id = main_table.{$main_index[0]} 
    AND
        meta.meta_class_id = :meta_class_id
INNER JOIN
    `{$roles_table}` as `create_role`
    ON
        create_role.role_id = meta.meta_object_create_role_id
INNER JOIN
    `{$roles_table}` as `last_update_role`
    ON
        last_update_role.role_id = meta.meta_object_last_update_role_id  
";
            //$b['meta_class_name'] = $class;
            $b['meta_class_id'] = $this->get_class_id($class);
        }
        //it is not a good idea to join the aliases
        //if there is more than one alias this will multiply the result... GROUP_CONCAT() will be needed along with GROUP_BY
        //also will require additional dimension to represent all aliases
//        $from_str .= "
//LEFT JOIN
//    `{$object_aliases_table}` as `aliases`
//    ON
//        aliases.object_alias_class_id = meta.meta_class_id
//    AND
//        aliases.object_alias_object_id = meta.meta_object_id
//        ";

//no need of GROUP BY main_table.{$main_index[0]}
        $q_data = "
SELECT
    {$select_str}
FROM
    {$from_str}
WHERE
    {$w_str}
    
    {$sort_str}
    {$l_str}
";

        //no need of GROUP BY main_table.{$main_index[0]}
        $q_count = "
SELECT
    COUNT(*) as total_found_rows
FROM
    {$from_str}
WHERE
    {$w_str}

";

//        $b_data = $b;
//        $b_count = $b;
//        unset($b);
//        //if ($class::uses_permissions()) {
//        if ($class::uses_permissions() && !$permission_checks_disabled) {
//            /** @var AuthorizationProviderInterface $AuthorizationProvider */
//            $AuthorizationProvider = self::get_service('AuthorizationProvider');
////            $permission_sql = $AuthorizationProvider::get_sql_permission_check($class);
////            if ($permission_sql) { //some providers do not return SQL
////                $w[] = "main_table.{$main_index[0]} = (".$permission_sql.")";
////            }
//            $AuthorizationProvider->add_sql_permission_checks($q_data, $b_data);
//            $AuthorizationProvider->add_sql_permission_checks($q_count, $b_count);
//        }

        //to avoid recursion in the services initialization get the AuthorizationProvider only if the class is using permissions
        if ($class::uses_permissions() && !$permission_checks_disabled) {
            /** @var AuthorizationProviderInterface $AuthorizationProvider */
            $AuthorizationProvider = self::get_service('AuthorizationProvider');
            $AuthorizationProvider->add_sql_permission_checks($q_data, $b, 'read', $Connection);
            $AuthorizationProvider->add_sql_permission_checks($q_count, $b, 'read', $Connection);
        }

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

        $class_uuid = $this->get_class_uuid($class);
        $add_class_name = $class::has_property('class_name') && $class::has_property('class_id');
        foreach ($data as &$record) {
            $record['meta_class_name'] = $class;
            $record['meta_class_uuid'] = $class_uuid;
            if ($add_class_name && array_key_exists('class_id', $record) && !array_key_exists('class_name', $record)) {
                $record['class_name'] = $this->get_class_name($record['class_id']);
            }
        }

        foreach ($record_data as $init_key=>$init_value) {
            $type = gettype($init_value);
            foreach ($data as &$_row) {
                foreach ($_row as $key=>$value) {
                    if ($key === $init_key && !is_null($_row[$key])) { //if the column is nullable and the value is null - leave the value null and do not cast
                        settype($_row[$key], $type);
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @return int
     */
    public function get_hits(): int
    {
        return $this->hits;
    }

    /**
     * @return int
     */
    public function get_misses(): int
    {
        return $this->misses;
    }

    /**
     * @return float
     */
    public function get_hits_percentage(): float
    {
        $ret = 0.0;
        $hits = $this->get_hits();
        $misses = $this->get_misses();
        $total = $hits + $misses;

        if (0 != $total) {
            $ret = (float) ($hits / $total * 100.0);
        }

        return $ret;
    }

    /**
     *
     */
    public function reset_hits(): void
    {
        $this->hits = 0;
    }

    /**
     *
     */
    public function reset_misses(): void
    {
        $this->misses = 0;
    }

    /**
     *
     */
    public function reset_stats(): void
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
