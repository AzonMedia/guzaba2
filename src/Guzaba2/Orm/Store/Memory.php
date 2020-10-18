<?php

declare(strict_types=1);

namespace Guzaba2\Orm\Store;

use Azonmedia\Utilities\ArrayUtil;
use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\BadMethodCallException;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Kernel\Kernel;
use Guzaba2\Kernel\Runtime;
use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Swoole\Handlers\WorkerStart;
use Guzaba2\Transaction\Interfaces\TransactionalResourceInterface;
use Guzaba2\Transaction\Interfaces\TransactionInterface;
use Guzaba2\Transaction\Interfaces\TransactionManagerInterface;
use Guzaba2\Transaction\ScopeReference;
use Guzaba2\Transaction\Transaction;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use Guzaba2\Orm\MetaStore\Interfaces\MetaStoreInterface;
use Guzaba2\Cache\Interfaces\CacheInterface;
use Guzaba2\Cache\Interfaces\CacheStatsInterface;
use Psr\Log\LogLevel;

/**
 * Class Memory
 * @package Guzaba2\Orm\Store
 */
class Memory extends Store implements StoreInterface, CacheStatsInterface, TransactionalResourceInterface
{
    protected const CONFIG_DEFAULTS = [

//        'max_rows'                      => 1,
//        'cleanup_at_percentage_usage'   => 95, //when the cleanup should be triggered
//        'cleanup_percentage_records'    => 20, //the percentage of records to be removed
//        'cleanup_expiration_time'       => 300, // 5 minutes in seconds

        'max_rows'                              => 100000,
        'cleanup_at_percentage_usage'           => 95,//when the cleanup should be triggered
        'cleanup_percentage_records'            => 20,//the percentage of records to be removed
        'cleanup_expiration_time'               => 3,// 5 minutes in seconds

        'cleanup_at_percentage_memory_limit'    => 75,//reaching this usage from the memory limit will trigger cleanup
        'match_by_complex_index_under_number_of_elements' => 100,

        'services'                              => [
            'OrmMetaStore',
            'Apm',
            'Events',
            'TransactionManager'
        ]
    ];

    protected const CONFIG_RUNTIME = [];

    protected const CHECK_MEMORY_STORE_MILLISECONDS = 10000;

    protected ?int $cleanup_timer_id = null;

//    /**
//     * @var array
//     */
//    protected array $known_classes = [];

    /**
     * @var MetaStoreInterface
     */
    protected MetaStoreInterface $MetaStore;


    /**
    \Azonmedia\Glog\LogEntries\Models\LogEntry::class   => [
    1   => [
    'unique_version_here'   => [
    'is_new_flag'           => FALSE,
    'was_new_flag'          => FALSE,
    'data'                  => [
    'log_entry_time'        => 1112233,
    'log_entry_data'        => 'some data here',
    ],
    ]
    ],
    ],
     * Multidimensional array
     * @var array
     */
    protected array $data = [
        /*

        */
    ];

    protected bool $caching_enabled_flag = true;

    /**
     * The total number of records in the cache (including various  classes, IDs, versions)
     * @var int
     */
    protected int $total_count = 0;
    protected int $hits = 0;
    protected int $misses = 0;

    /**
     * Twodimensional array like
     * $uuid_data[$uuid] = ['meta_object_id' => 'X', 'meta_class_name' => 'Y'];
     * @var array
     */
    protected array $uuid_data = [];

    /**
     * Memory constructor.
     * @param StoreInterface $FallbackStore
     * @param MetaStoreInterface|null $MetaStore
     * @throws RunTimeException
     * @throws InvalidArgumentException
     */
    public function __construct(StoreInterface $FallbackStore, ?MetaStoreInterface $MetaStore = null)
    {
        parent::__construct();

        $this->FallbackStore = $FallbackStore ?? new NullStore();
//        $this->total_count = 0;
//        $this->hits = 0;
//        $this->misses = 0;

        if ($MetaStore) {
            $this->MetaStore = $MetaStore;
        } else {
            //$this->MetaStore = self::OrmMetaStore();
            $this->MetaStore = static::get_service('OrmMetaStore');
        }

        //TODO - change it to use Server service...
        //$ServerInstance = \Swoole\Server::getInstance();
        //if ($ServerInstance) {
        $Server = Kernel::get_http_server();
        if ($Server) {
            //\Swoole\Timer::tick(1_000, function(){ print 'timer'; });
            //start the timer
            $this->start_cleanup_timer();
        } else {
            self::get_service('Events')->add_class_callback(WorkerStart::class, '_after_start', [$this, 'start_cleanup_timer']);
        }
    }

    /**
     * @param string $class
     * @return array
     * @throws RunTimeException
     * @throws BadMethodCallException
     */
    public function get_unified_columns_data(string $class): array
    {
        if (!isset($this->unified_columns_data[$class])) {
            $this->unified_columns_data[$class] = $this->FallbackStore->get_unified_columns_data($class);
        }
        return $this->unified_columns_data[$class];
    }

    /**
     * @param string $class
     * @return array
     * @throws RunTimeException
     * @throws BadMethodCallException
     */
    public function get_storage_columns_data(string $class): array
    {
        if (!isset($this->storage_columns_data[$class])) {
            $this->storage_columns_data[$class] = $this->FallbackStore->get_storage_columns_data($class);
        }
        return $this->storage_columns_data[$class];
    }

