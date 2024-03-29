<?php

declare(strict_types=1);

namespace Guzaba2\Orm;

use Azonmedia\Apm\Interfaces\ProfilerInterface;
use Azonmedia\Lock\Interfaces\LockInterface;
use Azonmedia\Lock\Interfaces\LockManagerInterface;
use Azonmedia\Reflection\ReflectionClass;
use Azonmedia\Utilities\ArrayUtil;
use Azonmedia\Utilities\GeneralUtil;
use Azonmedia\Http\Body\Structured;
use Azonmedia\Http\Method;
use Guzaba2\Authorization\CurrentUser;
use Guzaba2\Authorization\Interfaces\AuthorizationProviderInterface;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Traits\StaticStore;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Coroutine\Exceptions\ContextDestroyedException;
use Guzaba2\Kernel\Exceptions\ConfigurationException;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Log\LogEntry;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use Guzaba2\Orm\Interfaces\ActiveRecordTemporalInterface;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\MetaStore\MetaStore;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Orm\Store\Interfaces\StructuredStoreInterface;
use Guzaba2\Orm\Store\Memory;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Orm\Store\MemoryTransaction;
use Guzaba2\Orm\Store\Sql\Mysql;
use Guzaba2\Orm\Store\Store;
use Guzaba2\Orm\Traits\ActiveRecordAlias;
use Guzaba2\Orm\Traits\ActiveRecordAuthorization;
use Guzaba2\Orm\Traits\ActiveRecordLog;
use Guzaba2\Orm\Traits\ActiveRecordMeta;
use Guzaba2\Orm\Traits\ActiveRecordTemporal;
use Guzaba2\Orm\Traits\ActiveRecordHooks;
use Guzaba2\Transaction\Interfaces\TransactionInterface;
use Guzaba2\Transaction\Interfaces\TransactionManagerInterface;
use Guzaba2\Transaction\ScopeReference;
use Guzaba2\Transaction\Transaction;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Event\Event;
use Guzaba2\Orm\Traits\ActiveRecordOverloading;
use Guzaba2\Orm\Traits\ActiveRecordStructure;
use Guzaba2\Orm\Traits\ActiveRecordIterator;
use Guzaba2\Orm\Traits\ActiveRecordValidation;
use GuzabaPlatform\Navigation\Models\NavigationLink;
use ReflectionException;

/**
 * Class ActiveRecord
 * @package Guzaba2\Orm
 * @method Store OrmStore()
 * @method LockManagerInterface LockManager()
 *
 * @property string meta_object_uuid
 * @property int    meta_class_id
 * @property int    meta_object_id
 * @property int    meta_object_create_microtime
 * @property int    meta_object_last_update_microtime
 * @property int    meta_object_create_role_id
 * @property int    meta_object_last_update_role_id
 */
