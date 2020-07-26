<?php

declare(strict_types=1);

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
        'meta_object_create_microtime'               => 'integer',
        'meta_object_last_update_microtime'          => 'integer',
        //'updated_from_worker_id'    => 'int',
        //'updated_from_coroutine_id' => 'int',
    ];



    /**
     * @param string $class
     * @return int|null
     */
    public function get_class_last_update_time(string $class): ?int;

    /**
     * Returns data when an instance from a class was last created or modified
     * @param string $class
     */
    public function get_class_meta_data(string $class): ?array;

    /**
     * @param string $class
     * @param array $data
     * @throws InvalidArgumentException
     */
    public function set_class_meta_data(string $class, array $data): void;

    /**
     * @param string $class
     * @param array $primary_index
     * @return array|null
     */
    public function get_meta_data(string $class, array $primary_index): ?array;

    /**
     * @param ActiveRecord $ActiveRecord
     * @return array|null
     */
    public function get_meta_data_by_object(ActiveRecord $ActiveRecord): ?array;

    /**
     * @param string $key
     * @return float|null
     */
    public function get_last_update_time(string $class, array $primary_index): ?int;

    /**
     * @param ActiveRecord $ActiveRecord
     * @return float|null
     */
    public function get_last_update_time_by_object(ActiveRecord $ActiveRecord): ?int;


    /**
     * To be invoked when a record is updated or when a record is not present in the SwooleTable and was accessed and the lock information needs to be updated.
     * @param string $key
     * @param array $data
     * @throws InvalidArgumentException
     */
    public function set_meta_data(string $class, array $primary_index, array $data): void;

    /**
     * @param ActiveRecord $activeRecord
     * @param array $data
     * @throws InvalidArgumentException
     */
    public function set_meta_data_by_object(ActiveRecord $activeRecord, array $data): void;

    /**
     * Used when deleting a record
     *
     * @param string $class
     * @param array $primary_index
     */
    public function remove_meta_data(string $class, array $primary_index): void;

    /**
     * Used when deleting a record
     *
     * @param ActiveRecord $ActiveRecord
     */
    public function remove_meta_data_by_object(ActiveRecord $ActiveRecord): void;
}