    /**
     * @param ActiveRecordInterface $ActiveRecord
     * @return string
     * @throws RunTimeException
     * @throws InvalidArgumentException
     */
    public function update_record(ActiveRecordInterface $ActiveRecord): array
    {

        if (!$this->caching_enabled()) {
            return $this->FallbackStore->update_record($ActiveRecord);
        } else {
            //$class = get_class($ActiveRecord);
            //$lookup_index = $ActiveRecord->get_lookup_index();
            //$this->data[$class][$lookup_index] = $this->process_instance();
            //if ($this->FallbackStore) {
            if (true) { //cant work without backend for now
                $all_data = $this->FallbackStore->update_record($ActiveRecord);
            }

            //the meta data needs to be updated

            $lookup_index = self::form_lookup_index($ActiveRecord->get_primary_index());
            $class = get_class($ActiveRecord);

            $new_meta = $this->FallbackStore->get_meta($class, $ActiveRecord->get_id());

            $this->update_meta_data($class, $ActiveRecord->get_primary_index(), $new_meta);


            //cleanup
            //unset($this->data[$class][$lookup_index][0]);
            //$cid = self::get_root_coroutine_id();
            //do not clean this up - yet - it is used until the transaction is committed (and there is a cleanup procedure at the coroutine end - in defer)
            //$cid = \Swoole\Coroutine::getCid();
            //unset($this->data[$class][$lookup_index]['cid_' . $cid]);

            //return $new_meta['object_uuid'];

            // TODO make sure Memory can work as Final store - needs to generate and store UUIDs
            //$data = $ActiveRecord->get_record_data();
            //$meta = $ActiveRecord->

            // TODO - update the data in the memory store here too
            //$last_update_time = $pointer['meta']['meta_object_last_update_microtime'];
            //$this->data[$class][$lookup_index][$last_update_time] =& $pointer;

//IT IS WRONG to insert the data in the memory table BEFORE the transaction is committed
//either leave the data out of the memory store or add it only afther the current memory transaction (ORM transaction) is committed
//since the API does not allow (on purpose) obtaining the current active transaction it is not possible to add a callback
//thus the data is left out of the memory store
//            if ($ActiveRecord::uses_meta()) {
//                $last_update_time = $all_data['meta']['meta_object_last_update_microtime'];
//                $this->data[$class][$lookup_index][$last_update_time] = $all_data;
//            } else {
//                //$this->data[$class][$lookup_index][0] = $all_data;//disable caching the history records
//            }
//there is added get_current_transaction()
            //instead update the pointer for the new version
            //if ($ActiveRecord::uses_meta()) {
                //$_pointer =& $this->get_data_pointer_for_new_version(get_class($ActiveRecord), $ActiveRecord->get_primary_index());
                //print '====================================';
                //print_r($_pointer);
                //$_pointer = $all_data;//this will overwrite...
            //unset($this->data[$class][$lookup_index]['cid_' . $cid]);
            //}


            $_pointer =& $this->get_data_pointer_for_new_version(get_class($ActiveRecord), $ActiveRecord->get_primary_index());
            foreach ($all_data as $key=>$value) {
                $_pointer['data'][$key] = $value;
            }
            foreach ($all_data['meta'] as $key=>$value) {
                $_pointer['meta'][$key] = $value;
            }

            if (!$this->get_current_transaction()) {
                //if there is no transaction only then can write to the cache
                if ($ActiveRecord::uses_meta()) {
                    $last_update_time = $all_data['meta']['meta_object_last_update_microtime'];
                    $this->data[$class][$lookup_index][$last_update_time] = $all_data;
                } else {
                    //$this->data[$class][$lookup_index][0] = $all_data;//disable caching the history records
                }
            }

            return $all_data;
        }
    }

