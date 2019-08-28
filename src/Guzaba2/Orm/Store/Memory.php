<?php

namespace Guzaba2\Orm\Store;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\LogicException;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;
use Guzaba2\Orm\MetaStore\Interfaces\metaStoreInterface;

class Memory extends Store implements StoreInterface
{
    protected const CONFIG_DEFAULTS = [
        'max_rows'                      => 100000,
        'cleanup_at_percentage_usage'   => 95,//when the cleanup should be triggered
        'cleanup_percentage_records'    => 20,//the percentage of records to be removed
        'services'      => [
            'OrmMetaStore',
        ]
    ];

    protected const CONFIG_RUNTIME = [];


    /**
     * @var MetaStoreInterface
     */
    protected $MetaStore;

    /**
     * Holds data for last updated time.
     * THIS DATA IS SHARED BETWEEN THE WORKERS
     * @var Guzaba2\Orm\SwooleTable
     */
    protected $SwooleTable;

    protected $record_structures = [];
    protected $unified_columns_data = [];
    protected $storage_columns_data = [];

    //protected $data = [];
    //instead of storing the data
    protected $data = [
        /*
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
        */
    ];

    public function __construct(StoreInterface $FallbackStore, ?MetaStoreInterface $MetaStore = NULL)
    {
        parent::__construct();

        $this->FallbackStore = $FallbackStore ?? new NullStore();

        if ($MetaStore) {
            $this->MetaStore = $MetaStore;
        } else {
            $this->MetaStore = self::OrmMetaStore();
        }
    }

    /*
    public function get_record_structure(string $class) : array
    {
        if (isset($this->record_structures[$class])) {
            $ret = $this->record_structures[$class];
        } else {
            $ret = $this->FallbackStore->get_record_structure($class);
        }
        return $ret;
    }
    */

    public function get_unified_columns_data(string $class) : array
    {
        if (isset($this->unified_columns_data[$class])) {
            $ret = $this->unified_columns_data[$class];
        } else {
            $ret = $this->FallbackStore->get_unified_columns_data($class);
        }
        return $ret;
    }

    public function get_storage_columns_data(string $class) : array
    {
        if (isset($this->storage_columns_data[$class])) {
            $ret = $this->storage_columns_data[$class];
        } else {
            $ret = $this->FallbackStore->get_storage_columns_data($class);
        }
        return $ret;
    }

    /**
     * @param ActiveRecordInterface $ActiveRecord
     * @return string
     */
    public function update_record(ActiveRecordInterface $ActiveRecord) : void
    {
        //$class = get_class($ActiveRecord);
        //$lookup_index = $ActiveRecord->get_lookup_index();
        //$this->data[$class][$lookup_index] = $this->process_instance();
        $this->FallbackStore->update_record($ActiveRecord);
    }

    /**
     * Returns a pointer to the last unique_version of the gived class and $lookup_index
     * @param string $class
     * @param $index
     * @return array
     */
    public function &get_data_pointer(string $class, array $index) : array
    {
        //$index = implode(':', array_values($lookup_index));
        //check local storage at $data
        //$lookup_index = self::form_lookup_index($index);
        //the provided index is array
        //check is the provided array matching the primary index
        $primary_index = $class::get_index_from_data($index);
        if ($primary_index) {
            $lookup_index = self::form_lookup_index($primary_index);
            if (isset($this->data[$class][$lookup_index])) {
                //if found check is it current in MetaStore
                $last_update_time = $this->MetaStore->get_last_update_time($class, $primary_index);
                //print $last_update_time.'AAA'.PHP_EOL;
                if ($last_update_time && isset($this->data[$class][$lookup_index][$last_update_time])) {
                    $pointer =& $this->data[$class][$lookup_index][$last_update_time];
                    return $pointer;
                }
            }
        } else {
            //TODO - do a search in the available memory objects....
        }

        $pointer =& $this->FallbackStore->get_data_pointer($class, $index);

        $primary_index = $class::get_index_from_data($pointer['data']);//the primary index has to be available here
        $lookup_index = self::form_lookup_index($primary_index);
        if (!$primary_index) {
            throw new RunTimeException(sprintf(t::_('The primary index is not contained in the returned data by the previous Store for an object of class %s and requested index %s.'), $class, print_r($index, TRUE)));
        }

        $last_update_time = $pointer['meta']['object_last_update_microtime'];
        //print $last_update_time.'BBB'.PHP_EOL;
        $this->data[$class][$lookup_index][$last_update_time] =& $pointer;
        //there can be other versions for the same class & lookup_index

        $meta = [];
        //we need to provide only the needed data
        foreach (MetaStoreInterface::DATA_STRUCT as $key_name=>$value_type) {
            if (isset($pointer['meta'][$key_name])) {
                $meta[$key_name] = $pointer['meta'][$key_name];
            }
        }
        $this->MetaStore->set_meta_data($class, $primary_index, $meta);

        return $this->data[$class][$lookup_index][$last_update_time];
    }

    /**
     * Unlike get_data_pointer() which accepts any type of index there $primary_index is expected (as it is known - this method is to be invoked only by objects that are loaded)
     * @param string $class
     * @param array $primary_index
     * @return array
     * @throws LogicException
     */
    public function &get_data_pointer_for_new_version(string $class, array $primary_index) : array
    {
        //at this stage the object has gone through get_data_pointer() (even if it was new it should be all test)
        //so the MetaStore should have the correct data
        $lookup_index = self::form_lookup_index($primary_index);
        $last_update_time = $this->MetaStore->get_last_update_time($class, $primary_index);
        if (isset($this->data[$class][$primary_index][$last_update_time])) {
            throw new LogicException(sprintf(t::_('The Memory store has no data for version %s of object of class %s and primary index %s while it is expected to have that data.'), $last_update_time, $class, print_r($primary_index, TRUE)));
        }
        $this->data[$class][$primary_index][0] = $this->data[$class][$primary_index][$last_update_time];//should exist and should NOT be passed by reference - the whol point is to break the reference
        return $this->data[$class][$primary_index][0];
    }
}
