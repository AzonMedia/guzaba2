<?php
declare(strict_types=1);

namespace Guzaba2\Orm\Store;

use Guzaba2\Database\ConnectionFactory;
use Guzaba2\Orm\Store\Interfaces\StoreInterface;

/**
 * Class Database
 * @method static ConnectionFactory ConnectionFactory()
 * @package Guzaba2\Orm\Store
 */
abstract class Database extends Store
{
    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'ConnectionFactory',
            'CurrentUser',
            'TransactionManager',
        ]
    ];

    protected const CONFIG_RUNTIME = [];

    public function __construct()
    {
        parent::__construct();
    }
}
