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
 * Class NullStore
 * Null is reserves keyword so it uses NullStore name.
 * Throws RecordNotFoundException when a lookup is done.
 * It is to be used always as last fallback store so that an exception is thrown if the requested method is not found anywhere in the storage chain
 * @package Guzaba2\Orm\Store
 */
class NullStore extends Store implements StoreInterface
{
    public function __construct(?StoreInterface $FallbackStore = NULL)
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
        $this->throw_unknown_record_type_exception();
        return [];
    }

    /**
     * Returns class and id of object by uuid
     * @param  string $uuid
     * @return array - class and id
     */
    public function get_meta_by_uuid(string $uuid) : array
    {
        $this->throw_not_found_exception_by_uuid($uuid);
        return [];
    }

    /**
     * @param string $class
     * @param array $lookup_index
     * @return array
     * @throws \Guzaba2\Orm\Exceptions\RecordNotFoundException
     */
    public function &get_data_pointer(string $class, array $lookup_index) : array
    {
        $this->throw_not_found_exception($class, $lookup_index);
        return [];
    }

    public function update_record(ActiveRecordInterface $ActiveRecord) : array
    {
        throw new RunTimeException(sprintf(t::_('ORM Store %s does not support updating records.'), __CLASS__));
    }

    public function &get_data_pointer_for_new_version(string $class, array $primary_index) : array
    {
        return $this->get_data_pointer($class, $primary_index);
    }

    public function there_is_pointer_for_new_version(string $class, array $primary_index) : bool
    {
        return FALSE;
    }

    public function free_pointer(ActiveRecordInterface $ActiveRecord) : void
    {
    }

    public function debug_get_data() : array
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
        throw new RunTimeException(sprintf(t::_('ORM Store %s does not support deleting records.'), __CLASS__));
    }
}
