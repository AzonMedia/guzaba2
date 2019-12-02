<?php

namespace Guzaba2\Orm\Store\Interfaces;

use Guzaba2\Orm\ActiveRecord;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;

interface StoreInterface
{
    public const UNIFIED_COLUMNS_STRUCTURE = [
        'name'                  => 'string',
        'native_type'           => 'string',
        'php_type'              => 'string',
        'size'                  => 'int',
        'nullable'              => 'bool',
        'column_id'             => 'int',
        'primary'               => 'bool',
        'default_value'         => '', // mixed
        'autoincrement'         => 'bool',
        'key_name'              => 'string',
        'key_reference'         => 'string',
    ];

    public function get_fallback_store() : ?StoreInterface ;

    public function get_storage_columns_data(string $class) : array ;

    public function get_unified_columns_data(string $class) : array ;

    public function update_record(ActiveRecordInterface $ActiveRecord) : array ;

    public function &get_data_pointer(string $class, array $index) : array ;

    /**
     * Gets a new pointer for an object that is being updated.
     * Useful only for MemoryStore. The rest of the stores just return array (again by reference but internally in the method this reference points to a local var).
     * @param string $class
     * @param array $primary_index
     * @return array
     */
    public function &get_data_pointer_for_new_version(string $class, array $primary_index) : array ;

    /**
     * Check whethere is already an existing pointer for an object being updated.
     * Useful only for MemoryStore.
     * @param string $class
     * @param array $primary_index
     * @return bool
     */
    public function there_is_pointer_for_new_version(string $class, array $primary_index) : bool ;

    /**
     * To be called when an ActiveRecord is destroyed.
     * @param ActiveRecordInterface $ActiveRecord
     * @return void
     */
    public function free_pointer(ActiveRecordInterface $ActiveRecord) : void ;

    /**
     * Returns storage data used for debug purpose in a free format structure.
     * @return array
     */
    public function debug_get_data() : array ;

    /**
     * Removes an active record data from the Store
     * @param ActiveRecordInterface $ActiveRecord
     */
    public function remove_record(ActiveRecordInterface $ActiveRecord): void ;
}
