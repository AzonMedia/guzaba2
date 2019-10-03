<?php
declare(strict_types=1);

namespace Guzaba2\Database;

use Guzaba2\Base\Base;
use Guzaba2\Database\ConnectionFactory;
use Guzaba2\Database\ConnectionProviders\Pool;
use Guzaba2\Coroutine\Coroutine;

class ConnectionMonitor extends Base
{
    protected const CONFIG_DEFAULTS = [
        'services'      => [
            'ConnectionFactory'
        ],
        'ping_time' => 30000, // ms
    ];

    protected const CONFIG_RUNTIME = [];

    public function __construct()
    {
        parent::__construct();
    }

    public function monitor()
    {
        $ConnectionFactory = self::ConnectionFactory();

        Swoole\Timer::tick(self::CONFIG_RUNTIME['ping_time'], function () {
            $ConnectionFactory->ping_connections();
        });
    }
}