    /**
     * Returns a pointer to the last unique_version of the gived class and $lookup_index
     * @param string $class
     * @param array $index
     * @return array
     * @throws LogicException
     * @throws RecordNotFoundException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     */
    public function &get_data_pointer(string $class, array $index, bool $permission_checks_disabled = false): array
    {

        if (!is_a($class, ActiveRecordInterface::class, true)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided class %1$s is not a %2$s.'), $class, ActiveRecordInterface::class));
        }

        if (!$this->caching_enabled()) {
            return $this->FallbackStore->get_data_pointer($class, $index);
        } else {
            //the provided index is array
            //check is the provided array matching the primary index
            if ($primary_index = $class::get_index_from_data($index)) {
                $lookup_index = self::form_lookup_index($primary_index);
                if (isset($this->data[$class][$lookup_index])) {
                    //if found check is it current in MetaStore
                    $last_update_time = $this->MetaStore->get_last_update_time($class, $primary_index);
                    //if ($last_update_time && isset($this->data[$class][$lookup_index][$last_update_time])) {
                    if ($last_update_time && isset($this->data[$class][$lookup_index][$last_update_time]) && empty($this->data[$class][$lookup_index][$last_update_time]['is_deleted'])) {
//                        if (!isset($this->data[$class][$lookup_index][$last_update_time]['refcount'])) {
//                            $this->data[$class][$lookup_index][$last_update_time]['refcount'] = 0;
//                        }
//                        $this->data[$class][$lookup_index][$last_update_time]['refcount']++;
                        $this->increment_refcount($class, $lookup_index, $last_update_time);
                        $this->data[$class][$lookup_index][$last_update_time]['last_access_time'] = (double) microtime(true);
                        $_pointer =& $this->data[$class][$lookup_index][$last_update_time];
                        $this->hits++;

                        $_pointer =& $this->data[$class][$lookup_index][$last_update_time];
                        $this->data[$class][$lookup_index][$last_update_time]['last_access_time'] = (double) microtime(true);
                        Kernel::log(sprintf(t::_('%1$s: Object of class %2$s with index %3$s was found in Memory Store by primary key.'), __CLASS__, $class, current($primary_index)), LogLevel::DEBUG);
                        return $_pointer;
                    }
                }
            } elseif ($uuid = $class::get_uuid_from_data($index)) {
                if (isset($this->uuid_data[$uuid])) {
                    $lookup_index = $this->uuid_data[$uuid]['meta_object_id'];
                    $class_by_uuid = $this->uuid_data[$uuid]['meta_class_name'];
                    if ($class_by_uuid !== $class) {
                        throw new LogicException(sprintf(t::_('The requested object is of class %s while the the provided UUID %s is of class %s.'), $class, $uuid, $class_by_uuid));
                    }
                    if (isset($this->data[$class][$lookup_index])) {
                        $meta_data = $this->get_meta_by_uuid($uuid);
                        $primary_index = [$meta_data['meta_object_id']];
                        //if found check is it current in MetaStore
                        $last_update_time = $this->MetaStore->get_last_update_time($class, $primary_index);

                        //if ($last_update_time && isset($this->data[$class][$lookup_index][$last_update_time])) {
                        if ($last_update_time && isset($this->data[$class][$lookup_index][$last_update_time]) && empty($this->data[$class][$lookup_index][$last_update_time]['is_deleted'])) {
//                            if (!isset($this->data[$class][$lookup_index][$last_update_time]['refcount'])) {
//                                $this->data[$class][$lookup_index][$last_update_time]['refcount'] = 0;
//                            }
//                            $this->data[$class][$lookup_index][$last_update_time]['refcount']++;
                            $this->increment_refcount($class, $lookup_index, $last_update_time);
                            $this->data[$class][$lookup_index][$last_update_time]['last_access_time'] = (double) microtime(true);
                            $this->hits++;

                            $this->data[$class][$lookup_index][$last_update_time]['last_access_time'] = (double) microtime(true);
                            $_pointer =& $this->data[$class][$lookup_index][$last_update_time];

                            Kernel::log(sprintf(t::_('%1$s: Object of class %2$s with index %3$s was found in Memory Store by UUID.'), __CLASS__, $class, current($primary_index)), LogLevel::DEBUG);
                            return $_pointer;
                        }
                    }
                }
            } elseif (array_key_exists($class, $this->data)) {
                //do a search in the available memory objects....

                //$time_start_lookup = (double) microtime(TRUE);

                //do not loop through all if there are too many records
                if (count($this->data[$class]) <= self::CONFIG_DEFAULTS['match_by_complex_index_under_number_of_elements']) {
                    foreach ($this->data[$class] as $lookup_index => $records) {
                        foreach ($records as $update_time => $record) {
                            //certain $record['data'] may have keys that are arrays (from the own properties of the models, the overloaded properties are not expected to be arrays)
                            //the option is to unset the keys that are arrays and leave only the scalars... but this may become an expensive operation...
                            //check for array values in both
                            $perform_lookup = true;

                            foreach ($record['data'] as $data_value) {
                                if (is_array($data_value)) {
                                    $perform_lookup = false;
                                }
                            }
                            if ($perform_lookup) {
                                foreach ($index as $index_value) {
                                    if (is_array($index_value)) {
                                        //this is not supported
                                        $perform_lookup = false;
                                    }
                                }
                            }
                            if ($perform_lookup) {
                                if (array_intersect_assoc($index, $record['data']) === $index) {
                                    // $index is a subset of $record['data']
                                    //if found check is it current in MetaStore
                                    $primary_index = $class::get_index_from_data($record['data']);
                                    //there has to be a valid primary index now...
                                    if (!$primary_index) {
                                        throw new LogicException(sprintf(t::_('No primary index could be obtained from the data for object of class %s and search index %s.'), $class, print_r($index, true)));
                                    }

                                    $last_update_time = $this->MetaStore->get_last_update_time($class, $primary_index);

                                    if ($last_update_time && $last_update_time === $update_time) {
//                                if (!isset($this->data[$class][$lookup_index][$last_update_time]['refcount'])) {
//                                    $this->data[$class][$lookup_index][$last_update_time]['refcount'] = 0;
//                                }
//                                $this->data[$class][$lookup_index][$last_update_time]['refcount']++;
                                        $this->increment_refcount($class, (string)$lookup_index, $last_update_time);
                                        $this->data[$class][$lookup_index][$last_update_time]['last_access_time'] = (double)microtime(true);
                                        $this->hits++;

                                        $this->data[$class][$lookup_index][$last_update_time]['last_access_time'] = (double)microtime(true);
                                        $_pointer =& $this->data[$class][$lookup_index][$last_update_time];

                                        Kernel::log(sprintf(t::_('%1$s: Object of class %2$s with index %3$s was found in Memory Store by complex key lookup.'), __CLASS__, $class, current($primary_index)), LogLevel::DEBUG);
                                        return $_pointer;
                                    }
                                }
                            }// end if $perform_lookup
                        }
                    }
                }// end if count($this->data[$class] <=...



//            $time_end_lookup = (double) microtime(TRUE);
//            $memory_lookup_time = $time_end_lookup - $time_start_lookup;
//
//            if (self::has_service('Apm') && abs($memory_lookup_time) > Kernel::MICROTIME_EPS )  {
//                $Apm = self::get_service('Apm');
//                $Apm->increment_value('memory_store_time', $memory_lookup_time);
//            }
            } else {
                //no primary index provided and no local data for this class
                //proceed to the fallback store
            }

            $this->misses++;

            $_pointer =& $this->FallbackStore->get_data_pointer($class, $index, $permission_checks_disabled);

            if (!$this->get_current_transaction()) {
                //if there is no current transaction then the cache can be updated

                $primary_index = $class::get_index_from_data($_pointer['data']);//the primary index has to be available here
                $lookup_index = self::form_lookup_index($primary_index);
                if (!$primary_index) {
                    throw new RunTimeException(sprintf(t::_('The primary index is not contained in the returned data by the previous Store for an object of class %s and requested index %s.'), $class, print_r($index, true)));
                }

                if (!$class::uses_meta()) { //do not store the objects of classes that do not use meta
                    return $_pointer;
                }
                if (!isset($_pointer['meta']['meta_object_last_update_microtime'])) {
                    throw new RunTimeException(sprintf(t::_('There is no meta data for object of class %s with id %s. This is due to corrupted data. Please correct the record.'), $class, print_r($lookup_index, true)));
                }

                $last_update_time = $_pointer['meta']['meta_object_last_update_microtime'];
                $this->data[$class][$lookup_index][$last_update_time] =& $_pointer;
                $this->total_count++;

                $uuid = $_pointer['meta']['meta_object_uuid'];
                $this->uuid_data[$uuid] = ['meta_class_name' => $class, 'primary_index' => $primary_index, 'meta_object_id' => $lookup_index];

                //there can be other versions for the same class & lookup_index
                //update the meta in the MetaStore as this record was not found in Memory which means there may be no meta either (but there could be if another worker already loaded it)
                $this->update_meta_data($class, $primary_index, $_pointer['meta']);

    //            if (!isset($this->data[$class][$lookup_index][$last_update_time]['refcount'])) {
    //                $this->data[$class][$lookup_index][$last_update_time]['refcount'] = 0;
    //            }
    //            $this->data[$class][$lookup_index][$last_update_time]['refcount']++;
                $this->increment_refcount($class, $lookup_index, $last_update_time);
                $this->data[$class][$lookup_index][$last_update_time]['last_access_time'] = (double) microtime(true);

                //check if there are older versions of this record - if their refcount is 0 then these are to be deleted
                foreach ($this->data[$class][$lookup_index] as $previous_update_time => $data) {
                    if ($data['refcount'] === 0) {
                        unset($this->data[$class][$lookup_index][$previous_update_time]);
                    }
                }
                return $this->data[$class][$lookup_index][$last_update_time];
            } else {
                return $_pointer;
            }

        }
    }

