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

    public function __construct(?StoreInterface $FallbackStore = NULL)
    {
        parent::__construct();
        $this->FallbackStore = $FallbackStore ?? new NullStore();
    }

    public function get_record_structure(string $class) : array
    {
        //NI
        $ret = $this->FallbackStore->get_record_structure($class);

        return $ret;
    }

    public function add_instance(ActiveRecordInterface $ActiveRecord) : string
    {

    }

    public function &get_data_pointer( string $class, string $lookup_index) : array
    {
        //not implemented
        //currently immediately refers to falblack store
        return $this->FallbackStore->get_data_pointer($class, $lookup_index);
    }
}