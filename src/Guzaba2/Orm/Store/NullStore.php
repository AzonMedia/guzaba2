<?php


namespace Guzaba2\Orm\Store;


use Guzaba2\Base\Base;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Translator\Translator as t;
use http\Exception\InvalidArgumentException;

/**
 * Class NullStore
 * Null is reserves keyword so it uses NullStore name.
 * Throws RecordNotFoundException when a lookup is done.
 * It is to be used always as last fallback store so that an exception is thrown if the requested method is not found anywhere in the storage chain
 * @package Guzaba2\Orm\Store
 */
class NullStore extends Store
implements StoreInterface
{

    /**
     * @var StoreInterface|null
     */
    protected $FallbackStore;

    public function __construct(?StoreInterface $FallbackStore = NULL)
    {
        parent::__construct();
        //$this->FallbackStore = $FallbackStore;
        if ($FallbackStore) {
            throw new InvalidArgumentException(sprintf(t::_('ORM Store %s does not support fallback store.'), __CLASS__));
        }
    }

    public function get_record_structure(string $class): array
    {
        return $this->get_unified_columns_data();
    }

    public function get_unified_columns_data(string $class): array
    {
        return $this->get_unified_columns_data();
    }

    public function get_storage_columns_data(string $class): array
    {
        $this->throw_unknown_record_type_exception();
        return [];
    }

    public function &get_data_pointer(string $class, string $lookup_index) : array
    {
        $this->throw_not_found_exception($class, $lookup_index);
        return [];
    }

    public function add_instance(ActiveRecordInterface $ActiveRecord) : string
    {
        //does nothing
    }
}