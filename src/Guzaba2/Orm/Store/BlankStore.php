<?php

declare(strict_types=1);

namespace Guzaba2\Orm\Store;

use Guzaba2\Base\Base;
use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Base\Exceptions\InvalidArgumentException;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Translator\Translator as t;

/**
 * To be used for data creation.
 * NOT IMPLEMENTED
 * @package Guzaba2\Orm\Store
 */
class BlankStore extends Store implements StoreInterface
{
    public function __construct(?StoreInterface $FallbackStore = null)
    {
        parent::__construct();
        if ($FallbackStore) {
            throw new InvalidArgumentException(sprintf(t::_('ORM Store %s does not support fallback store.'), __CLASS__));
        }
    }

    /*
    public function get_record_structure(string $class): array
    {
        return $this->get_unified_columns_data();
    }
    */

    public function get_unified_columns_data(string $class): array
    {
        return $this->get_unified_columns_data();
    }

    public function get_storage_columns_data(string $class): array
    {
        if (!is_a($class, Guzaba2\Orm\ActiveRecordInterface::class, true)) {
            throw new InvalidArgumentException(sprintf(t::_('The provided class %s is not a %s.'), $class, ActiveRecordInterface::class));
        }

        $ret = $class::get_structure();

        if (!$ret) {
            throw new RunTimeException(sprintf(t::_('Empty structure provided in class %s'), $class));
        }
    }


    /**
     * @param string $class
     * @param array $lookup_index
     * @return array
     * @throws \Guzaba2\Orm\Exceptions\RecordNotFoundException
     */
    public function &get_data_pointer(string $class, array $lookup_index, bool $permission_checks_disabled = false): array
    {
        return $this->get_storage_columns_data($class);
    }

    public function update_record(ActiveRecordInterface $ActiveRecord): array
    {
        return ['data' => $ActiveRecord->get_record_data(), 'meta' => $ActiveRecord->get_meta_data()];
    }

    public function &get_data_pointer_for_new_version(string $class, array $primary_index): array
    {
        return $this->get_data_pointer($class, $primary_index);
    }

    public function there_is_pointer_for_new_version(string $class, array $primary_index): bool
    {
        return false;
    }

    public function free_pointer(ActiveRecordInterface $ActiveRecord): void
    {
    }

    public function debug_get_data(): array
    {
        return [];
    }

    /**
     * Removes an active record data from the Store
     * @param ActiveRecordInterface $ActiveRecord
     * @throws RunTimeException
     */
    public function remove_record(ActiveRecordInterface $ActiveRecord): void
    {
        //does nothing
    }

    /**
     * Returns class and id of object by uuid
     * @param  string $uuid
     * @return array - class and id
     */
    public function get_meta_by_uuid(string $uuid): array
    {

        return [];
    }

    /**
     * Returns class and id of object by uuid
     * @param  string $uuid
     * @return array - class and id
     */
    public function get_meta_by_id(string $class_name, int $object_id): array
    {

        return [];
    }
}