    /**
     * @param string $class
     * @param array $primary_index
     * @param array $meta_data
     * @throws InvalidArgumentException
     */
    protected function update_meta_data(string $class, array $primary_index, array $meta_data): void
    {

        if (!$this->caching_enabled()) {
            //do nothing
        } else {
            $meta = [];
            //we need to provide only the needed data
            foreach (MetaStoreInterface::DATA_STRUCT as $key_name => $value_type) {
                if (isset($meta_data[$key_name])) {
                    $meta[$key_name] = $meta_data[$key_name];
                }
            }
            if ($class::uses_meta()) {
                $this->MetaStore->set_meta_data($class, $primary_index, $meta);


                //only the last update time matters (it is also updated when an object is created)
                //$class_meta = ['object_last_update_microtime' => $meta['object_last_update_microtime'] ];
                $class_meta = $meta;
                $this->MetaStore->set_class_meta_data($class, $class_meta);
            }
        }
    }

    /**
     * Unlike get_data_pointer() which accepts any type of index there $primary_index is expected (as it is known - this method is to be invoked only by objects that are loaded).
     * When an object is being updated its pointer
     * @param string $class
     * @param array $primary_index
     * @return array
     * @throws LogicException
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \Guzaba2\Coroutine\Exceptions\ContextDestroyedException
     * @throws \ReflectionException
     * @throws RecordNotFoundException
     */
    public function &get_data_pointer_for_new_version(string $class, array $primary_index): array
    {

        if (!$this->caching_enabled()) {
            return $this->get_data_pointer($class, $primary_index);
        } else {
            // called in so the MetaStore contains the correct data
            //$this->get_data_pointer($class, $primary_index);
            $lookup_index = self::form_lookup_index($primary_index);
            $last_update_time = $this->MetaStore->get_last_update_time($class, $primary_index);

            if (isset($this->data[$class][$lookup_index][$last_update_time])) {
                $current_record_data = $this->data[$class][$lookup_index][$last_update_time];

            } else {
                //it is now possible this not to be set as the cache update is no longer done in get_data_pointer() if there is current transaction ongoing
                //throw new LogicException(sprintf(t::_('The Memory store has no data for version %s of object of class %s and primary index %s while it is expected to have that data.'), $last_update_time, $class, print_r($primary_index, true)));
                $current_record_data = $this->get_data_pointer($class, $primary_index);
                if (isset($this->data[$class][$lookup_index][$last_update_time])) {
                    $this->decrement_refcount($class, $lookup_index, $last_update_time);
                }
            }

            //$this->data[$class][$lookup_index][0] = $this->data[$class][$lookup_index][$last_update_time];//should exist and should NOT be passed by reference - the whol point is to break the reference
            //return $this->data[$class][$lookup_index][0];
            //the above is wrong - if multiple coroutines at the same time create a new version of the same object they will be pointing to the same revision - 0
            //$cid = \Co::getCid();
            //$cid = self::get_root_coroutine_id();
            //it is correct to have different data for the separate subcoroutines
            $cid = \Swoole\Coroutine::getCid();
            //better use the root coroutine as it may happen an object to be passed between coroutines

            //this also allows to use defer() for cleanup
            if (!$this->there_is_pointer_for_new_version($class, $primary_index)) {
                //!!! copying arrays actually copies the pointers
                //$this->data[$class][$lookup_index]['cid_'.$rcid] = $this->data[$class][$lookup_index][$last_update_time];//should exist and should NOT be passed by reference - the whol point is to break the reference
                //$this->data[$class][$lookup_index]['cid_'.$rcid]['modified'] = [];
                //instead a new array is to be formed from the existing one by assigning the keys one by one
                $new_arr = [];
                //foreach ($this->data[$class][$lookup_index][$last_update_time] as $key => $value) {
                foreach ($current_record_data as $key=>$value) {
                    $new_arr[$key] = $value;
                }
//                if ($this->data[$class][$lookup_index][$last_update_time]['refcount'] > 0) {
//                    $this->data[$class][$lookup_index][$last_update_time]['refcount']--;
//                }
                //why is this here???
                //$this->decrement_refcount($class, $lookup_index, $last_update_time);
//                if ($this->data[$class][$lookup_index][$last_update_time]['refcount'] === 0) {
//                    //unset($this->data[$class][$lookup_index][$last_update_time]);//always remove the old version if the refcount is 0
//                    //the old version may actually remain the current one if the object gets modified but not saved
//                    $latest_update_time = 0;
//                    foreach ($this->data[$class][$lookup_index] as $existing_last_update_time => $data) {
//                        $latest_update_time = max($existing_last_update_time, $latest_update_time);
//                    }
//                    if ($latest_update_time > $last_update_time) {
//                        //then there is a more recent record and this one can be deleted (as it is refcount 0)
//                        unset($this->data[$class][$lookup_index][$last_update_time]);
//                    } else {
//                        //leave the record in ormstore for the purpose of caching
//                    }
//                }
                $new_arr['refcount'] = 1;
                $new_arr['modified'] = [];
                $this->data[$class][$lookup_index]['cid_' . $cid] = $new_arr;
                if ($cid > 0) {
                    defer(function () use ($class, $lookup_index, $cid) {
                        unset($this->data[$class][$lookup_index]['cid_' . $cid]);
                    });
                }
            }

            return $this->data[$class][$lookup_index]['cid_' . $cid];
        }
    }

    public function there_is_pointer_for_new_version(string $class, array $primary_index): bool
    {

        if (!$this->caching_enabled()) {
            return $this->FallbackStore->there_is_pointer_for_new_version();
        } else {
            //$rcid = self::get_root_coroutine_id();
            $cid = \Swoole\Coroutine::getCid();
            $lookup_index = self::form_lookup_index($primary_index);

            return isset($this->data[$class][$lookup_index]['cid_' . $cid]);
        }
    }

