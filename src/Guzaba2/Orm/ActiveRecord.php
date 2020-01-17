<?php
declare(strict_types=1);

namespace Guzaba2\Orm;

use Azonmedia\Lock\Interfaces\LockInterface;
use Azonmedia\Reflection\ReflectionClass;
use Azonmedia\Utilities\ArrayUtil;
use Azonmedia\Utilities\GeneralUtil;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Traits\StaticStore;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Coroutine\Exceptions\ContextDestroyedException;
use Guzaba2\Http\Method;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\MetaStore\MetaStore;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Orm\Store\Interfaces\StructuredStoreInterface;
use Guzaba2\Orm\Store\Memory;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Orm\Traits\ActiveRecordAuthorization;
use Guzaba2\Orm\Traits\ActiveRecordHooks;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Event\Event;
use Guzaba2\Orm\Traits\ActiveRecordOverloading;
use Guzaba2\Orm\Traits\ActiveRecordStructure;
use Guzaba2\Orm\Traits\ActiveRecordIterator;
use Guzaba2\Orm\Traits\ActiveRecordValidation;


/**
 * Class ActiveRecord
 * @package Guzaba2\Orm
 * @method \Guzaba2\Orm\Store\Store OrmStore()
 * @method \Azonmedia\Lock\Interfaces\LockManagerInterface LockManager()
 */
