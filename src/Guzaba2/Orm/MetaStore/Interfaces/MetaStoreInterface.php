<?php

namespace Guzaba2\Orm\MetaStore\Interfaces;

use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Orm\ActiveRecord;

/**
 * Interface MetaStoreInterface
 * Keeps data when an object was last updated.
 * @package Guzaba2\Orm\MetaStore\Interfaces
 */
interface MetaStoreInterface
{
    public const DATA_STRUCT = [
        'object_create_microtime'               => 'integer',
        'object_last_update_microtime'          => 'integer',
        //'updated_from_worker_id'    => 'int',
        //'updated_from_coroutine_id' => 'int',
    ];

    public function get_meta_data(string $class, array $primary_index) : ?array ;

    /**
     * @param ActiveRecord $ActiveRecord
     * @return array|null
     */
    public function get_meta_data_by_object(ActiveRecord $ActiveRecord) : ?array ;

    /**
     * @param string $key
     * @return float|null
     */
    public function get_last_update_time(string $class, array $primary_index) : ?float ;

    /**
     * @param ActiveRecord $ActiveRecord
     * @return float|null
     */
    public function get_last_update_time_by_object(ActiveRecord $ActiveRecord) : ?float ;


    /**
     * To be invoked when a record is updated or when a record is not present in the SwooleTable and was accessed and the lock information needs to be updated.
     * @param string $key
     * @param array $data
     * @throws InvalidArgumentException
     */
    public function set_meta_data(string $class, array $primary_index, array $data) : void ;

    /**
     * @param ActiveRecord $activeRecord
     * @param array $data
     * @throws InvalidArgumentException
     */
    public function set_meta_data_by_object(ActiveRecord $activeRecord, array $data) : void ;
}