    /**
     * Removes an entry from the store if there are no more references and there is a newer version.
     * If this is the last version then the object stays in the store with refcount = 0 for the purpose of caching.
     * To be called by the ActiveRecord::__destruct()
     * @param ActiveRecord $ActiveRecord
     */
    public function free_pointer(ActiveRecordInterface $ActiveRecord): void
    {
        if (!$ActiveRecord::uses_meta()) {
            return;
        }
        $class = get_class($ActiveRecord);

        $lookup_index = self::form_lookup_index($ActiveRecord->get_primary_index());
        $last_update_time = $ActiveRecord->get_meta_data()['meta_object_last_update_microtime'];

        if (!isset($this->data[$class][$lookup_index][$last_update_time])) {
            //throw new LogicException(sprintf(t::_('For instance %s:%s there is no last_update_time %s found in the Memory->data array.'), $class, $lookup_index, $last_update_time ));
            //the record may have been deleted
            //nothing to do...
            return;
        }

//        if ($this->data[$class][$lookup_index][$last_update_time]['refcount'] > 0) {
//            $this->data[$class][$lookup_index][$last_update_time]['refcount']--;
//        }
        $this->decrement_refcount($class, $lookup_index, $last_update_time);

//        if ($this->data[$class][$lookup_index][$last_update_time]['refcount'] === 0) {
//            //if this is the latest version leave it in memory for the purpose of caching
//            $latest_update_time = 0;
//            foreach ($this->data[$class][$lookup_index] as $existing_last_update_time => $data) {
//                $latest_update_time = max($existing_last_update_time, $latest_update_time);
//            }
//            if ($latest_update_time > $last_update_time) {
//                //then there is a more recent record and this one can be deleted (as it is refcount 0)
//                $this->data[$class][$lookup_index][$last_update_time] = [];
//                unset($this->data[$class][$lookup_index][$last_update_time]);
//                if (!count($this->data[$class][$lookup_index])) { //impossible but just in case
//                    unset($this->data[$class][$lookup_index]);
//                }
//            } elseif (!empty($this->data[$class][$lookup_index][$last_update_time]['is_deleted'])) {
//                $this->data[$class][$lookup_index][$last_update_time] = [];
//                unset($this->data[$class][$lookup_index][$last_update_time]);
//                if (!count($this->data[$class][$lookup_index])) {
//                    unset($this->data[$class][$lookup_index]);
//                }
//            } else {
//                //leave the record in ormstore for the purpose of caching
//            }
//        }
    }

    /**
     * @param string $class
     * @param string $lookup_index
     * @param float $time
     */
    protected function increment_refcount(string $class, string $lookup_index, float $time): void
    {
        if (!isset($this->data[$class][$lookup_index][$time]['refcount'])) {
            $this->data[$class][$lookup_index][$time]['refcount'] = 0;
        }
        $this->data[$class][$lookup_index][$time]['refcount']++;

    }

    /**
     * @param string $class
     * @param string $lookup_index
     * @param float $time
     */
    protected function decrement_refcount(string $class, string $lookup_index, float $time): void
    {
        if (isset($this->data[$class][$lookup_index][$time]['refcount']) && $this->data[$class][$lookup_index][$time]['refcount'] > 0) {
            $this->data[$class][$lookup_index][$time]['refcount']--;
        }

        $last_update_time = $time;
        if ($this->data[$class][$lookup_index][$last_update_time]['refcount'] === 0) {
            //if this is the latest version leave it in memory for the purpose of caching
            $latest_update_time = 0;
            foreach ($this->data[$class][$lookup_index] as $existing_last_update_time => $data) {
                $latest_update_time = max($existing_last_update_time, $latest_update_time);
            }
            if ($latest_update_time > $last_update_time) {
                //then there is a more recent record and this one can be deleted (as it is refcount 0)
                $this->data[$class][$lookup_index][$last_update_time] = [];
                unset($this->data[$class][$lookup_index][$last_update_time]);
                if (!count($this->data[$class][$lookup_index])) { //impossible but just in case
                    unset($this->data[$class][$lookup_index]);
                }
            } elseif (!empty($this->data[$class][$lookup_index][$last_update_time]['is_deleted'])) {
                $this->data[$class][$lookup_index][$last_update_time] = [];
                unset($this->data[$class][$lookup_index][$last_update_time]);
                if (!count($this->data[$class][$lookup_index])) {
                    unset($this->data[$class][$lookup_index]);
                }
            } else {
                //leave the record in ormstore for the purpose of caching
            }
        }
    }

    /**
     * @return array
     */
    public function &debug_get_data(): array
    {
        return $this->data;
    }

    /**
     * @param string $uuid
     * @return array
     */
    public function get_meta_by_uuid(string $uuid): array
    {
        if (!$this->caching_enabled()) {
            return $ret = $this->FallbackStore->get_meta_by_uuid($uuid);
        } else {
            if (isset($this->uuid_data[$uuid])) {
                $ret = $this->uuid_data[$uuid];
            } else {
                $ret = $this->FallbackStore->get_meta_by_uuid($uuid);
            }
            return $ret;
        }
    }

    /**
     * @param string $class_name
     * @param int $object_id
     * @return array
     */
    public function get_meta_by_id(string $class_name, int $object_id): array
    {
        if (!$this->caching_enabled()) {
            return $ret = $this->FallbackStore->get_meta_by_id($class_name, $object_id);
        } else {
            if (isset($this->data[$class_name][$object_id])) {
                $timestamp = array_key_last($this->data[$class_name][$object_id]);
                if (!$timestamp) {
                    throw new LogicException(sprintf(t::_('No timestamp was found for object of class %1$s with index %2$s.'), $class_name, $object_id));
                }
                $ret = $this->data[$class_name][$object_id][$timestamp]['meta'];
            } else {
                $ret = $this->FallbackStore->get_meta_by_id($class_name, $object_id);
            }
            return $ret;
        }
    }