class ActiveRecord extends Base implements ActiveRecordInterface, \JsonSerializable, \Iterator
    //, \ArrayAccess, \Countable
{
    //for the porpose of splitting and organising the methods (as this class would become too big) traits are used
    use ActiveRecordOverloading;
    use ActiveRecordStructure;
    use ActiveRecordIterator;
    use ActiveRecordValidation;
    use ActiveRecordHooks;
    use ActiveRecordAuthorization;

    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'OrmStore',
            'LockManager',
            'Events',
            'AuthorizationProvider',
        ],
        //only for non-sql stores
        'structure' => [

        ],
    ];

    protected const CONFIG_RUNTIME = [];

    protected const CAST_PROPERTIES_ON_ASSIGNMENT = false;


    /**
     * @var StoreInterface
     */
    protected StoreInterface $Store;

    /**
     * @var bool
     */
    protected bool $is_new_flag = TRUE;

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
    protected bool $disable_property_hooks_flag = FALSE;
    
    /**
     * Are the method hooks like _before_save enabled or not.
     * @see activeRecord::disable_method_hooks()
     * @see activeRecord::enable_method_hooks()
     *
     * @var bool
     */
    protected bool $disable_method_hooks_flag = FALSE;
    
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
    protected static array $columns_data = [];

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
    protected static bool $orm_locking_enabled_flag = TRUE;

    /**
     * @var bool
     */
    private bool $read_lock_obtained_flag = FALSE;


    /**
     * To store what was initially provided as $index to the constructor.
     * Will be returned by get_id() when the record_data is not yet populated.
     * This may happen when DetaultCurrentUser is set to non 0.
     * @var scalar
     */
    private /* scalar */ $requested_index = self::INDEX_NEW;


    private bool $read_only_flag = FALSE;

    private bool $permission_checks_disabled_flag = FALSE;

    /**
     * ActiveRecord constructor.
     * @param int $index
     * @param StoreInterface|null $Store
     * @throws InvalidArgumentException
     * @throws RunTimeException
     * @throws \Guzaba2\Kernel\Exceptions\ConfigurationException
     */
    //public function __construct(/* mixed*/ $index = self::INDEX_NEW, ?StoreInterface $Store = NULL)
    public function __construct(/* mixed*/ $index = self::INDEX_NEW, bool $read_only = FALSE, bool $permission_checks_disabled = FALSE, ?StoreInterface $Store = NULL)
    {
        parent::__construct();



        if (strpos(get_class($this),'Test') !== FALSE) {
            //print_r(static::CONFIG_RUNTIME);
            //print_r(static::CONFIG_DEFAULTS);
        }

        if (!isset(static::CONFIG_RUNTIME['main_table'])) {
            throw new RunTimeException(sprintf(t::_('ActiveRecord class %s does not have "main_table" entry in its CONFIG_RUNTIME.'), get_called_class()));
        }

        $this->read_lock_obtained_flag = $read_only;
        $this->permission_checks_disabled_flag = $permission_checks_disabled;

        if (Coroutine::inCoroutine()) {
            $Request = Coroutine::getRequest();
            if ($Request->getMethodConstant() === Method::HTTP_GET) {
                $this->read_only_flag = TRUE;
            }
        }

        //$this->locking_enabled_flag = self::is_locking_enabled();
        
        //if ($this->index == self::INDEX_NEW) { //checks is the index already set (as it may be set in _before_construct()) - if not set it
        //    $this->index = $index;
        //}

        if ($Store) {
            $this->Store = $Store;
        } else {
            //$this->Store = static::OrmStore();//use the default service
            $this->Store = static::get_service('OrmStore');
        }
        
        //self::initialize_columns();
        $this->requested_index = $index;

        $primary_columns = static::get_primary_index_columns();

        $called_class = get_class($this);
        // 1. check is there main index loaded
        // if $this->index is still empty this means that this table has no primary index
        if (!count($primary_columns)) {
            throw new \Guzaba2\Kernel\Exceptions\ConfigurationException(sprintf(t::_('The class %s has no primary index defined.'), $called_class));
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
                //} elseif (strlen((string)$index) === 36) {
            } elseif (GeneralUtil::is_uuid( (string) $index)) {
                //this is UUID
                $index = ['object_uuid' => $index];
            } else {
                throw new \Guzaba2\Base\Exceptions\RunTimeException(sprintf(t::_('An unsupported type "%s" was supplied for the index of object of class "%s".'), gettype($index), get_class($this)));
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

            $this->read($index);


        }

    }

    /**
     * Returns an instance filled with the provided data (it should also inject it in the Memory store)
     * @param iterable $data
     * @return ActiveRecordInterface
     */
    public static function get_from_record(iterable $data) : ActiveRecordInterface
    {
        //TODO implement
    }

    public function __destruct()
    {


        //print 'destr '.get_class($this).PHP_EOL;


        if (!$this->is_new() && count($this->record_data)) { //count($this->record_data) means is not deleted
            $this->Store->free_pointer($this);
        }

        if (self::is_locking_enabled() && !$this->is_new()) {

            if ($this->read_lock_obtained_flag) { //this is needed for the new records.. at this point they are no longer new if successfulyl saved
                $resource = MetaStore::get_key_by_object($this);
                static::get_service('LockManager')->release_lock($resource);

                $this->read_lock_obtained_flag = FALSE;
            }

        }
        parent::__destruct();
    }

    final public function __toString() : string
    {
        //return MetaStore::get_key_by_object($this);
        return $this->as_array();
    }

    /**
     * @implements \jsonSerializable
     * @return mixed|void
     */
    public function jsonSerialize()
    {
        return $this->as_array();
    }

    public function as_array() : array
    {
        //return ['data' => $this->get_property_data(), 'meta' => $this->get_meta_data()];
        return array_merge( $this->get_property_data(), $this->get_meta_data() );
    }

    public function read(/* int|string|array */ $index) : void
    {



        if (!is_string($index) && !is_int($index) && !is_array($index)) {
            throw new InvalidArgumentException(sprintf(t::_('The $index argument of %s() must be int, string or array. %s provided instead.'),__METHOD__, gettype($index) ));
        }

//        if (static::uses_service('AuthorizationProvider') && static::uses_permissions() && !$this->are_permission_checks_disabled() ) {
//            $this->check_permission('read');
//        }

        if (method_exists($this, '_before_read') && !$this->are_method_hooks_disabled()) {
            $args = func_get_args();
            call_user_func_array([$this,'_before_read'], $args);//must return void
        }

        self::get_service('Events')::create_event($this, '_before_read');



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



        //check the permissions now, not before the record is found as the provided index may be a an array and then the permissions lookup will fail
        $this->check_permission('read');

        if (!count($this->meta_data)) {
            throw new LogicException(sprintf(t::_('No metadata is found/loaded for object of class %s with ID %s.'), get_class($this), print_r($index, TRUE)));
        }


        if (static::is_locking_enabled()) {
            //if ($this->locking_enabled_flag) {
            $resource = MetaStore::get_key_by_object($this);
            $LR = '&';//this means that no scope reference will be used. This is because the lock will be released in another method/scope.
            //self::LockManager()->acquire_lock($resource, LockInterface::READ_LOCK, $LR);
            static::get_service('LockManager')->acquire_lock($resource, LockInterface::READ_LOCK, $LR);

            $this->read_lock_obtained_flag = TRUE;
        }

        $this->is_new_flag = FALSE;

        //new Event($this, '_after_read');
        self::get_service('Events')::create_event($this, '_after_read');

        //_after_load() event
        if (method_exists($this, '_after_read') && !$this->are_method_hooks_disabled()) {
            $args = func_get_args();
            call_user_func_array([$this,'_after_read'], $args);//must return void
        }


    }

    public function write() : ActiveRecordInterface
    {

        //instead of setting the BypassAuthorizationProvider to bypass the authorization
        //it is possible not to set AuthorizationProvider at all (as this will save a lot of function calls
        if ($this->is_new()) {
            $this->check_permission('create');
        } else {
            $this->check_permission('write');
        }


        if ($this->is_read_only()) {
            throw new RunTimeException(sprintf(t::_('Trying to write/save a read-only instance of class %s with id %s.'), get_class($this), $this->get_id() ));
        }

//read_only is set in constructor() if method is GET
//        if (Coroutine::inCoroutine()) {
//            $Request = Coroutine::getRequest();
//            if ($Request->getMethodConstant() === Method::HTTP_GET) {
//                throw new RunTimeException(sprintf(t::_('Trying to save object of class %s with id %s in GET request.'), get_class($this), $this->get_id()));
//            }
//        }

        if (!count($this->get_modified_properties_names()) && !$this->is_new()) {
            return $this;
        }

        //TODO - it is not correct to release the lock and acquire it again - someone may obtain it in the mean time
        //instead the lock levle should be updated (lock reacquired)
        if ($this->is_new() && static::is_locking_enabled()) {
            $resource = MetaStore::get_key_by_object($this);
            $LR = '&';//this means that no scope reference will be used. This is because the lock will be released in another method/scope.
            //self::LockManager()->acquire_lock($resource, LockInterface::READ_LOCK, $LR);
            static::get_service('LockManager')->acquire_lock($resource, LockInterface::READ_LOCK, $LR);
            unset($LR);
        }

        //BEGIN ORMTransaction (==ORMDBTransaction)

        if (method_exists($this, '_before_write') && !$this->are_method_hooks_disabled()) {
            $args = func_get_args();
            call_user_func_array([$this,'_before_write'], $args);//must return void
        }

        self::get_service('Events')::create_event($this, '_before_write');

        if (static::is_locking_enabled()) {
            $resource = MetaStore::get_key_by_object($this);
            //self::LockManager()->acquire_lock($resource, LockInterface::WRITE_LOCK, $LR);
            static::get_service('LockManager')->acquire_lock($resource, LockInterface::WRITE_LOCK, $LR);
        }

        static::get_service('OrmStore')->update_record($this);

        self::get_service('Events')::create_event($this, '_after_write');

        if (method_exists($this, '_after_write') && !$this->are_method_hooks_disabled()) {
            $args = func_get_args();
            call_user_func_array([$this,'_after_write'], $args);//must return void
        }

        //COMMIT ORMTransaction



        //reattach the pointer
        $pointer =& $this->Store->get_data_pointer(get_class($this), $this->get_primary_index());

        $this->record_data =& $pointer['data'];
        $this->meta_data =& $pointer['meta'];
        $this->record_modified_data = [];

        $this->is_new_flag = FALSE;

        if (static::is_locking_enabled()) {
            //self::LockManager()->release_lock('', $LR);
            static::get_service('LockManager')->release_lock('', $LR);
        }

        return $this;
    }

    /**
     * Deletes active record
     */
    public function delete(): void
    {

        if ($this->is_read_only()) {
            throw new RunTimeException(sprintf(t::_('Trying to delete a read-only instance of class %s with id %s.'), get_class($this), $this->get_id() ));
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


        if (method_exists($this, '_before_delete') && !$this->are_method_hooks_disabled()) {
            $args = func_get_args();
            call_user_func_array([$this,'_before_delete'], $args);//must return void
        }


        //new Event($this, '_before_delete');
        self::get_service('Events')::create_event($this, '_before_delete');

        if (static::is_locking_enabled()) {
            $resource = MetaStore::get_key_by_object($this);
            //self::LockManager()->acquire_lock($resource, LockInterface::WRITE_LOCK, $LR);
            static::get_service('LockManager')->acquire_lock($resource, LockInterface::WRITE_LOCK, $LR);
        }

        //remove any permissions associated with this record
        $this->delete_permissions();
        //and only then remove the record
        static::get_service('OrmStore')->remove_record($this);





        //new Event($this, '_after_delete');
        self::get_service('Events')::create_event($this, '_after_delete');

        if (method_exists($this, '_after_delete') && !$this->are_method_hooks_disabled()) {
            $args = func_get_args();
            call_user_func_array([$this,'_after_delete'], $args);//must return void
        }

        if (static::is_locking_enabled()) {
            //self::LockManager()->release_lock('', $LR);
            static::get_service('LockManager')->release_lock('', $LR);
        }

        //parent::__destruct();
    }

    /**
     * @return bool
     */
    public static function has_main_table_defined() : bool
    {
        return isset(static::CONFIG_RUNTIME['main_table']);
    }

    /**
     * @return string
     * @throws RunTimeException
     */
    public static function get_main_table() : string
    {
        if (empty(static::CONFIG_RUNTIME['main_table'])) {
            throw new RunTimeException(sprintf(t::_('The class %s does not define CONFIG_DEFAULTS[\'main_table\'] but is using a StructuredStore.'), get_called_class() ));
        }
        return static::CONFIG_RUNTIME['main_table'];
    }

    /**
     * @return bool
     */
    public static function has_structure_defined() : bool
    {
        return isset(static::CONFIG_RUNTIME['structure']);
    }

    /**
     * @return array
     */
    public static function get_validation_rules() : array
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
     * @throws RecordNotFoundException
     */
    public static final function get_by_uuid(string $uuid) : ActiveRecord
    {
        //$Store = static::OrmStore();
        /**
         * Guzaba2\Orm\Store\Sql\Mysql
         */
        $Store = static::get_service('OrmStore');
        $meta_data = $Store->get_meta_by_uuid($uuid);
        if (!$meta_data) {
            throw new RecordNotFoundException(sprintf(t::_('There is no record found by UUID %s.'), $uuid));
        }
            
        $id = $meta_data['meta_object_id'];
        return new $meta_data['meta_class_name']($id);
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
            $Context->orm_locking_enabled_flag = TRUE;
        } else {
            self::$orm_locking_enabled_flag = TRUE;
        }
    }

    public static function disable_locking() : void
    {
        //cant use a local static var - this is shared between the coroutines
        //self::set_static('locking_enabled_flag', FALSE);
        if (Coroutine::inCoroutine()) {
            $Context = Coroutine::getContext();
            $Context->orm_locking_enabled_flag = FALSE;
        } else {
            self::$orm_locking_enabled_flag = FALSE;
        }
    }

    public static function is_locking_enabled() : bool
    {
        //debug_print_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
        //return self::get_static('locking_enabled_flag');

        $called_class = get_called_class();
        if (!empty(self::CONFIG_RUNTIME['orm_locking_disabled'])) { //the ORM locking is disabled for this specific class
            return FALSE;
        }

        if (Coroutine::inCoroutine() ) {

            try {
                $Context = Coroutine::getContext();
                if (property_exists($Context,'orm_locking_enabled_flag')) {
                    $ret = $Context->orm_locking_enabled_flag;
                } else {
                    $ret = self::$orm_locking_enabled_flag;
                }
            } catch (ContextDestroyedException $Exception) {
                //$ret = self::$orm_locking_enabled_flag;
                $ret = FALSE;
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
     * If the record has multiple columms please use @see self::get_primary_index() which will return an associative array.
     * @return mixed
     * @throws RunTimeException
     */
    public function get_id()  /* int|string */
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
     * Returns the $index provided to the constructor.
     * The returned index is always converted to an array with column_name=>$value even if scalar was provided.
     * @return array
     */
    public function get_requested_index() : array
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
    public function get_uuid() : string
    {
        $ret = $this->meta_data['meta_object_uuid'];
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
     * Returns the primary index (associative array) from the provided $data array.
     * @param array $data
     * @return array|null
     */
    public static function get_index_from_data(array $data): ?array
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


    /**
     * Returns the UUID of the object if it is present in the provided $data.
     * @param array $data
     * @return string|null
     */
    public static function get_uuid_from_data(array $data) : ?string
    {
        $ret = NULL;
        if (isset($data['meta_object_uuid'])) {
            $ret = $data['meta_object_uuid'];
        }
        return $ret;
    }

    /**
     * Returns an indexed array with the name of the primary columns.
     * @return array
     */
    public static function get_primary_index_columns() : array
    {
        $called_class = get_called_class();
        return self::$primary_index_columns[$called_class];
    }

    /**
     * @return array
     */
    public static function get_columns_data() : array
    {
        $called_class = get_called_class();
        //self::initialize_columns();
        return self::$columns_data[$called_class];
    }

    /**
     * @return bool
     */
    public static function uses_autoincrement() : bool
    {
        $ret = FALSE;
        foreach (static::get_columns_data() as $column_datum) {
            if (isset($column_datum['autoincrement']) && $column_datum['autoincrement'] === TRUE) {
                $ret = TRUE;
                break;
            }
        }
        return $ret;
    }

    public static function get_default_route() : ?string
    {
        return static::CONFIG_RUNTIME['default_route'] ?? NULL;
    }
    
    //public static function get_meta_table() : string
    //{
    //    return static::CONFIG_RUNTIME['meta_table'];
    //}



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

    public function are_method_hooks_disabled() : bool
    {
        return $this->disable_method_hooks_flag;
    }

    public function is_read_only() : bool
    {
        return $this->read_only_flag;
    }

    public function are_permission_checks_disabled() : bool
    {
        return $this->permission_checks_disabled_flag;
    }

    /**
     * This is similar to self::get_record_data() but also invokes the property hooks.
     * @return array
     */
    public function get_property_data() : array
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
    public function get_record_data() : array
    {
        return $this->record_data;
    }

    public function get_meta_data() : array
    {
        $ret = $this->meta_data;
        return $ret;
    }

    /**
     * Can be overriden to provide editable default routing.
     * @return iterable|null
     */
    public static function get_routes() : ?iterable
    {
        $ret = NULL;
        $called_class = get_called_class();
        //if (array_key_exists('route', static::CONFIG_RUNTIME)) {
        if ($called_class::has_runtime_configuration() && array_key_exists('route', static::CONFIG_RUNTIME)) {
            if (static::CONFIG_RUNTIME['route'][0] !== '/') {
                throw new RunTimeException(sprintf(t::_('The route "%s" for ActiveRecord class %s seems wrong. All routes must begin with "/".'), static::CONFIG_RUNTIME['route'], get_called_class() ));
            }
            $default_route = static::CONFIG_RUNTIME['route'];
            $ret = [
                $default_route                              => [
                    //Method::HTTP_GET_HEAD_OPT                   => [ActiveRecordDefaultController::class, 'options'],
                    Method::HTTP_OPTIONS                        => [ActiveRecordDefaultController::class, 'options'],
                    Method::HTTP_GET                            => [ActiveRecordDefaultController::class, 'list'],
                    Method::HTTP_POST                           => [ActiveRecordDefaultController::class, 'crud_action_create'],
                ],
                $default_route.'/{uuid}'                    => [
                    Method::HTTP_GET_HEAD_OPT                   => [ActiveRecordDefaultController::class, 'crud_action_read'],
                    Method::HTTP_PUT | Method::HTTP_PATCH       => [ActiveRecordDefaultController::class, 'crud_action_update'],
                    Method::HTTP_DELETE                         => [ActiveRecordDefaultController::class, 'crud_action_delete'],
                ],
                $default_route.'/{uuid}/permission'         => [
                    Method::HTTP_POST                           => [ActiveRecordDefaultController::class, 'crud_grant_permission'],
                    Method::HTTP_DELETE                         => [ActiveRecordDefaultController::class, 'crud_revoke_permission'],
                ],
                $default_route.'/class-permission'          => [
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
    public function update_primary_index(/* int | string */ $index) : void
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
     * @return iterable  list of ActiveRecord objects
     * @throws RunTimeException
     */
    public static function get_by(array $index = []) : iterable
    {
        $class_name = static::class;
        $data = static::get_data_by($index);

        //$primary_index = static::get_primary_index_columns()[0];
        $primary_index_columns = static::get_primary_index_columns();

        $ret = array();
        foreach ($data as $record) {
            $object_index = ArrayUtil::extract_keys($record, $primary_index_columns);
            $ret[] = new $class_name($object_index);
        }

        return $ret;
    }

    /**
     * @param array $index
     * @param int $offset
     * @param int $limit
     * @param bool $use_like
     * @param string $sort_by
     * @param $sort_desc
     * @return iterable
     * @throws RunTimeException
     */
    //public static function get_data_by(array $index, int $offset = 0, int $limit = 0, bool $use_like = FALSE, string $sort_by = 'none', bool $sort_desc = FALSE) : iterable
    public static function get_data_by(array $index, int $offset = 0, int $limit = 0, bool $use_like = FALSE, ?string $sort_by = NULL, bool $sort_desc = FALSE, ?int &$total_found_rows = NULL) : iterable
    {
        return static::get_service('OrmStore')->get_data_by(static::class, $index, $offset, $limit, $use_like, $sort_by, $sort_desc, $total_found_rows);
    }

//    public static function get_data_count_by(array $index, bool $use_like = FALSE) : int
//    {
//        $Store = static::get_service('OrmStore');
//        //static::initialize_columns();
//        $class_name = static::class;
//        $data = $Store->get_data_count_by($class_name, $index, $use_like);
//        return $data;
//    }

    /**
     * Returns all ActiveRecord classes that are loaded by the Kernel in the provided namespace prefixes.
     * Usually the array from Kernel::get_registered_autoloader_paths() is provided to $ns_prefixes
     * @param array $ns_prefixes
     * @return array Indexed array with class names
     */
    public static function get_active_record_classes(array $ns_prefixes) : array
    {
        static $active_record_classes = [];
        $args_hash = md5(ArrayUtil::array_as_string($ns_prefixes));
        if (!array_key_exists( $args_hash, $active_record_classes ) ) {
            $classes = Kernel::get_classes($ns_prefixes, ActiveRecordInterface::class);
            $classes = array_filter( $classes, fn(string $class) : bool => !in_array($class, [ActiveRecord::class, ActiveRecordInterface::class] )  && ( new ReflectionClass($class) )->isInstantiable()  );
            $active_record_classes[$args_hash] = $classes;
        }
        return $active_record_classes[$args_hash];
    }

    public static function is_loaded_in_memory() : bool
    {
        return !empty(static::CONFIG_RUNTIME['load_in_memory']);
    }

    public static function initialize_in_memory() : void
    {
        $class = get_called_class();
        $OrmStore = self::get_service('OrmStore');
        $data = $OrmStore->get_data_by($class, []);//get everything
    }

}
