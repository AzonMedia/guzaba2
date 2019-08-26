<?php

namespace Guzaba2\Orm\Store\Interfaces;

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
    ];

    public function get_fallback_store() : ?StoreInterface ;

    public function &get_data_pointer(string $class, array $lookup_index) : array ;

    public function add_instance(ActiveRecordInterface $ActiveRecord) : string ;
}