    /**
     * Removes an active record data from the Store
     * @param ActiveRecordInterface $ActiveRecord
     * @throws RunTimeException
     * @throws InvalidArgumentException
     */
    public function remove_record(ActiveRecordInterface $ActiveRecord): void
    {

        if (!$this->caching_enabled()) {
            $this->FallbackStore->remove_record($ActiveRecord);
        } else {
            $class = get_class($ActiveRecord);
            $primary_index = $ActiveRecord->get_primary_index();
            $index = $ActiveRecord->get_id();

            $this->FallbackStore->remove_record($ActiveRecord);
            $this->MetaStore->remove_meta_data($class, $primary_index);

            //this is a safe delete only for the version used by the current coroutine
            //(but leaves an empty entry $data[class][index])
            // $pointer =& $this->get_data_pointer(get_class($ActiveRecord), $ActiveRecord->get_primary_index());
            // $pointer['meta'] = [];
            // $pointer['data'] = [];
            //if proper locking on obth read & write is used there cant be any other instnaces used by other corouties
            //and deleting all records/versions of this instance should not affect any other coroutines
            //there even no need to have a loop thorugh the timestamps as if there is proper locking there should be only one (last) version remaining
            //the free_pointer() should have released all previous versions (and all other coroutines/instances should have been destroyed if this coroutine has write lock)

            // foreach ($this->data[$class][$index] as $microtime=>&$object_data) {
            //     $object_data['meta'] = [];
            //     $object_data['data'] = [];
            // }
            // unset($this->data[$class][$index]);
            //if (count($this->data[$class][$index]) > 1) {
            //$message = sprintf(t::_('At the time of deletion of object of class %s with index %s there are %s versions in Memory Store. This can be due to improper locking (both read & write needs to be used) or there is a bug in the cleanup in ActiveRecord::__destruct() or Memory::free_pointer().'),

            //throw new RunTimeException($message);
            //}
            if (isset($this->data[$class][$index]) && is_array($this->data[$class][$index])) {
                foreach ($this->data[$class][$index] as $update_microtime => & $_object_data) {
                    $_object_data['is_deleted'] = true;
                    //$object_data['meta']['meta_is_deleted'] = TRUE;
                }
            }


            //the below should also be correct (see explanation above)
            //$last_version_microtime = array_key_first($this->data[$class][$index]);
            //$this->data[$class][$index][$last_version_microtime]['data'] = [];
            //$this->data[$class][$index][$last_version_microtime]['meta'] = [];
            //unset($this->data[$class][$index]);
            //instead of deleting the data now (which may affect other coroutines in the same worker) we mark it for deletion
            //which deletion will take place when refcount = 0 in free_pointer()
            //this will allow GET requests to remain without locking
            //but mandates a check that no WRITE/DELETE occurs in a GET request
            //$this->data[$class][$index][$last_version_microtime]['is_deleted'] = TRUE;

            //$this->update_meta_data($class, $ActiveRecord->get_primary_index(), $new_meta);
            $object_last_update_microtime = (int) microtime(true) * 1_000_000;
            $class_meta = ['meta_object_create_microtime' => $object_last_update_microtime, 'meta_object_last_update_microtime' => $object_last_update_microtime];
            $this->MetaStore->set_class_meta_data($class, $class_meta);
        }
    }

    /**
     * Currently relies on the fallback store.
     * @param string $class
     * @param array $index
     * @param int $offset
     * @param int $limit
     * @param bool $use_like
     * @param string|null $sort_by
     * @param bool $sort_desc
     * @param int|null $total_found_rows
     * @return iterable
     * @throws RunTimeException
     */
    public function get_data_by(string $class, array $index, int $offset = 0, int $limit = 0, bool $use_like = false, ?string $sort_by = null, bool $sort_desc = false, ?int &$_total_found_rows = null, bool $permission_checks_disabled = false): iterable
    {

        $ret = [];
        //loaded in memory means that ALL records are loaded in memory, not just some (cached)
        //this also needs to take into account the meta store class lat update timestamp
        //if it has been update everything needs to be re-read from the fallback store
        //if ($class::is_loaded_in_memory() && array_key_exists($class, $this->data) ) {
        if (false) {
            $time_start_lookup = (double) microtime(true);
            //should be initialized ... return the data from memory
            //temp solution - TODO - update to use the indexes - create new keys and references based on the column keys for speed
            foreach ($this->data[$class] as $lookup_index => $datum) {
                foreach ($datum as $last_update_microtime => $row) {
                    if (!empty($row['is_deleted'])) {
                        continue;
                    }
                    foreach ($index as $index_key => $index_value) {
                        if ($row['data'][$index_key] !== $index_value) {
                            break 2;//on the first mismatch we find go onto the next row
                        }
                    }
                    $ret[] = array_merge($row['meta'], $row['data']);
//the below does not handle correctly keys with NULL value
//                    if (count(array_intersect($index, $row['data'])) === count($index)) {
//                        $ret[] = array_merge($row['meta'], $row['data']);
//                    }
                }
            }

            $_total_found_rows = count($ret);

            $time_end_lookup = (double) microtime(true);
            $memory_lookup_time = $time_end_lookup - $time_start_lookup;

            if (self::has_service('Apm') && abs($memory_lookup_time) > Kernel::MICROTIME_EPS) {
                $Apm = self::get_service('Apm');
                $Apm->increment_value('memory_store_time', $memory_lookup_time);
            }
        } else {
            $ret = $this->FallbackStore->get_data_by($class, $index, $offset, $limit, $use_like, $sort_by, $sort_desc, $_total_found_rows, $permission_checks_disabled);
//            foreach ($ret as $row) {
//                $primary_index = $class::get_index_from_data($row);
//                $lookup_index = self::form_lookup_index($primary_index);
//                //$this->data[$class][$lookup_index][$row['meta_object_last_update_microtime']] = $row;
//                $meta = [];
//                $data = [];
//                foreach ($row as $column_name => $column_value) {
//                    if (in_array($column_name, ActiveRecordInterface::META_TABLE_COLUMNS)) {
//                        $meta[$column_name] = $column_value;
//                    } else {
//                        $data[$column_name] = $column_value;
//                    }
//                }
//                if (!$meta['meta_object_create_microtime']) {
//                    throw new RunTimeException(sprintf(t::_('No meta data found for object of class %s with index %s.'), $class, print_r($primary_index, TRUE) ));
//                }
//                $this->data[$class][$lookup_index][$row['meta_object_last_update_microtime']] = ['meta' => $meta, 'data' => $data];
//                $this->update_meta_data($class, $primary_index, $meta);
//            }
        }

        return $ret;
    }

//    public function get_data_count_by(string $class, array $index, bool $use_like = FALSE) : int
//    {
//        $ret = $this->FallbackStore->get_data_count_by($class, $index, $use_like);
//        return $ret;
//    }

