<?php

namespace Guzaba2\Orm\Store;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Coroutine\Coroutine;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Orm\Exceptions\RecordNotFoundException;

class Memory extends Store
implements StoreInterface
{

    protected const CONFIG_DEFAULTS = [
        'max_rows'                      => 100000,
        'cleanup_at_percentage_usage'   => 95,//when the cleanup should be triggered
        'cleanup_percentage_records'    => 20,//the percentage of records to be removed
    ];

    protected const CONFIG_RUNTIME = [];

    /**
     * @var StoreInterface|null
     */
    protected $FallbackStore;

    /**
     * Holds data for last updated time.
     * THIS DATA IS SHARED BETWEEN THE WORKERS
     * @var Guzaba2\Orm\SwooleTable
     */
    protected $SwooleTable;

    protected $record_structures = [];

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

    public function __construct(?StoreInterface $FallbackStore = NULL)
    {

        parent::__construct();

        if (Coroutine::inCoroutine()) {
            throw new RunTimeException(sprintf(t::_('Instances from %s need to be created before the swoole server is started. This instance is created in a coroutine whcih suggests it is being created inside the request (or other) handler of the server.'), __CLASS__));
        }

        $this->FallbackStore = $FallbackStore ?? new NullStore();


        //$this->SwooleTable = new \Swoole\Table(self::CONFIG_RUNTIME['max_rows']);
        $this->SwooleTable = new SwooleTable();
    }

    public function get_record_structure(string $class) : array
    {
        if (isset($this->record_structures[$class])) {
            $ret = $this->record_structures[$class];
        } else {
            if ($this->FallbackStore) {
                $ret = $this->FallbackStore->get_record_structure($class);
                $this->record_structures[$class] = $ret;
            } else {
                $this->throw_unknown_record_type_exception($class);
            }
        }
        return $ret;
    }

    public function get_record_storage_structure(string $class) : array
    {

    }

    /**
     * For adding newly created instances or instances.
     * It is also used when an instance that does not exist in the cache is pulled from a fallback storage.
     * @param ActiveRecordInterface $ActiveRecord
     * @return string
     */
    public function add_instance(ActiveRecordInterface $ActiveRecord) : string
    {
        $class = get_class($ActiveRecord);
        $lookup_index = $ActiveRecord->get_lookup_index();
        //$this->data[$class][$lookup_index] = $this->process_instance();
    }

    /**
     * Returns a pointer to the last unique_version of the gived class and $lookup_index
     * @param string $class
     * @param $index
     * @return array
     */
    public function &get_data_pointer( string $class, string $lookup_index) : array
    {
        //check local storage at $data
        $found = FALSE;
        if (isset($this->data[$class][$lookup_index])) {
            //if found check is it current in SwooleTable
            $key = $class.'_'.$lookup_index;
            $last_update_time = $this->SwooleTable->get_last_update_time($key);
            if ($last_update_time) {
                //check is there data for this time
                if (isset($this->data[$class][$lookup_index][$last_update_time])) {
                    $found = TRUE;
                    $pointer =& $this->data[$class][$lookup_index][$last_update_time];
                } else {
                    //this store has no current data (has for a previous version)
                    if (!$this->FallbackStore) {
                        $this->throw_not_found_exception($class, $lookup_index);
                    }
                    $pointer =& $this->FallbackStore->get_data_pointer($class, $lookup_index);

                }
            } else {
                //fallback to the next store
                if (!$this->FallbackStore) {
                    $this->throw_not_found_exception($class, $lookup_index);
                }
                $pointer =& $this->FallbackStore->get_data_pointer($class, $lookup_index);
            }

        } else {
            if (!$this->FallbackStore) {
                $this->throw_not_found_exception($class, $lookup_index);
            }
            $pointer =& $this->FallbackStore->get_data_pointer($class, $lookup_index);
        }

        if (!$found) {
            //this means the data was pulled from the fallback store
            //we need to update the local store and the update time data
            $update_data = [
                'updated_microtime'         => $pointer->updated_microtime,
                'updated_from_worker_id'    => $pointer->updated_from_worker_id,
                'updated_from_coroutine_id' => $pointer->updated_from_coroutine_id,
            ];
            $this->SwooleTable->set_update_data($key, $update_data);
            $this->data[$class][$lookup_index][$pointer->updated_microtime] = $pointer;
            //there can be other versions for the same class & lookup_index
        }

        return $pointer;
    }

}