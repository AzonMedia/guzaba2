<?php

namespace Guzaba2\Orm\Store;

use Guzaba2\Base\Base;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;

class Memory extends Base
{

    /**
     * @var StoreInterface|null
     */
    protected $FallbackStore;

    //protected $data = [];
    //instead of storing the data
    protected $data = [
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
    ];

    public function __construct(?StoreInterface $FallbackStore = NULL)
    {

        parent::__construct();
        $this->FallbackStore = $FallbackStore;
    }

    /**
     * For adding newly created instances.
     * @param ActiveRecordInterface $ActiveRecord
     * @return string
     */
    public function add_instance(ActiveRecordInterface $ActiveRecord) : string
    {
        $class = get_class($ActiveRecord);
        $lookup_index = $ActiveRecord->get_lookup_index();
        $this->data[$class][$lookup_index] = $this->process_instance();
    }

    /**
     * Returns a pointer to the last unique_version of the gived class and $lookup_index
     * @param string $class
     * @param $index
     * @return array
     */
    public function &get_data_pointer( string $class, string $lookup_index) : array
    {
        //load from the fallback store if not found in this
        return $this->data[$class][$lookup_index]['unique_version_here'];
    }
}