class ActiveRecord extends Base implements ActiveRecordInterface, \JsonSerializable, \Iterator
    //, \ArrayAccess, \Countable
{
    //for the purpose of splitting and organising the methods (as this class would become too big) traits are used
    use ActiveRecordAlias;
    use ActiveRecordOverloading;
    use ActiveRecordStructure;
    use ActiveRecordIterator;
    use ActiveRecordValidation;
    use ActiveRecordHooks;
    use ActiveRecordAuthorization;
    use ActiveRecordTemporal;
    use ActiveRecordLog;
    use ActiveRecordMeta;

    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'OrmStore',
            'MysqlOrmStore',//needed only for get_class_id()
            'LockManager',
            'Events',
            'AuthorizationProvider',
            'CurrentUser',
            'TransactionManager',
            'Apm',
        ],
        //only for non-sql stores
        'structure' => [

        ],
        'temporal_table_suffix'  => '_temporal',
        'temporal_class_suffix'  => 'Temporal',

        /**
         * Casts the following:
         * - strign values which are numeric to int or float if the column is int or float
         * - bool values to int is the column is int (this is probably a BOOL column which is INT size 1 - this is how MySQL defines a BOOL column - no real bool type)
         */
        'cast_properties_on_assignment'         => true,

        'log_notice_on_property_cast'           => false,
        'throw_exception_on_property_cast'      => false,
        'add_validation_error_on_property_cast' => false,

        'profile_classes'       => [
            //\GuzabaPlatform\Settings\Models\Setting::class,
            //\Guzaba2\Authorization\Acl\Permission::class,
        ],

    ];

    protected const CONFIG_RUNTIME = [];


    //protected const CAST_PROPERTIES_ON_ASSIGNMENT = TRUE;

    protected const STANDARD_ACTIONS = [
        'create',
        'read',
        'write',
        'delete',
        'grant_permission',
        'revoke_permission',
        'change_owner',
    ];


    /**
     * @var StoreInterface
     */
    protected StoreInterface $Store;

    /**
     * @var bool
     */
    protected bool $is_new_flag = true;

    /**
     * @var bool
     */
    protected bool $was_new_flag = false;

    /**
     * @var array
     */
    protected array $record_data = [];

    /**
     * @var array
     */
    protected array $record_modified_data = [];

    /**
     * @var array
     */
    protected array $meta_data = [];
    
    /**
     * @var bool
     */
    protected bool $disable_property_hooks_flag = false;
    
    /**
     * Are the method hooks like _before_save enabled or not.
     * Supports nesting.
     * @see activeRecord::disable_method_hooks()
     * @see activeRecord::enable_method_hooks()
     *
     * @var bool
     */
    protected int $disable_method_hooks = 0;

    /**
     * @var bool
     */
    private bool $read_lock_obtained_flag = false;


    /**
     * To store what was initially provided as $index to the constructor.
     * Will be returned by get_id() when the record_data is not yet populated.
     * This may happen when DetaultCurrentUser is set to non 0.
     * @var scalar
     */
    private /* scalar */ $requested_index = self::INDEX_NEW;

    /**
     * @var bool
     */
    private bool $read_only_flag = false;

    /**
     * Can be set to TRUE if the $permission_checks_disabled flag is passed to the constructor
     * @var bool
     */
    private bool $permission_checks_disabled_flag = false;

    /**
     * Supports nested calls
     * @var int
     */
    private int $modified_data_tracking_disabled = 0;


    /**
     * Contains the unified record structure for this class.
     * @see StoreInterface::UNIFIED_COLUMNS_STRUCTURE
     * While in Swoole/coroutine context static variables shouldnt be used here it is acceptable as this structure is not expected to change ever during runtime once it is assigned.
     * @var array
     */
    protected static array $columns_data = [];

    /**
     * Contains properties of the class in the unified format as per the structure.
     * @see StoreInterface::UNIFIED_COLUMNS_STRUCTURE
     * While in Swoole/coroutine context static variables shouldnt be used here it is acceptable as this structure is not expected to change ever during runtime once it is assigned.
     * @var array
     */
    protected static array $properties_data = [];

    /**
     * Contains the unified record structure for this class meta data (ownership data).
     * @see StoreInterface::UNIFIED_COLUMNS_STRUCTURE
     * While in Swoole/coroutine context static variables shouldnt be used here it is acceptable as this structure is not expected to change ever during runtime once it is assigned.
     * @var array
     */
    protected static array $meta_columns_data = [];

    /**
     * Contains an indexed array with the name of the primary key columns. Usually it is one but can be more.
     * @var array
     */
    protected static array $primary_index_columns = [];

    /**
     * Should locking be used when creating (read lock) and saving (write lock) objects.
     * It can be set to FALSE if the REST method used doesnt imply writing.
     * This property is to be used only in NON-coroutine mode.
     * In coroutine mode the Context is to be used.
     * @see self::is_locking_enabled()
     * @var bool
     */
    protected static bool $orm_locking_enabled_flag = true;


    /**
     * Detect is the current execution already in write().
     * It is possible to call write() from _before & _after_write().
     * Only the public properties are considered data properties for saving them in the ORM Store.
     * @var int
     */
    protected int $nested_write_counter = 0;


    /**
     * ActiveRecord constructor.
     * @param int $index
     * @param bool $read_only
     * @param bool $permission_checks_disabled
     * @param StoreInterface|null $Store
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws ConfigurationException
     * @throws ReflectionException
     * @throws ContextDestroyedException
     */
    //public function __construct(/* mixed*/ $index = self::INDEX_NEW, ?StoreInterface $Store = NULL)
    public function __construct(/* mixed*/ $index = self::INDEX_NEW, bool $read_only = false, bool $permission_checks_disabled = false, ?StoreInterface $Store = null)
    //public function __construct(int|string|array $index = self::INDEX_NEW, bool $read_only = false, bool $permission_checks_disabled = false, ?StoreInterface $Store = null)
    {
        parent::__construct();

        if (!isset(static::CONFIG_RUNTIME['main_table'])) {
            throw new RunTimeException(sprintf(t::_('ActiveRecord class %s does not have "main_table" entry in its CONFIG_RUNTIME.'), get_called_class()));
        }

        //$this->read_lock_obtained_flag = $read_only;??? //TODO - check why this was here
        $this->set_read_only($read_only);
        $this->permission_checks_disabled_flag = $permission_checks_disabled;

//        if (Coroutine::inCoroutine()) {
//            $Request = Coroutine::getRequest();
//            if ($Request && Method::get_method_constant($Request) === Method::HTTP_GET) {
//                $this->set_read_only(true);
//            }
//        }

        //$this->locking_enabled_flag = self::is_locking_enabled();
        
        //if ($this->index == self::INDEX_NEW) { //checks is the index already set (as it may be set in _before_construct()) - if not set it
        //    $this->index = $index;
        //}

        if ($Store) {
            $this->Store = $Store;
        } else {
            //$this->Store = static::OrmStore();//use the default service
            /** @var StoreInterface $Store */
            $Store = static::get_service('OrmStore');
            $this->Store = $Store;
        }

        $this->requested_index = $index;


        //unset all properties of the object (only the child class properties)
        //the property info has already been collected by initialize_structure()
//        $class_properties = static::get_class_property_names();
//        foreach ($class_properties as $class_property_name) {
//            unset($this->{$class_property_name});
//        }
//        $class_properties = static::get_property_names_with_types();
//        foreach ($class_properties as $class_property_name => $class_property_data) {
//            unset($this->{$class_property_name});
//            if ($class_property_data['nullable']) {
//                $this->{$class_property_name} = null;
//            }
//        }
        $class_properties_data = static::get_class_properties_data();
        foreach ($class_properties_data as $class_property_name => $class_property_data) {
            unset($this->{$class_property_name});
//            if ($class_property_data['nullable']) {
//                $this->{$class_property_name} = null;
//            }
        }

        $primary_columns = static::get_primary_index_columns();

        $called_class = get_class($this);
        // 1. check is there main index loaded
        // if $this->index is still empty this means that this table has no primary index
        if (!count($primary_columns)) {
            throw new ConfigurationException(sprintf(t::_('The class %s has no primary index defined.'), $called_class));
        }

        if (is_scalar($index)) {
            if (ctype_digit((string)$index)) {
                $index = (int)$index;
                // if the primary index is compound and the provided $index is a scalar throw an error - this could be a mistake by the developer not knowing that the primary index is compound and providing only one component
                // providing only one component for the primary index is still supported but needs to be provided as array
                if (count($primary_columns) === 1) {
                    $index = [$primary_columns[0] => $index];
                } else {
                    $message = sprintf(t::_('The class "%s" with primary table "%s" has a compound primary index consisting of "%s". Only a single scalar value "%s" was provided to the constructor which could be an error. For classes that use compound primary indexes please always provide arrays. If needed it is allowed the provided array to have less keys than components of the primary key.'), $called_class, static::get_main_table(), implode(', ', $primary_columns), $index);
                    throw new InvalidArgumentException($message);
                }
            } elseif (GeneralUtil::is_uuid((string) $index)) {
                //this is UUID
                $index = ['meta_object_uuid' => $index];
            } elseif (is_string($index)) {
                //try to do a lookup by object alias
                try {
                    $ObjectAlias = new ObjectAlias(['object_alias_class_id' => static::get_class_id(), 'object_alias_name' => $index]);
                    //$index = $ObjectAlias->object_alias_object_id;
                    $index = [$primary_columns[0] => $ObjectAlias->object_alias_object_id];
                    //and proceed loading the object (it should already be cached as ObjectAlias creates the target object in order to check the permissions
                } catch (RecordNotFoundException $Exception) {
                    //there is no such alias (at least not for an object of this class
                    throw new RunTimeException(sprintf(t::_('An unsupported type "%s" with value "%s" was supplied for the index of object of class "%s".'), gettype($index), $index, get_class($this)));
                } //SECURITY - leave the PermissionDeniedException to pop... it will expose that such an object does exist but is consistent with the rest of the logic
            } else {
                throw new RunTimeException(sprintf(t::_('An unsupported type "%s" with value "%s" was supplied for the index of object of class "%s".'), gettype($index), $index, get_class($this)));
            }
        } elseif (is_array($index)) {
            // no check for count($this->index)==count(self::$primary_index_columns) as an array with some criteria may be supplied instead of index
            // no change
            //moved to get_data_by in the store
//            if (count($index) === 1 && array_key_exists('meta_object_uuid', $index) ) {
//                if (!GeneralUtil::is_uuid( (string) $index['meta_object_uuid'] )) {
//                    throw new InvalidArgumentException(sprintf(t::_('An invalid value "%s" (not an UUID) for the meta_object_uuid key in the index is provided for object of class "%s".'), $index['meta_object_uuid'], get_class($this) ));
//                }
//            }
        } else {
            throw new RunTimeException(sprintf(t::_('An unsupported type "%s" was supplied for the index of object of class "%s".'), gettype($index), get_class($this)));
        }



        if (isset($index[$primary_columns[0]]) && $index[$primary_columns[0]] === self::INDEX_NEW) {
            //$this->record_data = Store::get_record_structure(static::get_columns_data());
            $this->record_data = Store::get_record_structure(static::get_properties_data());
        //the new records are not referencing the OrmStore
            //no locking here either
        } else {
            $this->read($index);
        }
    }

    public function __debugInfo() {
        return $this->record_data;
    }

    /**
     * Returns an instance filled with the provided data (it should also inject it in the Memory store)
     * @param iterable $data
     * @return ActiveRecordInterface
     */
    public static function get_from_record(iterable $data, bool $execute_after_read = true, bool $set_meta = true): ActiveRecordInterface
    {
        $class = get_called_class();
        $Object = new $class(0);
        $Object->disable_modified_data_tracking();
        $class_properties = $class::get_class_property_names();
        foreach ($class::get_property_names() as $property) {
            if (!array_key_exists($property, $data)) {

                if (!in_array($property, $class_properties)) {
                    throw new RunTimeException(sprintf(t::_('The property %1$s needed by class %2$s does not exist in the provided data.'), $property, $class));
                } else {
                    //it will be set in _after_read()
                    continue;
                }
            }

            //$Object->{$property} = $data[$property];//use this as it invokes the property hooks
            //or call __set() directly for better speed
            $Object->__set($property, $data[$property]);
            //$Object->record_data[$property] = $data[$property];
        }

        if ($set_meta) {
            foreach ($class::get_meta_property_names() as $property) {
                //TODO - fix this exception for mysql
                if ($property === 'meta_object_uuid_binary') {
                    continue;
                }
                if (!array_key_exists($property, $data)) {
                    throw new RunTimeException(sprintf(t::_('The meta property %1$s needed by class %2$s does not exist in the provided data.'), $property, $class));
                }
                //can not use the overloading as the meta is read only
                $Object->meta_data[$property] = $data[$property];
            }
        }
        $Object->is_new_flag = false;
        if (method_exists($Object, '_after_read') && $execute_after_read) {
            //this will set the values of the class property names
            $Object->_after_read();
        }
        $Object->enable_modified_data_tracking();
        return $Object;
    }

    protected function _before_destruct()
    {


//        if (!$this->is_new() && count($this->record_data)) { //count($this->record_data) means is not deleted
//            $this->Store->free_pointer($this);
//        }

        if (!$this->is_new()) {

            //even if it is deleted still try to free the pointer
            $this->Store->free_pointer($this);
        }

        /*
        if (self::is_locking_enabled() && !$this->is_new()) {

            if ($this->read_lock_obtained_flag) { //this is needed for the new records.. at this point they are no longer new if successfully saved
                $resource = MetaStore::get_key_by_object($this);
                static::get_service('LockManager')->release_lock($resource);

                $this->read_lock_obtained_flag = FALSE;
            }

        }
        */
        if ($this->read_lock_obtained_flag) { //this is needed for the new records.. at this point they are no longer new if successfully saved
            $resource = MetaStore::get_key_by_object($this);
            static::get_service('LockManager')->release_lock($resource);

            $this->read_lock_obtained_flag = false;
        }
    }

    public function __toString(): string
    {
        //return MetaStore::get_key_by_object($this);
        //return $this->as_array();
        return json_encode($this->as_array(), Structured::getJsonFlags());
    }

    /**
     * @implements \jsonSerializable
     * @return mixed|void
     */
    public function jsonSerialize()
    {
        return $this->as_array();
    }

    public static function get_temporal_class(): string
    {
        return get_called_class() . self::CONFIG_RUNTIME['temporal_class_suffix'];
    }

    /**
     * Returns all the object data (class onw properties + database columns (overloaded properties)) and the object meta data combined in a one dimensional associative array.
     * Please note that class properties may be arrays, thus making the resulting array multidimensional!
     * @return array
     */
    public function as_array(): array
    {
        //return ['data' => $this->get_property_data(), 'meta' => $this->get_meta_data()];
        return array_merge($this->get_property_data(), $this->get_meta_data());
    }

    /**
     * @param int|string|array $index
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws ContextDestroyedException
     */
    public function read(/* int|string|array */ $index): void
    {

        if (!is_string($index) && !is_int($index) && !is_array($index)) {
            throw new InvalidArgumentException(sprintf(t::_('The $index argument of %s() must be int, string or array. %s provided instead.'), __METHOD__, gettype($index)));
        }

//        if (static::uses_service('AuthorizationProvider') && static::uses_permissions() && !$this->are_permission_checks_disabled() ) {
//            $this->check_permission('read');
//        }

        if (method_exists($this, '_before_read') && !$this->are_method_hooks_disabled() && !($this instanceof ActiveRecordTemporalInterface) ) {
            $args = func_get_args();
            call_user_func_array([$this,'_before_read'], $args);//must return void
        }

        //new Event($this, '_before_read');
        self::get_service('Events')::create_event($this, '_before_read');


        //before the pointer is obtained check is the provided index the primary one
        //if it is the primary it is OK to proceed
        //if it is not the record must be first resolved and only then to proceed
        //The permission checks are disabled here in order to retrieve the object
        //if there is no permission and the permission checks are enabled a RecordNotFoundException will be thrown
        //instead of the expected PermissionDeniedException
        //TODO - add a config option that allows the PermissionDenied to be replaced with RecordNotFound for added security
        $properties = self::get_property_names();
        foreach (array_keys($index) as $index_property) {
            if ($index_property === 'meta_object_uuid') {
                continue;
            }
            if ($index_property === '<' || $index_property === '>') {
                continue;
            }
            if (!in_array($index_property, $properties)) {
                throw new InvalidArgumentException(sprintf(t::_('An invalid property named %1$s was provided to the constructor of %2$s.'), $index_property, get_class($this) ));
            }
        }
        $primary_columns = static::get_primary_index_columns();
        if (array_keys($index) === array_keys($primary_columns)) {
            //it is OK to proceed to the pointer lookup
        } else {

            //need to resolve the primary index and only then to proceed
            //$pointer =& $this->Store->get_data_pointer(get_class($this), $index, $this->are_permission_checks_disabled());
            $_pointer =& $this->Store->get_data_pointer(get_class($this), $index, $permission_checks_disabled = true);//disable permission checks - just retrieve the record and make a permission check after that
            $index = self::get_index_from_data($_pointer['data']);
            //the whole purpose of this was to obtain the correct primary index
            unset($_pointer);
            $free_data_pointer_obtained_during_index_lookup = true;
        }

        if ($this->Store->there_is_pointer_for_new_version(get_class($this), $index)) {
            $_pointer =& $this->Store->get_data_pointer_for_new_version(get_class($this), $index);
            $this->record_data =& $_pointer['data'];
            $this->meta_data =& $_pointer['meta'];
            if (!array_key_exists('modified', $_pointer)) {
                throw new LogicException(sprintf(t::_('The pointer for the new version for an instance of class %1$s with primary index %2$s does not have key "modified".'), get_class($this), implode(':', $index) ));
            }
            $this->record_modified_data =& $_pointer['modified'];
        } else {
            //$pointer =& $this->Store->get_data_pointer(get_class($this), $index, $this->are_permission_checks_disabled());
            $_pointer =& $this->Store->get_data_pointer(get_class($this), $index, $permission_checks_disabled = true);//disable permission checks - just retrieve the record and make a permission check after that
            $this->record_data =& $_pointer['data'];
            $this->meta_data =& $_pointer['meta'];
            if (array_key_exists('was_new_flag', $_pointer)) { //the was_new_flag needs to be preserved across all objects in the stack
                $this->was_new_flag =& $_pointer['was_new_flag'];
            }
            $this->record_modified_data = [];
        }

        if (!empty($free_data_pointer_obtained_during_index_lookup)) {
            $this->Store->free_pointer($this);//if not freed it will result in refcount = 1, instead of 1 for the objects obtained not with their primary index
        }

        //CLASS_PROPERTIES
        //the $record_data needs to be "enriched" with the properties data
        //the properties first need to be defined in the record_data array (if they dont exist)
        //then if the class has _after_read hook it will populate these (usually these properties are dependant on the core properties stored in the Store (get_columns_data())
        $class_properties_data = static::get_class_properties_data();
        foreach ($class_properties_data as $class_properties_datum) {
            if (!array_key_exists($class_properties_datum['name'], $this->record_data)) {
                $this->record_data[$class_properties_datum['name']] = $class_properties_datum['default_value'];
            }
        }



        //check the permissions now, not before the record is found as the provided index may be a an array and then the permissions lookup will fail
        //though at this stage it may be too late as the permission check in the backend storage will not return results if there are no permissions
        //TODO - best will be in DEV env to return permission denied error and in production to return RecordNotFound
        $this->check_permission('read');

        if (!count($this->meta_data)) {
            throw new LogicException(sprintf(t::_('No metadata is found/loaded for object of class %s with ID %s.'), get_class($this), print_r($index, true)));
        }

        //TODO - check this should be moved before the pointer is obtained
        if (static::is_locking_enabled() && !$this->is_read_only()) {
            //if ($this->locking_enabled_flag) {
            $resource = MetaStore::get_key_by_object($this);
            $LR = '&';//this means that no scope reference will be used. This is because the lock will be released in another method/scope.
            //self::LockManager()->acquire_lock($resource, LockInterface::READ_LOCK, $LR);
            static::get_service('LockManager')->acquire_lock($resource, LockInterface::READ_LOCK, $LR);

            $this->read_lock_obtained_flag = true;
        }

        $this->is_new_flag = false;

        $this->disable_modified_data_tracking();

        //new Event($this, '_after_read');
        self::get_service('Events')::create_event($this, '_after_read');

        //_after_load() event
        if (method_exists($this, '_after_read') && !$this->are_method_hooks_disabled() && !($this instanceof ActiveRecordTemporalInterface) ) {
            $args = func_get_args();
            call_user_func_array([$this,'_after_read'], $args);//must return void
        }

        $this->enable_modified_data_tracking();
    }

    private function profile(string $checkpoint_name, float $time): void
    {
        if (in_array(get_class($this), self::CONFIG_RUNTIME['profile_classes'])) {
            $message = sprintf(
                t::_('%1$s: Write profiling for %2$s %3$s, checkoint %4$s: %5$s.'),
                __CLASS__,
                get_class($this),
                implode(':', $this->get_primary_index()),
                $checkpoint_name,
                $time
            );
            Kernel::log($message);
        }
    }

    /**
     * write()s can be nested (for example invoking write() from _after_write()).
     * This returns the nesting level.
     * @return int
     */
    public function get_write_nesting(): int
    {
        return $this->nested_write_counter;
    }

    /**
     * @param bool $force_write Will do a write even if there are no modifications to the object
     * @return ActiveRecordInterface
     * @throws Exceptions\MultipleValidationFailedException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws ContextDestroyedException
     */
    public function write(bool $force_write = false, bool $disable_validation = false, bool $permission_checks_disabled = false): ActiveRecordInterface
    {

        $this->nested_write_counter++;
        try {

            $start_time = microtime(true);

            //instead of setting the BypassAuthorizationProvider to bypass the authorization
            //it is possible not to set AuthorizationProvider at all (as this will save a lot of function calls
            if ($this->nested_write_counter === 1) { //check the permissions only onf the first (outermost) call
                if ($this->is_new()) {
                    //$this->check_permission('create');
                    $this->check_class_permission('create');
                } else {
                    $this->check_permission('write');
                }
            }

            $this->profile('CHECK 1', microtime(true) - $start_time);

            if ($this->is_read_only()) {
                throw new RunTimeException(sprintf(t::_('Trying to write/save a read-only instance of class %s with id %s.'), get_class($this), $this->get_id()));
            }

    //read_only is set in constructor() if method is GET
    //        if (Coroutine::inCoroutine()) {
    //            $Request = Coroutine::getRequest();
    //            if ($Request->getMethodConstant() === Method::HTTP_GET) {
    //                throw new RunTimeException(sprintf(t::_('Trying to save object of class %s with id %s in GET request.'), get_class($this), $this->get_id()));
    //            }
    //        }

            if (!count($this->get_modified_properties_names()) && !$this->is_new() && !$force_write) {
                return $this;
            }

            $this->profile('CHECK 2', microtime(true) - $start_time);

            //TODO - it is not correct to release the lock and acquire it again - someone may obtain it in the mean time
            //instead the lock level should be updated (lock reacquired)
            if ($this->is_new() && static::is_locking_enabled()) {
                $resource = MetaStore::get_key_by_object($this);
    //            $LR = '&';//this means that no scope reference will be used. This is because the lock will be released in another method/scope.
    //            //self::LockManager()->acquire_lock($resource, LockInterface::READ_LOCK, $LR);
    //            static::get_service('LockManager')->acquire_lock($resource, LockInterface::READ_LOCK, $LR);
    //            unset($LR);
                /** @var LockManagerInterface $LockManager */
                $LockManager = static::get_service('LockManager');
                $LockManager->acquire_lock($resource, LockInterface::WRITE_LOCK, $LR);
            }


            $this->profile('CHECK 3', microtime(true) - $start_time);

            $Transaction = ActiveRecord::new_transaction($TR);
            $Transaction->begin();

            if (method_exists($this, '_before_write') && !$this->are_method_hooks_disabled() && !($this instanceof ActiveRecordTemporalInterface) ) {
                $args = func_get_args();
                call_user_func_array([$this,'_before_write'], $args);//must return void
            }

            $this->profile('CHECK 4', microtime(true) - $start_time);

            //new Event($this, '_before_write');
            self::get_service('Events')::create_event($this, '_before_write');


            $this->profile('CHECK 5', microtime(true) - $start_time);

            if (!$disable_validation) {
                $this->validate();
            }

            $this->profile('CHECK 6', microtime(true) - $start_time);

            static::get_service('OrmStore')->update_record($this);

            $this->profile('CHECK 7', microtime(true) - $start_time);


            //reattach the pointer
            //$_pointer =& $this->Store->get_data_pointer(get_class($this), $this->get_primary_index());
            //if get_data_pointer() is used this will return wrong data as the data is not yet stored in the main storage of Memory
            //until the transaction is committed
            //so until committed use get_data_pointer_for_new_version
            $_pointer =& $this->Store->get_data_pointer_for_new_version(get_class($this), $this->get_primary_index());

            //not needed
    //        //CLASS_PROPERTIES - the returned data is as it is found in the store
    //        //it needs to be enriched with the current properties

    //        foreach (self::get_class_property_names() as $class_property_name) {
    ////            if (!array_key_exists($class_property_name, $pointer['data'])) {
    //                $pointer['data'][$class_property_name] = $this->record_data[$class_property_name];
    ////            }
    //        }

            $this->record_data =& $_pointer['data'];
            $this->meta_data =& $_pointer['meta'];
            $_pointer['was_new_flag'] =& $this->was_new_flag;
            //lets clear this after the write() is committed
            //this way it will be available also in _after_write
            //$this->record_modified_data = [];


            //setting the flag to FALSE means that the record has UUID & ID assigned
            //the record is not yet commited
            if ($this->is_new_flag) {
                $this->is_new_flag = false;
                $this->was_new_flag = true;
            }

            $this->profile('CHECK 8', microtime(true) - $start_time);

            //if (! ($this instanceof LogEntry)) {
            if (self::uses_log()) {
                if ($this->was_new()) {
                    $this->add_log_entry('create', sprintf(t::_('A new record with ID %1$s and UUID %2$s is created.'), $this->get_id(), $this->get_uuid()));
                } else {
                    $this->add_log_entry('write', sprintf(t::_('The record was modified with the following properties being updated %1$s.'), implode(', ', $this->get_modified_properties_names())));
                }
            }

            $this->profile('CHECK 9', microtime(true) - $start_time);

            if (!$permission_checks_disabled) {
                if ($this->was_new() && self::uses_permissions() && $this->nested_write_counter === 1) { //create the permissions only if this is the first call
                    /** @var AuthorizationProviderInterface $AuthorizationProvider */
                    $AuthorizationProvider = self::get_service('AuthorizationProvider');
                    /** @var CurrentUser $CurrentUser */
                    $CurrentUser = self::get_service('CurrentUser');
                    $Role = $CurrentUser->get()->get_role();
                    //create permission records for each action this record supports
                    $object_actions = self::get_object_actions();
                    foreach ($object_actions as $object_action) {
                        $AuthorizationProvider->grant_permission($Role, $object_action, $this);
                    }
                }
            }

            $this->profile('CHECK 10', microtime(true) - $start_time);


            //new Event($this, '_after_write');
            self::get_service('Events')::create_event($this, '_after_write');

            if (method_exists($this, '_after_write') && !$this->are_method_hooks_disabled() && !($this instanceof ActiveRecordTemporalInterface) ) {
                $args = func_get_args();
                call_user_func_array([$this,'_after_write'], $args);//must return void
            }

            $this->profile('CHECK 11', microtime(true) - $start_time);

            $Transaction->commit();

            $this->profile('CHECK 12', microtime(true) - $start_time);

            //if (static::is_locking_enabled()) {
            if (!empty($LR)) {
                /** @var LockManagerInterface $LockManager */
                $LockManager = static::get_service('LockManager');
                $LockManager->release_lock('', $LR);
            }

            //the flag is lowered only after the record is committed
            $this->was_new_flag = false;
            //the modified data will be cleared only after the transaction is over
            $this->record_modified_data = [];

            $end_time = microtime(true);

            $this->profile('CHECK 13', microtime(true) - $start_time);

            /** @var ProfilerInterface $Apm */
            $Apm = self::get_service('Apm');
            $object_key = str_replace('\\','_',get_class($this)).'_'.implode('_', $this->get_primary_index()).'_write_time';
            $Apm->add_key($object_key);
            $Apm->increment_value($object_key, $end_time - $start_time);

        } finally {
            $this->nested_write_counter--;
        }
        return $this;
    }

    /**
     * Deletes active record
     */
    public function delete(): void
    {

        if ($this->is_read_only()) {
            throw new RunTimeException(sprintf(t::_('Trying to delete a read-only instance of class %s with id %s.'), get_class($this), $this->get_id()));
        }

//read_only is set in constructor() if method is GET
//        if (Coroutine::inCoroutine()) {
//            $Request = Coroutine::getRequest();
//            if ($Request->getMethodConstant() === Method::HTTP_GET) {
//                throw new RunTimeException(sprintf(t::_('Trying to delete object of class %s with id %s in GET request.'), get_class($this), $this->get_id()));
//            }
//        }



        //instead of setting the BypassAuthorizationProvider to bypass the authorization
        //it is possible not to set AuthorizationProvider at all (as this will save a lot of function calls
        $this->check_permission('delete');

        if ($this->is_new() && static::is_locking_enabled()) {
            $resource = MetaStore::get_key_by_object($this);
            static::get_service('LockManager')->acquire_lock($resource, LockInterface::WRITE_LOCK, $LR);
        }

        $Transaction = ActiveRecord::new_transaction($TR);
        $Transaction->begin();

        if (method_exists($this, '_before_delete') && !$this->are_method_hooks_disabled() && !($this instanceof ActiveRecordTemporalInterface) ) {
            $args = func_get_args();
            call_user_func_array([$this,'_before_delete'], $args);//must return void
        }


        //new Event($this, '_before_delete');
        self::get_service('Events')::create_event($this, '_before_delete');

        //get these before the object is deleted.
        $id = $this->get_id();
        $uuid = $this->get_uuid();

        //remove any permissions associated with this record
        $this->delete_permissions();

        //delete all alises to this object
        $this->delete_all_aliases();

        //and only then remove the record
        /** @var Store $OrmStore */
        $OrmStore = static::get_service('OrmStore');
        $OrmStore->remove_record($this);

        $this->add_log_entry('delete', sprintf(t::_('The object with ID %1$s and UUID %2$s was deleted.'), $id, $uuid));


        //new Event($this, '_after_delete');
        self::get_service('Events')::create_event($this, '_after_delete');

        if (method_exists($this, '_after_delete') && !$this->are_method_hooks_disabled() && !($this instanceof ActiveRecordTemporalInterface) ) {
            $args = func_get_args();
            call_user_func_array([$this,'_after_delete'], $args);//must return void
        }

        $Transaction->commit();

        //if (static::is_locking_enabled()) {
        if (!empty($LR)) {
            //self::LockManager()->release_lock('', $LR);
            static::get_service('LockManager')->release_lock('', $LR);
        }

        //parent::__destruct();
    }

    /**
     * @return bool
     */
    public static function has_main_table_defined(): bool
    {
        return isset(static::CONFIG_RUNTIME['main_table']);
    }

    /**
     * @return string
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws ReflectionException
     */
    public static function get_main_table(): string
    {
        if (empty(static::CONFIG_RUNTIME['main_table'])) {
            throw new RunTimeException(sprintf(t::_('The class %s does not define CONFIG_DEFAULTS[\'main_table\'] but is using a StructuredStore.'), get_called_class()));
        }
        $class = get_called_class();
        if (is_a($class, ActiveRecordTemporalInterface::class, true)) {
            $ret = static::CONFIG_RUNTIME['main_table'] . self::CONFIG_RUNTIME['temporal_table_suffix'];
        } else {
            $ret = static::CONFIG_RUNTIME['main_table'];
        }
        return $ret;
    }

    /**
     * @return bool
     */
    public static function has_structure_defined(): bool
    {
        return isset(static::CONFIG_RUNTIME['structure']);
    }

    /**
     * @return array
     */
    public static function get_validation_rules(): array
    {
        $ret = [];
        if (isset(static::CONFIG_RUNTIME['validation'])) {
            $ret = static::CONFIG_RUNTIME['validation'];
        }
        return $ret;
    }

    /**
     * Returns an instance by the provided UUID.
     * @param string $uuid
     * @return ActiveRecord
     * @throws InvalidArgumentException
     * @throws RecordNotFoundException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    final public static function get_by_uuid(string $uuid): ActiveRecord
    {
        if (!GeneralUtil::is_uuid($uuid)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided $uuid argument %1$s is not a valid UUID.'), $uuid));
        }
        //$Store = static::OrmStore();
        /**
         * Guzaba2\Orm\Store\Sql\Mysql
         */
        $Store = static::get_service('OrmStore');
        $meta_data = $Store->get_meta_by_uuid($uuid);
        if (!$meta_data) {
            throw new RecordNotFoundException(sprintf(t::_('There is no record found by UUID %1$s.'), $uuid));
        }
            
        $object_id = $meta_data['meta_object_id'];
        return new $meta_data['meta_class_name']($object_id);
    }

    public function _before_change_context(): void
    {
        if ($this->is_modified()) {
            $message = sprintf(t::_('It is not allowed to pass modified but unsaved ActiveRecord objects between coroutines. The object of class %s with index %s is modified but unsaved.'), get_class($this), print_r($this->get_primary_index(), true));
            throw new RunTimeException($message);
        }
    }

    /**
     * Based on the REST method type the locking may be disabled if the request is read only.
     */
    public static function enable_locking(): void
    {
        //cant use a local static var - this is shared between the coroutines
        //self::set_static('locking_enabled_flag', TRUE);
        if (Coroutine::inCoroutine()) {
            $Context = Coroutine::getContext();
            $Context->orm_locking_enabled_flag = true;
        } else {
            self::$orm_locking_enabled_flag = true;
        }
    }

    /**
     * @throws ContextDestroyedException
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public static function disable_locking(): void
    {
        //cant use a local static var - this is shared between the coroutines
        //self::set_static('locking_enabled_flag', FALSE);
        if (Coroutine::inCoroutine()) {
            $Context = Coroutine::getContext();
            $Context->orm_locking_enabled_flag = false;
        } else {
            self::$orm_locking_enabled_flag = false;
        }
    }

    /**
     * @return bool
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public static function is_locking_enabled(): bool
    {

        $called_class = get_called_class();
        if (!empty(self::CONFIG_RUNTIME['orm_locking_disabled'])) { //the ORM locking is disabled for this specific class
            return false;
        }

        if (Coroutine::inCoroutine()) {
            try {
                $Context = Coroutine::getContext();
                if (property_exists($Context, 'orm_locking_enabled_flag')) {
                    $ret = $Context->orm_locking_enabled_flag;
                } else {
                    $ret = self::$orm_locking_enabled_flag;
                }
            } catch (ContextDestroyedException $Exception) {
                //$ret = self::$orm_locking_enabled_flag;
                $ret = false;
                //it is OK - the coroutine is over and this destructor is invoked as part of the context cleanup
                //at this stage all locks have been released
            }
        } else {
            $ret = self::$orm_locking_enabled_flag;
        }
        return $ret;
    }

    /**
     * Returns the primary index (a scalar) of the record.
     * If the record has multiple columms please use get_primary_index (returns an array)
     * @return int
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws ReflectionException
     * @throws ContextDestroyedException
     * @see self::get_primary_index() which will return an associative array.
     */
    public function get_id(): int
    {
        $primary_index_columns = static::get_primary_index_columns();
        if (count($primary_index_columns) > 1) {
            throw new RunTimeException(sprintf(t::_('The class %s has a compound primary index and %s can not be used on it.'), get_class($this), __METHOD__));
        }

        if ($this->record_data) {
            $ret = $this->record_data[$primary_index_columns[0]];//the index should exist
        } else {
//            if (is_array($this->requested_index)) {
//                if (count($this->requested_index) > 1) {
//                    throw new RunTimeException(sprintf(t::_('The instance was instantiated with an array as index with more than one element.')));
//                }
//                $ret = array_key_first($this->requested_index);
//            } else {
//                $ret = $this->requested_index;
//            }
            $ret = self::INDEX_NEW;
        }
        
        return $ret;
    }

    /**
     * Returns the class ID for the class name that was provided.
     * If the class name is not provided it assumes the class on which the method was invoked.
     * May return NULL if NO Mysql store is used as the class ID is an implementation specific detail for MySQL.
     * @param null|string $class_name
     * @return int|null
     * @throws ContextDestroyedException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public static function get_class_id(?string $class_name = ''): ?int
    {
        $class_id = null;
        if (!$class_name) {
            $class_name = get_called_class();
            if ($class_name === __CLASS__) {
                throw new RunTimeException(sprintf(t::_('The method %s is to be called on a class extending %s, not on %s it self.'), __METHOD__, __CLASS__, __CLASS__));
            }
        }

        if (self::uses_service('MysqlOrmStore')) {
            /** @var Mysql $MysqlOrmStore */
            $MysqlOrmStore = self::get_service('MysqlOrmStore');

            $class_id = $MysqlOrmStore->get_class_id($class_name);
            if (!$class_id) {
                //if the class is not found this is a runtime error as the class on which this method is called is an ActiveRecord class, null is acceptable only if th
                throw new RunTimeException(sprintf(t::_('No class ID for class %s could be found.'), $class_name));
            }
        }
        return $class_id;
    }

    /**
     * Returns the class name
     * @param int $class_id
     * @return string
     * @throws ContextDestroyedException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public static function get_class_name(int $class_id): string
    {
        if (!$class_id) {
            throw new InvalidArgumentException(sprintf(t::_('No class_id provided.')));
        }
        if ($class_id < 0) {
            throw new InvalidArgumentException(sprintf(t::_('A negative class_id %s provided.'), $class_id));
        }
        /** @var Mysql $MysqlOrmStore */
        $MysqlOrmStore = self::get_service('MysqlOrmStore');
        $class_name = $MysqlOrmStore->get_class_name($class_id);
        if (!$class_name) {
            throw new RunTimeException(sprintf(t::_('There is no class associated with class_id %s.'), $class_id));
        }
        return $class_name;
    }

    /**
     * Returns the $index provided to the constructor.
     * The returned index is always converted to an array with column_name=>$value even if scalar was provided.
     * @return array
     */
    public function get_requested_index(): array
    {
        $ret = [];
        if (is_array($this->requested_index)) {
            $ret = $this->requested_index;
        } else {
            //if scalar has been provided then it is expected this to be the primary column (and this needs to be a single one as well)
            //there is already a check for that in the constructor
            $primary_index_columns = self::get_primary_index_columns();
            $ret = [ $primary_index_columns[0] => $this->requested_index ];
        }
        return $ret;
    }

    /**
     * Get the object UUID.
     * Only objects with a single scalar primary index have UUIDs.
     * @return mixed
     */
    public function get_uuid(): string
    {
        $ret = $this->meta_data['meta_object_uuid'];
        return $ret;
    }

    /**
     * Returns the primary index as an associative array.
     * For all purposes in the framework this method is to be used.
     * @return array
     */
    public function get_primary_index(): array
    {
        $ret = [];
        $primary_index_columns = static::get_primary_index_columns();
        foreach ($primary_index_columns as $primary_index_column) {
            $ret[$primary_index_column] = $this->record_data[$primary_index_column];
        }
        return $ret;
    }


    /**
     * Returns the primary index (associative array) from the provided $data array.
     * @param array $data
     * @return array|null
     */
    public static function get_index_from_data(array $data): ?array
    {
        $called_class = get_called_class();
        $ret = null;
        $primary_columns = static::get_primary_index_columns();
        foreach ($primary_columns as $primary_column) {
            if (!array_key_exists($primary_column, $data)) {
                break;//not enough data to construct the primary index
            }
            $ret[$primary_column] = $data[$primary_column];
        }

        return $ret;
    }


    /**
     * Returns the UUID of the object if it is present in the provided $data.
     * @param array $data
     * @return string|null
     */
    public static function get_uuid_from_data(array $data): ?string
    {
        $ret = null;
        if (isset($data['meta_object_uuid'])) {
            $ret = $data['meta_object_uuid'];
        }
        return $ret;
    }

    /**
     * Returns an indexed array with the name of the primary columns.
     * @return array
     */
    public static function get_primary_index_columns(): array
    {
        $called_class = get_called_class();
        return self::$primary_index_columns[$called_class];
    }

    /**
     * Returns the database columns (overloaded model properties)
     * @return array
     */
    public static function get_columns_data(): array
    {
        $called_class = get_called_class();
        return self::$columns_data[$called_class];
    }

    /**
     * Retruns the combined class properties and database columns (overloaded model properties)
     * @return array
     */
    public static function get_properties_data(): array
    {
        return self::get_columns_data() + self::get_class_properties_data();
    }

    /**
     * Returns the class own properties.
     * @return array
     */
    public static function get_class_properties_data(): array
    {
        $called_class = get_called_class();
        return self::$properties_data[$called_class];
    }



    /**
     * @return bool
     */
    public static function uses_autoincrement(): bool
    {
        $ret = false;
        foreach (static::get_columns_data() as $column_datum) {
            if (isset($column_datum['autoincrement']) && $column_datum['autoincrement'] === true) {
                $ret = true;
                break;
            }
        }
        return $ret;
    }

    public static function get_default_route(): ?string
    {
        return static::CONFIG_RUNTIME['default_route'] ?? null;
    }
    
    //public static function get_meta_table() : string
    //{
    //    return static::CONFIG_RUNTIME['meta_table'];
    //}



    /**
     * Returns true is the record is just being created now and it is not yet saved.
     * More precisely if the record has no yet UUID & ID it will return TRUE.
     * The record may have been submitted to be saved to the DB and have UUID & ID but may not be actually be saved as it may be part of a transaction which can get rolled back.
     * @return bool
     */
    public function is_new(): bool
    {
        return $this->is_new_flag;
    }

    /**
     * Returns TRUE when the object has UUID & ID but still not committed to the database.
     * Once committed then will return FALSE.
     * This method is useful in _after_write() context.
     * @return bool
     */
    public function was_new(): bool
    {
        return $this->was_new_flag;
    }

    public function is_modified(): bool
    {
        return count($this->record_modified_data) ? true : false;
    }
    
    public function disable_method_hooks(): void
    {
        $this->disable_method_hooks++;
    }

    public function enable_method_hooks(): void
    {
        $this->disable_method_hooks--;
    }

    public function are_method_hooks_disabled(): bool
    {
        return (bool) $this->disable_method_hooks;
    }

    public function is_read_only(): bool
    {
        return $this->read_only_flag;
    }

    private function set_read_only(bool $flag): void
    {
        $this->read_only_flag = $flag;
    }

    public function are_permission_checks_disabled(): bool
    {
        return $this->permission_checks_disabled_flag;
    }

    public function is_modified_data_tracking_disabled(): bool
    {
        return (bool) $this->modified_data_tracking_disabled;
    }

    public function disable_modified_data_tracking(): void
    {
        $this->modified_data_tracking_disabled++;
    }

    public function enable_modified_data_tracking(): void
    {
        $this->modified_data_tracking_disabled--;
    }

    /**
     * This is similar to self::get_record_data() but also invokes the property hooks.
     * @return array
     */
    public function get_property_data(): array
    {
        $ret = [];
        foreach ($this->get_property_names() as $property) {
            $ret[$property] = $this->{$property};//this triggers the overloading and the hooks
        }
        return $ret;
    }

    /**
     * Returns the raw record data.
     * To take into account the _before_get and _after_get hooks please use self::get_property_data()
     * @return array
     */
    public function get_record_data(): array
    {
        return $this->record_data;
    }

    /**
     * Sets all data (or subset) without triggering the hooks.
     *
     * @param array $record_data
     * @throws InvalidArgumentException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function set_record_data(array $record_data): void
    {
        $property_names = $this->get_property_names();
        $record_data_property_names = array_keys($record_data);
        //$record_data_property_names must be a subset of $property_names
        //if (array_intersect($record_data_property_names, $property_names) !== $record_data_property_names) {
        if ($diff = array_diff($record_data_property_names, $property_names)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided $record_data argument contains invalid keys %1$s.'), implode(', ', $diff)));
        }

        $this->record_data = array_replace($this->record_data, $record_data);
    }

    /**
     * Whether this class creates meta data entries.
     * @return bool
     */
    public static function uses_meta(): bool
    {
        return
            empty(static::CONFIG_RUNTIME['no_meta'])
            &&
            !is_a(get_called_class(), ActiveRecordTemporalInterface::class, true)
            &&
            !is_a(get_called_class(), LogEntry::class, true)
            ;
    }

    /**
     * Whether this class creates log entries.
     * @return bool
     */
    public static function uses_log(): bool
    {
        return
            empty(static::CONFIG_RUNTIME['no_log'])
            &&
            !is_a(get_called_class(), ActiveRecordTemporalInterface::class, true)
            &&
            !is_a(get_called_class(), LogEntry::class, true)
            ;
    }

    public function get_meta_data(): array
    {
        $ret = $this->meta_data;
        return $ret;
    }

    /**
     * Can be overriden to provide editable default routing.
     * @return iterable|null
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws ReflectionException
     * @throws ContextDestroyedException
     */
    public static function get_routes(): ?iterable
    {
        $ret = null;
        $called_class = get_called_class();
        //if (array_key_exists('route', static::CONFIG_RUNTIME)) {
        if ($called_class::has_runtime_configuration() && array_key_exists('route', static::CONFIG_RUNTIME)) {
            $default_route = static::CONFIG_RUNTIME['route'];
            if ($default_route[0] !== '/') {
                throw new ConfigurationException(sprintf(t::_('The route "%1$s" for ActiveRecord class %s seems wrong. All routes must begin with "/".'), $default_route, $called_class));
            }
            $ret = [
                $default_route                              => [
                    //Method::HTTP_GET_HEAD_OPT                   => [ActiveRecordDefaultController::class, 'options'],
                    //Method::HTTP_OPTIONS                        => [ActiveRecordDefaultController::class, 'options'],
                    Method::HTTP_GET                            => [ActiveRecordDefaultController::class, 'list'],
                    Method::HTTP_POST                           => [ActiveRecordDefaultController::class, 'crud_action_create'],
                ],
                $default_route . '/{uuid}'                    => [
                    //Method::HTTP_GET_HEAD_OPT                   => [ActiveRecordDefaultController::class, 'crud_action_read'],
                    Method::HTTP_GET                           => [ActiveRecordDefaultController::class, 'crud_action_read'],
                    Method::HTTP_PUT | Method::HTTP_PATCH       => [ActiveRecordDefaultController::class, 'crud_action_update'],
                    Method::HTTP_DELETE                         => [ActiveRecordDefaultController::class, 'crud_action_delete'],
                ],
                $default_route . '/{uuid}/permission'         => [
                    Method::HTTP_POST                           => [ActiveRecordDefaultController::class, 'crud_grant_permission'],
                    Method::HTTP_DELETE                         => [ActiveRecordDefaultController::class, 'crud_revoke_permission'],
                ],
                $default_route . '/class-permission'          => [
                    Method::HTTP_POST                           => [ActiveRecordDefaultController::class, 'crud_grant_class_permission'],
                    Method::HTTP_DELETE                         => [ActiveRecordDefaultController::class, 'crud_revoke_class_permission'],
                ],
            ];
        }
        return $ret;
    }

    /**
     * Returns the property names of the modified properties
     * @return array Indexed array with property names
     */
    public function get_modified_properties_names(): array
    {
        //return $this->record_modified_data;
        return array_keys($this->record_modified_data);
    }

    public function is_property_modified(string $property): bool
    {
        if (!array_key_exists($property, $this->record_data)) {
            throw new RunTimeException(sprintf(t::_('Trying to check a non existing property "%s" of instance of "%s" (ORM class).'), $property, get_class($this)));
        }
        return array_key_exists($property, $this->record_modified_data);
    }

