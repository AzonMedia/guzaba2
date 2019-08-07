<?php

namespace Guzaba2\Orm\Store\Interfaces;

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
}
