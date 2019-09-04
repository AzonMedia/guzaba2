<?php

namespace Guzaba2\Orm\Store\Nosql;

use Guzaba2\Base\Exceptions\RunTimeException;
use Guzaba2\Orm\Interfaces\ActiveRecordInterface;
use Guzaba2\Orm\Store\Database;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;
use Guzaba2\Translator\Translator as t;
use Guzaba2\Orm\Store\NullStore;

class Redis extends Database
{
    /**
     * @var StoreInterface|null
     */
    protected $FallbackStore;

    public function __construct(StoreInterface $FallbackStore)
    {
        parent::__construct();
        $this->FallbackStore = $FallbackStore ?? new NullStore();
    }


    public function get_unified_columns_data(string $class) : array
    {
        //NI
        $ret = $this->FallbackStore->get_unified_columns_data($class);
        return $ret;
    }

    public function get_storage_columns_data(string $class) : array
    {
        //NI
        $ret = $this->FallbackStore->get_storage_columns_data($class);
        return $ret;
    }

    public function update_record(ActiveRecordInterface $ActiveRecord) : void
    {
        $this->FallbackStore->update_record($ActiveRecord);
    }

    public function &get_data_pointer(string $class, array $index) : array
    {
        //not implemented
        //currently immediately refers to falblack store
        return $this->FallbackStore->get_data_pointer($class, $index);
    }
    
    public function get_meta(string $class_name, int $object_id) : array
    {
        //not implemented
        //currently immediately refers to falblack store
        return $this->FallbackStore->get_meta($class_name, $object_id);
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
}