//    /**
//     * Resets the modification history of a property.
//     * Can be used if a property is modified in _after_read.
//     * @param string $property
//     * @return void
//     * @throws RunTimeException
//     * @throws \Azonmedia\Exceptions\InvalidArgumentException
//     */
//    public function reset_modified_property(string $property): void
//    {
//        if (!array_key_exists($property, $this->record_data)) {
//            throw new RunTimeException(sprintf(t::_('Trying to check a non existing property "%s" of instance of "%s" (ORM class).'), $property, get_class($this)));
//        }
//        $this->record_modified_data[$property] = [];
//    }
//
//    /**
//     * Resets the modification history of all properties.
//     * Can be used if a property is modified in _after_read.
//     * @return void
//     */
//    public function reset_modified_properties(): void
//    {
//        $this->record_modified_data = [];
//    }

    /**
     * Returns all old values
     * @param string $property
     * @return array
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws ReflectionException
     */
    public function get_property_old_values(string $property): array
    {
        if (!array_key_exists($property, $this->record_data)) {
            throw new RunTimeException(sprintf(t::_('Trying to get old values for a non existing property "%s" of instance of "%s" (ORM class).'), $property, get_class($this)));
        }
        return $this->record_modified_data[$property] ?? [];
    }

    /**
     * Returns the last old value
     * @param string $property
     * @return mixed
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws ReflectionException
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
     * Returns the original value (before the first modification) of the property
     * @param string $property
     * @return mixed
     * @throws ContextDestroyedException
     * @throws ReflectionException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     */
    public function get_property_original_value(string $property) /* mixed */
    {
        $modified_data = $this->get_property_old_values($property);
        if (!count($modified_data)) {
            throw new RunTimeException(sprintf(t::_('The property "%s" on instnace of class "%s" (ORM class) is not modified and has no original value.'), $property, get_class($this)));
        }
        return $modified_data[0];
    }

    /**
     * Updates the primary index after the object is saved.
     * To be used on the records using autoincrement.
     * To be called classes implementing the StoreInterface.
     */
    public function update_primary_index(/* int | string */ $index): void
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
     * Returns all ActiveRecord objects that match the given criteria
     * @param  array  $index array of $property_name => $value
     * @return ActiveRecord[]  list of ActiveRecord objects
     * @throws RunTimeException
     */
    //public static function get_by(array $index = []): iterable
    public static function get_by(
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
        $class_name = static::class;
        $data = static::get_data_by($index,  $offset, $limit, $use_like, $sort_by, $sort_desc, $total_found_rows, $permission_checks_disabled);


        //$primary_index = static::get_primary_index_columns()[0];
        $primary_index_columns = static::get_primary_index_columns();

        $ret = array();
        foreach ($data as $record) {
            //$object_index = ArrayUtil::extract_keys($record, $primary_index_columns);
            //$ret[] = new $class_name($object_index);
            $ret[] = static::get_from_record($record);//this does not trigger reads in the DB, just loads the object
        }
        return $ret;
    }

    /**
     * @param array $index
     * @param int $offset
     * @param int $limit
     * @param bool $use_like
     * @param string $sort_by
     * @param bool $sort_desc
     * @return iterable
     * @throws RunTimeException
     */
    //public static function get_data_by(array $index, int $offset = 0, int $limit = 0, bool $use_like = FALSE, string $sort_by = 'none', bool $sort_desc = FALSE) : iterable
    public static function get_data_by(
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
        /** @var StoreInterface $OrmStore */
        $OrmStore = static::get_service('OrmStore');
        return $OrmStore->get_data_by(static::class, $index, $offset, $limit, $use_like, $sort_by, $sort_desc, $total_found_rows);
    }

    public static function is_loaded_in_memory(): bool
    {
        return !empty(static::CONFIG_RUNTIME['load_in_memory']);
    }

    public static function initialize_in_memory(): void
    {
        $class = get_called_class();
        $OrmStore = self::get_service('OrmStore');
        $data = $OrmStore->get_data_by($class, []);//get everything
    }

    public static function get_standard_actions(): array
    {
        return self::STANDARD_ACTIONS;
    }

    public static function data_to_collection(array $data): ActiveRecordCollection
    {
        return new ActiveRecordCollection(get_called_class(), $data);
    }

    public static function get_store(): StoreInterface
    {
        /** @var StoreInterface $Store */
        $Store = self::get_service('OrmStore');
        return $Store;
    }

    /**
     *
     * @param ScopeReference|null $ScopeReference
     * @param array $options
     * @return Transaction
     */
    public static function new_transaction(?ScopeReference &$ScopeReference, array $options = []): TransactionInterface
    {
//
//        if ($ScopeReference) {
//            //$ScopeReference->set_release_reason($ScopeReference::RELEASE_REASON_OVERWRITING);
//            $ScopeReference = NULL;//trigger rollback (and actually destroy the transaction object - the object may or may not get destroyed - it may live if part of another transaction)
//        }
//
//        $Transaction = new \Guzaba2\Orm\Transaction($options);
//
//        $ScopeReference = new ScopeReference($Transaction);
//
//        return $Transaction;
        //it is not an issue that a new instance of OrmTransactionalResource is created every time as this is not really holding any resource
        //the only method that is needed is get_resource_id() and this is Coroutine dependent not instance dependent
        return (new OrmTransactionalResource())->new_transaction($ScopeReference, $options);
    }

    /**
     * Used to obtain the current transaction within the ActiveRecord context.
     * For example in the hooks like _before_write() or _after_delete().
     * NO - the whole concept of obtaining the current transaction is flawed.
     * This will allow to attach callbacks to a transaction that was started somewhere else.
     * Instead a nested transaction is to be started
     * CHANGED - the method will be provided as it is really needed by Memory store - it should not write to the store if there is a transaction
     * @return TransactionInterface
     */
    public static function get_current_transaction(): ?TransactionInterface
    {
        return (new OrmTransactionalResource())->get_current_transaction();
    }
}
