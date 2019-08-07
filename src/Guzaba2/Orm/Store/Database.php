<?php

namespace Guzaba2\Orm\Store;

use Guzaba2\Orm\Store\Interfaces\StoreInterface;

abstract class Database extends Store
{
    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'ConnectionFactory',
        ]
    ];

    protected const CONFIG_RUNTIME = [];

    public function __construct()
    {
        parent::__construct();
    }
}