    public function enable_caching(): void
    {
        $this->caching_enabled_flag = true;
    }

    public function disable_caching(): void
    {
        $this->caching_enabled_flag = false;
    }

    public function caching_enabled(): bool
    {
        return $this->caching_enabled_flag;
    }

//    public function clear_cache() : void
//    {
//        $this->data = [];
//    }

    public function get_hits(): int
    {
        // !!!!!!FIXME!!!!!! remove start timer
        //$this->start_cleanup_timer();
        return $this->hits;
    }

    public function get_misses(): int
    {
        // !!!!!!FIXME!!!!!! - remove start timer
        //$this->start_cleanup_timer();
        return $this->misses;
    }

    public function get_hits_percentage(): float
    {
        $ret = 0.0;
        $hits = $this->get_hits();
        $misses = $this->get_misses();
        $total = $hits + $misses;

        if (0 != $total) {
            $ret = (float) ($hits / $total * 100.0);
        }
        $ret = round($ret, 2);
        return $ret;
    }

    public function reset_hits(): void
    {
        $this->hits = 0;
    }

    public function reset_misses(): void
    {
        $this->misses = 0;
    }

    public function reset_stats(): void
    {
        $this->reset_hits();
        $this->reset_misses();
    }

    public function reset_all()
    {
        $this->clear_cache();
        $this->reset_stats();

        //$this->start_cleanup_timer();
    }

    /**
     *
     */
    public function start_cleanup_timer(): void
    {


        if (null === $this->cleanup_timer_id || (!\Swoole\Timer::exists($this->cleanup_timer_id))) {
//            $CleanupFunction = function () {
//                //Kernel::log('memory cleanup is running...' . PHP_EOL, LogLevel::INFO);
//                if ($this->total_count > self::CONFIG_RUNTIME['max_rows'] || ($this->total_count / self::CONFIG_RUNTIME['max_rows'] * 100.0 >= self::CONFIG_RUNTIME['cleanup_at_percentage_usage'])) {
//                    // cleanup
//                    $total_count = $this->total_count;
//                    $cleanedup = 0;
//
//                    foreach ($this->data as $class => $class_data) {
//                        foreach ($class_data as $object => $object_data) {
//                            foreach ($object_data as $last_update_time => $data) {
//                                $time = (double) microtime(TRUE);
//                                if ($data['refcount'] == 0 && ($time - $data['last_access_time'] > self::CONFIG_RUNTIME['cleanup_expiration_time'])) {
//                                    // !!!!!!!FIXME!!!!!
//                                    unset($this->data[$class][$object]);
//
//                                    $this->total_count--;
//                                    $cleanedup++;
//                                    $cleanup_percentage = $cleanedup / $total_count * 100.0;
//                                    if ($cleanup_percentage >= self::CONFIG_RUNTIME['cleanup_percentage_records']) {
//                                        $message_log = sprintf(t::_('Memory cleanup: %d records found, %d records cleaned up. Records left count: %d'), $total_count, $cleanedup, $this->total_count);
//                                        Kernel::log($message_log, LogLevel::INFO);
//                                        return;
//                                    }
//
//                                }
//                            }
//                        }
//                    }
//                }
//            };

            //$this->cleanup_timer_id = \Swoole\Timer::tick(self::CHECK_MEMORY_STORE_MILLISECONDS, $CleanupFunction);

            $Function = function (): void {
                $percentage_reached = $this->total_count / self::CONFIG_RUNTIME['max_rows'] * 100.0;
                if ($this->total_count > self::CONFIG_RUNTIME['max_rows'] || $percentage_reached >= self::CONFIG_RUNTIME['cleanup_at_percentage_usage']) {
                    $message_log = sprintf(
                        t::_('%1$s: Triggering cache cleanup because the number of rows reached %1$s which is %2$s%% out of the maximum allowed %3$s records. The cleanup is set to be triggered at reaching %4$s%%.'),
                        $this->total_count,
                        round($percentage_reached, 2),
                        self::CONFIG_RUNTIME['max_rows'],
                        self::CONFIG_RUNTIME['cleanup_at_percentage_usage']
                    );
                    Kernel::log($message_log, LogLevel::INFO);
                    $this->clear_cache();
                }

                //also trigger cache cleanup if the memory limit is nearing the limit
                $memory_percentage_reached =  Runtime::memory_get_usage() / Runtime::get_memory_limit() * 100;
                if ($memory_percentage_reached >= self::CONFIG_RUNTIME['cleanup_at_percentage_memory_limit']) {
                    $message_log = sprintf(
                        t::_('%1$s: Triggering cache cleanup because the memory usage reached %1$sMB which is %2$s%% out of the memory limit of %3$sMB. The cleanup is set to be triggered at reaching %4$s%%.'),
                        round(Runtime::memory_get_usage() / (1024 * 1024), 2),
                        round($memory_percentage_reached, 2),
                        Runtime::get_memory_limit() / (1024 * 1024),
                        self::CONFIG_RUNTIME['cleanup_at_percentage_memory_limit']
                    );
                    Kernel::log($message_log, LogLevel::INFO);
                    $this->clear_cache();
                }
            };
            $this->cleanup_timer_id = \Swoole\Timer::tick(self::CHECK_MEMORY_STORE_MILLISECONDS, $Function);
        }
    }

    public function clear_cache(int $percentage = self::CONFIG_RUNTIME['cleanup_percentage_records']): int
    {

        $start_memory_usage = Runtime::memory_get_usage() / (1024 * 1024);
        $message_log = sprintf(t::_('Memory usage before cleanup: %1$sMB'), round($start_memory_usage, 2) );
        Kernel::log($message_log, LogLevel::INFO);

        $cleanedup_data_records = $this->clear_data_cache($percentage);
        $cleanedup_uuid_records = $this->clear_uuid_cache($percentage);

        //it is very important to also trigger the GC so that the object that have cyclic references but are no longer used to be cleared.
        $collected_cycles = Runtime::gc_collect_cycles();
        $message_log = sprintf(t::_('Forced GC cycles collection - %1$s cycles collected.'), $collected_cycles);
        Kernel::log($message_log, LogLevel::INFO);

        $end_memory_usage = Runtime::memory_get_usage() / (1024 * 1024);
        $message_log = sprintf(t::_('Memory usage after cleanup: %1$sMB. Freed memory: %2$sMB.'), round($end_memory_usage, 2), round($start_memory_usage - $end_memory_usage, 2) );
        Kernel::log($message_log, LogLevel::INFO);

        return $cleanedup_data_records;
    }

