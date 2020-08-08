<?php

declare(strict_types=1);

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

    public function get_fallback_store(): ?StoreInterface;

    public function get_storage_columns_data(string $class): array;

    public function get_unified_columns_data(string $class): array;

    public function update_record(ActiveRecordInterface $ActiveRecord): array;

    public function &get_data_pointer(string $class, array $index, bool $permission_checks_disabled = false): array;

    /**
     * Gets a new pointer for an object that is being updated.
     * Useful only for MemoryStore. The rest of the stores just return array (again by reference but internally in the method this reference points to a local var).
     * @param string $class
     * @param array $primary_index
     * @return array
     */
    public function &get_data_pointer_for_new_version(string $class, array $primary_index): array;

    /**
     * Check whethere is already an existing pointer for an object being updated.
     * Useful only for MemoryStore.
     * @param string $class
     * @param array $primary_index
     * @return bool
     */
    public function there_is_pointer_for_new_version(string $class, array $primary_index): bool;

    /**
     * To be called when an ActiveRecord is destroyed.
     * @param ActiveRecordInterface $ActiveRecord
     * @return void
     */
    public function free_pointer(ActiveRecordInterface $ActiveRecord): void;

    /**
     * Returns storage data used for debug purpose in a free format structure.
     * @return array
     */
    public function debug_get_data(): array;

    /**
     * Removes an active record data from the Store
     * @param ActiveRecordInterface $ActiveRecord
     */
    public function remove_record(ActiveRecordInterface $ActiveRecord): void;

    /**
     * Returns the meta data for the given UUID
     * @param string $uuid
     * @return array
     */
    public function get_meta_by_uuid(string $uuid): array;

    /**
     * Returns the meta data by given class & object id
     * @param string $class_name
     * @param int $object_id
     * @return array
     */
    public function get_meta_by_id(string $class_name, int $object_id): array;

    /**
     * @param string $class
     * @param array $index
     * @param int $offset
     * @param int $limit
     * @param bool $use_like
     * @param string|null $sort_by
     * @param bool $sort_desc
     * @param int|null $_total_found_rows
     * @param bool $permission_checks_disabled
     * @return iterable
     */
    //public function get_data_by(string $class, array $index, int $offset = 0, int $limit = 0, bool $use_like = false, ?string $sort_by = null, bool $sort_desc = false, ?int &$_total_found_rows = null, bool $permission_checks_disabled = false): iterable ;
}