    protected function clear_data_cache(int $percentage = self::CONFIG_RUNTIME['cleanup_percentage_records']): int
    {
        //Kernel::log('memory cleanup is running...' . PHP_EOL, LogLevel::INFO);
        //if ($this->total_count > self::CONFIG_RUNTIME['max_rows'] || ($this->total_count / self::CONFIG_RUNTIME['max_rows'] * 100.0 >= self::CONFIG_RUNTIME['cleanup_at_percentage_usage'])) {
        // cleanup
        $total_count = $this->total_count;
        $cleanedup = 0;
        if ($total_count) {
            foreach ($this->data as $class => $class_data) {
                foreach ($class_data as $object => $object_data) {
                    foreach ($object_data as $last_update_time => $data) {
                        $time = (double)microtime(true);
                        if ($data['refcount'] === 0 && ($time - $data['last_access_time'] > self::CONFIG_RUNTIME['cleanup_expiration_time'])) {
                            //if ($data['refcount'] === 0) {
                            // !!!!!!!FIXME!!!!! - ok?
                            unset($this->data[$class][$object][$last_update_time]);

                            $this->total_count--;
                            $cleanedup++;
                            $cleanup_percentage = $cleanedup / $total_count * 100.0;
                            if ($cleanup_percentage >= $percentage) {
                                //$message_log = sprintf(t::_('Memory cleanup: %d records found, %d records cleaned up. Records left count: %d'), $total_count, $cleanedup, $total_count - $cleanedup);
                                //Kernel::log($message_log, LogLevel::INFO);
                                //return $cleanedup;
                                break 3;
                            }
                        }
                    }
                }
            }

            $message_log = sprintf(
                t::_('%1$s: Memory store cleanup: %2$d records found, %3$d records cleaned up. Records left count: %4$d. Cleanup run with target cleanup percentage set to %5$s%%.'),
                __CLASS__,
                $total_count,
                $cleanedup,
                $total_count - $cleanedup,
                $percentage
            );
            Kernel::log($message_log, LogLevel::INFO);

        } else {

            //there are no records in the cache and the used memory is still too high
            //this means there is memory leak
            //and it is best to restart the worker
            //TODO
            $message_log = sprintf(
                t::_('%1$s: No records were removed because there are 0 cached records. This could be a sign of memory leak!'),
                __CLASS__,
                );
            Kernel::log($message_log, LogLevel::CRITICAL);
        }
        return $cleanedup;
    }

    protected function clear_uuid_cache(int $percentage = self::CONFIG_RUNTIME['cleanup_percentage_records']): int
    {
        $total_count = count($this->uuid_data);
        $cleanedup = 0;
        //starts deleting from the oldest added...
        if ($total_count) {
            foreach ($this->uuid_data as $uuid => $uuid_datum) {
                unset($this->uuid_data[$uuid]);
                $cleanedup++;
                $cleanup_percentage = $cleanedup / $total_count * 100.0;
                if ($cleanup_percentage >= $percentage) {
                    break 1;
                }
            }

            $message_log = sprintf(
                t::_('%1$s: Memory store UUID cache cleanup: %2$d records found, %3$d records cleaned up. Records left count: %4$d. Cleanup run with target cleanup percentage set to %5$s%%.'),
                __CLASS__,
                $total_count,
                $cleanedup,
                $total_count - $cleanedup,
                $percentage
            );
            Kernel::log($message_log, LogLevel::INFO);

        } else {
            //nothing cached and still running out of memory - this would mean a memory leak...
            $message_log = sprintf(
                t::_('%1$s: No UUID cache records were removed because there are 0 cached records. This could be a sign of memory leak!'),
                __CLASS__,
                );
            Kernel::log($message_log, LogLevel::CRITICAL);
        }

        return $cleanedup;
    }

    public function begin_transaction(): void
    {
        // TODO: Implement begin_transaction() method.
    }

    public function commit_transaction(): void
    {
        // TODO: Implement commit_transaction() method.
    }

    public function rollback_transaction(): void
    {
        // TODO: Implement rollback_transaction() method.
    }

    public function create_savepoint(string $savepoint_name): void
    {
        // TODO: Implement create_savepoint() method.
    }

    public function rollback_to_savepoint(string $savepoint_name): void
    {
        // TODO: Implement rollback_to_savepoint() method.
    }

    public function release_savepoint(string $savepoint_name): void
    {
        // TODO: Implement release_savepoint() method.
    }

    /**
     * @param ScopeReference|null $ScopeReference
     * @param array $options
     * @return Transaction
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function new_transaction(?ScopeReference &$ScopeReference, array $options = []): Transaction
    {

        if ($ScopeReference) {
            //$ScopeReference->set_release_reason($ScopeReference::RELEASE_REASON_OVERWRITING);
            $ScopeReference = null;//trigger rollback (and actually destroy the transaction object - the object may or may not get destroyed - it may live if part of another transaction)
        }

        $Transaction = new MemoryTransaction($this, $options);

        $ScopeReference = new ScopeReference($Transaction);

        return $Transaction;
    }

    /**
     * @return TransactionInterface|null
     * @throws RunTimeException
     * @throws \Azonmedia\Exceptions\InvalidArgumentException
     * @throws \ReflectionException
     */
    public function get_current_transaction(): ?TransactionInterface
    {
        /** @var TransactionManagerInterface $TransactionManager */
        $TransactionManager = self::get_service('TransactionManager');
        $CurrentTransaction = $TransactionManager->get_current_transaction($this->get_resource_id());
        return $CurrentTransaction;
    }

    /**
     * @return string
     */
    public function get_resource_id(): string
    {
        return get_class($this) . ':' . Coroutine::getCid();
    }

    public function get_stats(): array
    {
        $data = [
            'total_records'         => $this->total_count,
            'records_classes'       => count($this->data),
            'enabled'               => $this->caching_enabled(),
            'cache_hits'            => $this->get_hits(),
            'cache_misses'          => $this->get_misses(),
            'cache_hits_ratio'      => $this->get_hits_percentage(),
        ];
        return $data;
    }
}
